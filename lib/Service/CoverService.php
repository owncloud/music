<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2017 - 2025
 */

namespace OCA\Music\Service;

use OCA\Music\AppFramework\Core\Logger;
use OCA\Music\AppFramework\Db\UniqueConstraintViolationException;
use OCA\Music\BusinessLayer\AlbumBusinessLayer;
use OCA\Music\Db\Album;
use OCA\Music\Db\Artist;
use OCA\Music\Db\Cache;
use OCA\Music\Db\Entity;
use OCA\Music\Db\PodcastChannel;
use OCA\Music\Db\Playlist;
use OCA\Music\Db\RadioStation;
use OCA\Music\Utility\HttpUtil;
use OCA\Music\Utility\PlaceholderImage;
use OCA\Music\Utility\Random;
use OCP\Files\Folder;
use OCP\Files\File;

use OCP\IConfig;
use OCP\IL10N;

/**
 * utility to get cover image for album
 */
class CoverService {
	private Extractor $extractor;
	private Cache $cache;
	private AlbumBusinessLayer $albumBusinessLayer;
	private int $coverSize;
	private IL10N $l10n;
	private Logger $logger;

	const MAX_SIZE_TO_CACHE = 102400;
	const DO_NOT_CROP_OR_SCALE = -1;

	public function __construct(
			Extractor $extractor,
			Cache $cache,
			AlbumBusinessLayer $albumBusinessLayer,
			IConfig $config,
			IL10N $l10n,
			Logger $logger) {
		$this->extractor = $extractor;
		$this->cache = $cache;
		$this->albumBusinessLayer = $albumBusinessLayer;
		$this->l10n = $l10n;
		$this->logger = $logger;

		// Read the cover size to use from config.php or use the default
		$this->coverSize = \intval($config->getSystemValue('music.cover_size')) ?: 380;
	}

	/**
	 * Get cover image of an album, an artist, a podcast, or a playlist.
	 * Returns a generated placeholder in case there's no art set.
	 *
	 * @param int|null $size Desired (max) image size, null to use the default.
	 *                       Special value DO_NOT_CROP_OR_SCALE can be used to opt out of
	 *                       scaling and cropping altogether.
	 * @return array|null Image data in format accepted by \OCA\Music\Http\FileResponse
	 */
	public function getCover(Entity $entity, string $userId, Folder $rootFolder, ?int $size=null, bool $allowPlaceholder=true) : ?array {
		if ($entity instanceof Playlist) {
			$trackIds = $entity->getTrackIdsAsArray();
			$albums = $this->albumBusinessLayer->findAlbumsWithCoversForTracks($trackIds, $userId, 4);
			$result = $this->getCoverMosaic($albums, $userId, $rootFolder);
		} elseif ($entity instanceof RadioStation) {
			$result = null; // only placeholders supported for radio
		} elseif ($entity instanceof Album || $entity instanceof Artist || $entity instanceof PodcastChannel) {
			if ($size !== null) {
				// Skip using cache in case the cover is requested in specific size
				$result = $this->readCover($entity, $rootFolder, $size);
			} else {
				$dataAndHash = $this->getCoverAndHash($entity, $userId, $rootFolder);
				$result = $dataAndHash['data'];
			}
		} else {
			// only placeholder is supported for any other Entity type
			$result = null;
		}	

		if ($result === null && $allowPlaceholder) {
			$result = $this->getPlaceholder($entity, $size);
		}

		return $result;
	}

	public function getCoverMosaic(array $entities, string $userId, Folder $rootFolder, ?int $size=null) : ?array {
		if (\count($entities) === 0) {
			return null;
		} elseif (\count($entities) === 1) {
			return $this->getCover($entities[0], $userId, $rootFolder, $size);
		} else {
			$covers = \array_map(fn($entity) => $this->getCover($entity, $userId, $rootFolder), $entities);
			return $this->createMosaic($covers, $size);
		}
	}

	/**
	 * Get cover image of an album or and artist along with the image's hash
	 *
	 * The hash is non-null only in case the cover is/was cached.
	 *
	 * @param Album|Artist|PodcastChannel $entity
	 * @param string $userId
	 * @param Folder $rootFolder
	 * @return array Dictionary with keys 'data' and 'hash'
	 */
	public function getCoverAndHash($entity, string $userId, Folder $rootFolder) : array {
		$hash = $this->cache->get($userId, self::getHashKey($entity));
		$data = null;

		if ($hash !== null) {
			$data = $this->getCoverFromCache($hash, $userId);
		}
		if ($data === null) {
			$hash = null;
			$data = $this->readCover($entity, $rootFolder, $this->coverSize);
			if ($data !== null) {
				$hash = $this->addCoverToCache($entity, $userId, $data);
			}
		}

		return ['data' => $data, 'hash' => $hash];
	}

