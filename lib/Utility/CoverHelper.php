<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2017 - 2021
 */

namespace OCA\Music\Utility;

use \OCA\Music\AppFramework\Core\Logger;
use \OCA\Music\AppFramework\Db\UniqueConstraintViolationException;
use \OCA\Music\Db\Album;
use \OCA\Music\Db\Artist;
use \OCA\Music\Db\Cache;

use \OCP\Files\Folder;
use \OCP\Files\File;

use \OCP\IConfig;

/**
 * utility to get cover image for album
 */
class CoverHelper {
	private $extractor;
	private $cache;
	private $coverSize;
	private $logger;

	const MAX_SIZE_TO_CACHE = 102400;
	const DO_NOT_CROP_OR_SCALE = -1;

	public function __construct(
			Extractor $extractor,
			Cache $cache,
			IConfig $config,
			Logger $logger) {
		$this->extractor = $extractor;
		$this->cache = $cache;
		$this->logger = $logger;

		// Read the cover size to use from config.php or use the default
		$this->coverSize = \intval($config->getSystemValue('music.cover_size')) ?: 380;
	}

	/**
	 * Get cover image of an album or and artist
	 *
	 * @param Album|Artist $entity
	 * @param string $userId
	 * @param Folder $rootFolder
	 * @param int|null $size Desired (max) image size, null to use the default.
	 *                       Special value DO_NOT_CROP_OR_SCALE can be used to opt out of
	 *                       scaling and cropping altogether.
	 * @return array|null Image data in format accepted by \OCA\Music\Http\FileResponse
	 */
	public function getCover($entity, string $userId, Folder $rootFolder, int $size=null) {
		// Skip using cache in case the cover is requested in specific size
		if ($size !== null) {
			return $this->readCover($entity, $rootFolder, $size);
		} else {
			$dataAndHash = $this->getCoverAndHash($entity, $userId, $rootFolder);
			return $dataAndHash['data'];
		}
	}

	/**
	 * Get cover image of an album or and artist along with the image's hash
	 *
	 * The hash is non-null only in case the cover is/was cached.
	 *
	 * @param Album|Artist $entity
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
	public function getCoverFromCache(string $hash, string $userId, bool $asBase64 = false) {
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

	/**
	 * Cache the given cover image data
	 * @param Album|Artist $entity
	 * @param string $userId
	 * @param array $coverData
	 * @return string|null Hash of the cached cover
	 */
	private function addCoverToCache($entity, string $userId, array $coverData) {
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
				// collection.json needs to be regenrated the next time it's fetched
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
	public function removeAlbumCoverFromCache(int $albumId, string $userId=null) {
		$this->cache->remove($userId, 'album_cover_hash_' . $albumId);
	}

	/**
	 * Remove artist cover image from cache if it is there. Silently do nothing if there
	 * is no cached cover. All users are targeted if no $userId passed.
	 */
	public function removeArtistCoverFromCache(int $artistId, string $userId=null) {
		$this->cache->remove($userId, 'artist_cover_hash_' . $artistId);
	}

	/**
	 * Read cover image from the file system
	 * @param Album|Artist $entity
	 * @param Folder $rootFolder
	 * @param int $size Maximum size for the image to read, larger images are scaled down.
	 *                  Special value DO_NOT_CROP_OR_SCALE can be used to opt out of
	 *                  scaling and cropping altogether.
	 * @return array|null Image data in format accepted by \OCA\Music\Http\FileResponse
	 */
	private function readCover($entity, Folder $rootFolder, int $size) {
		$response = null;
		$coverId = $entity->getCoverFileId();

		if ($coverId > 0) {
			$nodes = $rootFolder->getById($coverId);
			if (\count($nodes) > 0) {
				// get the first valid node (there shouldn't be more than one node anyway)
				/* @var $node File */
				$node = $nodes[0];
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
			} elseif ($size !== self::DO_NOT_CROP_OR_SCALE) {
				$response['content'] = $this->scaleDownAndCrop($response['content'], $size);
			}
		}

		return $response;
	}

	/**
	 * Scale down images to reduce size and crop to square shape
	 *
	 * If one of the dimensions of the image is smaller than the maximum, then just
	 * crop to square shape but do not scale.
	 * @param string $image The image to be scaled down as string
	 * @param integer $maxSize The maximum size in pixels for the square shaped output
	 * @return string The processed image as string
	 */
	public function scaleDownAndCrop($image, $maxSize) {
		$meta = \getimagesizefromstring($image);
		$srcWidth = $meta[0];
		$srcHeight = $meta[1];

		// only process picture if it's larger than target size or not perfect square
		if ($srcWidth > $maxSize || $srcHeight > $maxSize || $srcWidth != $srcHeight) {
			$img = imagecreatefromstring($image);

			if ($img === false) {
				$this->logger->log('Failed to open cover image for downscaling', 'warning');
			} else {
				$srcCropSize = \min($srcWidth, $srcHeight);
				$srcX = (int)(($srcWidth - $srcCropSize) / 2);
				$srcY = (int)(($srcHeight - $srcCropSize) / 2);

				$dstSize = \min($maxSize, $srcCropSize);
				$scaledImg = \imagecreatetruecolor($dstSize, $dstSize);

				if ($scaledImg === false) {
					$this->logger->log("Failed to create scaled image of size $dstSize x $dstSize", 'warning');
					\imagedestroy($img);
				} else {
					\imagecopyresampled($scaledImg, $img, 0, 0, $srcX, $srcY, $dstSize, $dstSize, $srcCropSize, $srcCropSize);
					\imagedestroy($img);

					\ob_start();
					\ob_clean();
					$mime = $meta['mime'];
					switch ($mime) {
						case 'image/jpeg':
							imagejpeg($scaledImg, null, 75);
							$image = \ob_get_contents();
							break;
						case 'image/png':
							imagepng($scaledImg, null, 7, PNG_ALL_FILTERS);
							$image = \ob_get_contents();
							break;
						case 'image/gif':
							imagegif($scaledImg, null);
							$image = \ob_get_contents();
							break;
						default:
							$this->logger->log("Cover image type $mime not supported for downscaling", 'warning');
							break;
					}
					\ob_end_clean();
					\imagedestroy($scaledImg);
				}
			}
		}
		return $image;
	}

	/**
	 * @param Album|Artist $entity
	 * @throws \InvalidArgumentException if entity is not one of the expected types
	 * @return string
	 */
	private static function getHashKey($entity) {
		if ($entity instanceof Album) {
			return 'album_cover_hash_' . $entity->getId();
		} elseif ($entity instanceof Artist) {
			return 'artist_cover_hash_' . $entity->getId();
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
	 * @see CoverHelper::createAccessToken
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