	/**
	 * Get all album cover hashes for one user.
	 * @param string $userId
	 * @return array with album IDs as keys and hashes as values
	 */
	public function getAllCachedAlbumCoverHashes(string $userId) : array {
		$rows = $this->cache->getAll($userId, 'album_cover_hash_');
		$hashes = [];
		$prefixLen = \strlen('album_cover_hash_');
		foreach ($rows as $row) {
			$albumId = \substr($row['key'], $prefixLen);
			$hashes[$albumId] = $row['data'];
		}
		return $hashes;
	}

	/**
	 * Get cover image with given hash from the cache
	 *
	 * @param string $hash
	 * @param string $userId
	 * @param bool $asBase64
	 * @return array|null Image data in format accepted by \OCA\Music\Http\FileResponse
	 */
	public function getCoverFromCache(string $hash, string $userId, bool $asBase64 = false) : ?array {
		$cached = $this->cache->get($userId, 'cover_' . $hash);
		if ($cached !== null) {
			$delimPos = \strpos($cached, '|');
			$mime = \substr($cached, 0, $delimPos);
			$content = \substr($cached, $delimPos + 1);
			if (!$asBase64) {
				$content = \base64_decode($content);
			}
			return ['mimetype' => $mime, 'content' => $content];
		}
		return null;
	}

	private function getPlaceholder(Entity $entity, ?int $size) : array {
		$name = $entity->getNameString($this->l10n);
		if (\method_exists($entity, 'getAlbumArtistNameString')) {
			$seed = $entity->/** @scrutinizer ignore-call */getAlbumArtistNameString($this->l10n) . $name;
		} else {
			$seed = $name;
		}
		$size = $size > 0 ? (int)$size : $this->coverSize;
		return PlaceholderImage::generateForResponse($name, $seed, $size);
	}

	/**
	 * Cache the given cover image data
	 * @param Album|Artist|PodcastChannel $entity
	 * @param string $userId
	 * @param array $coverData
	 * @return string|null Hash of the cached cover
	 */
	private function addCoverToCache(Entity $entity, string $userId, array $coverData) : ?string {
		$mime = $coverData['mimetype'];
		$content = $coverData['content'];
		$hash = null;
		$hashKey = self::getHashKey($entity);

		if ($mime && $content) {
			$size = \strlen($content);
			if ($size < self::MAX_SIZE_TO_CACHE) {
				$hash = \hash('md5', $content);
				// cache the data with hash as a key
				try {
					$this->cache->add($userId, 'cover_' . $hash, $mime . '|' . \base64_encode($content));
				} catch (UniqueConstraintViolationException $ex) {
					$this->logger->log("Cover with hash $hash is already cached", 'debug');
				}
				// cache the hash with hashKey as a key
				try {
					$this->cache->add($userId, $hashKey, $hash);
				} catch (UniqueConstraintViolationException $ex) {
					$this->logger->log("Cover hash with key $hashKey is already cached", 'debug');
				}
				// collection.json needs to be regenerated the next time it's fetched
				$this->cache->remove($userId, 'collection');
			} else {
				$this->logger->log("Cover image of entity with key $hashKey is large ($size B), skip caching", 'debug');
			}
		}

		return $hash;
	}

	/**
	 * Remove album cover image from cache if it is there. Silently do nothing if there
	 * is no cached cover. All users are targeted if no $userId passed.
	 */
	public function removeAlbumCoverFromCache(int $albumId, ?string $userId=null) : void {
		$this->cache->remove($userId, 'album_cover_hash_' . $albumId);
	}

	/**
	 * Remove artist cover image from cache if it is there. Silently do nothing if there
	 * is no cached cover. All users are targeted if no $userId passed.
	 */
	public function removeArtistCoverFromCache(int $artistId, ?string $userId=null) : void {
		$this->cache->remove($userId, 'artist_cover_hash_' . $artistId);
	}

	/**
	 * Read cover image from the entity-specific file or URL and scale it unless the caller opts out of it
	 * @param Album|Artist|PodcastChannel $entity
	 * @param Folder $rootFolder
	 * @param int $size Maximum size for the image to read, larger images are scaled down.
	 *                  Special value DO_NOT_CROP_OR_SCALE can be used to opt out of
	 *                  scaling and cropping altogether.
	 * @return array|null Image data in format accepted by \OCA\Music\Http\FileResponse
	 */
	private function readCover($entity, Folder $rootFolder, int $size) : ?array {
		if ($entity instanceof PodcastChannel) {
			list('content' => $image, 'content_type' => $mime) = HttpUtil::loadFromUrl($entity->getImageUrl());
			if ($image !== false) {
				$response = ['mimetype' => $mime, 'content' => $image];
			} else {
				$response = null;
			}
		} else {
			$response = $this->readCoverFromLocalFile($entity, $rootFolder);
		}

		if ($response !== null) {
			if ($size !== self::DO_NOT_CROP_OR_SCALE) {
				$response = $this->scaleDownAndCrop($response, $size);
			} elseif ($response['mimetype'] === null) {
				$response['mimetype'] = self::autoDetectMime($response['content']);
			}
		}

		return $response;
	}

	/**
	 * Read cover image from the file system
	 * @param Album|Artist $entity
	 * @param Folder $rootFolder
	 * @return array|null Image data in format accepted by \OCA\Music\Http\FileResponse
	 */
	private function readCoverFromLocalFile($entity, Folder $rootFolder) : ?array {
		$response = null;

		$coverId = $entity->getCoverFileId();
		if ($coverId > 0) {
			$node = $rootFolder->getById($coverId)[0] ?? null;
			if ($node instanceof File) {
				$mime = $node->getMimeType();

				if (\strpos($mime, 'audio') === 0) { // embedded cover image
					$cover = $this->extractor->parseEmbeddedCoverArt($node); // TODO: currently only album cover supported

					if ($cover !== null) {
						$response = ['mimetype' => $cover['image_mime'], 'content' => $cover['data']];
					}
				} else { // separate image file
					$response = ['mimetype' => $mime, 'content' => $node->getContent()];
				}
			}

			if ($response === null) {
				$class = \get_class($entity);
				$this->logger->log("Requested cover not found for $class entity {$entity->getId()}, coverId=$coverId", 'error');
			}
		}

		return $response;
	}

	/**
	 * Scale down images to reduce size and crop to square shape
	 *
	 * If one of the dimensions of the image is smaller than the maximum, then just
	 * crop to square shape but do not scale.
	 * @param array $image The image to be scaled down in format accepted by \OCA\Music\Http\FileResponse
	 * @param integer $maxSize The maximum size in pixels for the square shaped output
	 * @return array The processed image in format accepted by \OCA\Music\Http\FileResponse
	 */
	public function scaleDownAndCrop(array $image, int $maxSize) : array {
		$meta = \getimagesizefromstring($image['content']);

		if ($meta !== false) {
			$srcWidth = $meta[0];
			$srcHeight = $meta[1];
		} else {
			$srcWidth = $srcHeight = 0;
			$this->logger->log('Failed to extract size of the image, skip downscaling', 'warn');
		}

		// only process picture if it's larger than target size or not perfect square
		if ($srcWidth > $maxSize || $srcHeight > $maxSize || $srcWidth != $srcHeight) {
			$img = \imagecreatefromstring($image['content']);

			if ($img === false) {
				$this->logger->log('Failed to open cover image for downscaling', 'warn');
			} else {
				$srcCropSize = \min($srcWidth, $srcHeight);
				$srcX = (int)(($srcWidth - $srcCropSize) / 2);
				$srcY = (int)(($srcHeight - $srcCropSize) / 2);

				$dstSize = \min($maxSize, $srcCropSize);
				$scaledImg = \imagecreatetruecolor($dstSize, $dstSize);

				if ($scaledImg === false) {
					$this->logger->log("Failed to create scaled image of size $dstSize x $dstSize", 'warn');
					\imagedestroy($img);
				} else {
					\imagecopyresampled($scaledImg, $img, 0, 0, $srcX, $srcY, $dstSize, $dstSize, $srcCropSize, $srcCropSize);
					\imagedestroy($img);

					\ob_start();
					\ob_clean();
					$image['mimetype'] = $meta['mime']; // override the supplied mime with the auto-detected one
					switch ($image['mimetype']) {
						case 'image/jpeg':
							imagejpeg($scaledImg, null, 75);
							$image['content'] = \ob_get_contents();
							break;
						case 'image/png':
							imagepng($scaledImg, null, 7, PNG_ALL_FILTERS);
							$image['content'] = \ob_get_contents();
							break;
						case 'image/gif':
							imagegif($scaledImg, null);
							$image['content'] = \ob_get_contents();
							break;
						default:
							$this->logger->log("Cover image type {$image['mimetype']} not supported for downscaling", 'warn');
							break;
					}
					\ob_end_clean();
					\imagedestroy($scaledImg);
				}
			}
		}
		return $image;
	}

	private function createMosaic(array $covers, ?int $size) : array {
		$size = $size ?: $this->coverSize;
		$pieceSize = $size/2;
		$mosaicImg = \imagecreatetruecolor($size, $size);
		if ($mosaicImg === false) {
			$this->logger->log("Failed to create mosaic image of size $size x $size", 'warn');
		}
		else {
			$scaleAndCopyPiece = function($pieceData, $dstImage, $dstX, $dstY, $dstSize) {
				$meta = \getimagesizefromstring($pieceData['content']);
				$srcWidth = $meta[0];
				$srcHeight = $meta[1];

				$piece = imagecreatefromstring($pieceData['content']);

				if ($piece === false) {
					$this->logger->log('Failed to open cover image to create a mosaic', 'warn');
				} else {
					\imagecopyresampled($dstImage, $piece, $dstX, $dstY, 0, 0, $dstSize, $dstSize, $srcWidth, $srcHeight);
					\imagedestroy($piece);
				}
			};

			$coordinates = [
				['x' => 0,			'y' => 0],			// top-left
				['x' => $pieceSize,	'y' => $pieceSize],	// bottom-right
				['x' => $pieceSize,	'y' => 0],			// top-right
				['x' => 0,			'y' => $pieceSize],	// bottom-left
			];

			$covers = \array_slice($covers, 0, 4);
			foreach ($covers as $i => $cover) {
				$scaleAndCopyPiece($cover, $mosaicImg, $coordinates[$i]['x'], $coordinates[$i]['y'], $pieceSize);
			}
		}

		$image = ['mimetype' => 'image/png'];
		\ob_start();
		\ob_clean();
		imagepng($mosaicImg, null, 7, PNG_ALL_FILTERS);
		$image['content'] = \ob_get_contents();
		\ob_end_clean();
		\imagedestroy($mosaicImg);

		return $image;
	}

	private static function autoDetectMime(string $imageContent) : ?string {
		$meta = \getimagesizefromstring($imageContent);
		return ($meta === false) ? null : $meta['mime'];
	}

	/**
	 * @throws \InvalidArgumentException if entity is not one of the expected types
	 */
	private static function getHashKey(Entity $entity) : string {
		if ($entity instanceof Album) {
			return 'album_cover_hash_' . $entity->getId();
		} elseif ($entity instanceof Artist) {
			return 'artist_cover_hash_' . $entity->getId();
		} elseif ($entity instanceof PodcastChannel) {
			return 'podcast_cover_hash' . $entity->getId();
		} else {
			throw new \InvalidArgumentException('Unexpected entity type');
		}
	}

	/**
	 * Create and store an access token which can be used to read cover images of a user.
	 * A user may have only one valid cover image access token at a time; the latest token
	 * always overwrites the previously obtained one.
	 *
	 * The reason this is needed is because the mediaSession in Firefox loads the cover images
	 * in a context where normal cookies and other standard request headers are not available.
	 * Hence, we need to provide the cover images as "public" resources, i.e. without requiring
	 * that the caller is logged in to the cloud. But still, we don't want to let just anyone
	 * load the user data. The solution is to use a temporary token which grants access just to
	 * the cover images. This token can be then sent as URL argument by the mediaSession.
	 */
	public function createAccessToken(string $userId) : string {
		$token = Random::secure(32);
		// It might be neater to use a dedicated DB table for this, but the generic cache table
		// will do, at least for now.
		$this->cache->set($userId, 'cover_access_token', $token);
		return $token;
	}

	/**
	 * @see CoverService::createAccessToken
	 * @throws \OutOfBoundsException if the token is not valid
	 */
	public function getUserForAccessToken(?string $token) : string {
		if ($token === null) {
			throw new \OutOfBoundsException('Cannot get user for a null token');
		}
		$userId = $this->cache->getOwner('cover_access_token', $token);
		if ($userId === null) {
			throw new \OutOfBoundsException('No userId found for the given token');
		}
		return $userId;
	}
}
