<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2017 - 2020
 */

namespace OCA\Music\Utility;

use \OCA\Music\AppFramework\Core\Logger;
use \OCA\Music\BusinessLayer\AlbumBusinessLayer;
use \OCA\Music\Db\Cache;

use \OCP\Files\Folder;
use \OCP\Files\File;

use \Doctrine\DBAL\Exception\UniqueConstraintViolationException;

/**
 * utility to get cover image for album
 */
class CoverHelper {
	private $albumBusinessLayer;
	private $extractor;
	private $cache;
	private $logger;

	const MAX_SIZE_TO_CACHE = 102400;

	public function __construct(
			AlbumBusinessLayer $albumBusinessLayer,
			Extractor $extractor,
			Cache $cache,
			Logger $logger) {
		$this->albumBusinessLayer = $albumBusinessLayer;
		$this->extractor = $extractor;
		$this->cache = $cache;
		$this->logger = $logger;
	}

	/**
	 * Get cover image of the album
	 *
	 * @param int $albumId
	 * @param string $userId
	 * @param Folder $rootFolder
	 * @param int|null $size
	 * @return array|null Image data in format accepted by \OCA\Music\Http\FileResponse
	 */
	public function getCover($albumId, $userId, $rootFolder, $size) {
		// Skip using cache in case the cover is requested in specific size
		if ($size) {
			return $this->readCover($albumId, $userId, $rootFolder, $size);
		} else {
			$dataAndHash = $this->getCoverAndHash($albumId, $userId, $rootFolder);
			return $dataAndHash['data'];
		}
	}

	/**
	 * Get cover image of the album along with its hash
	 * 
	 * The hash is non-null only in case the cover is/was cached.
	 *
	 * @param int $albumId
	 * @param string $userId
	 * @param Folder $rootFolder
	 * @return array Dictionary with keys 'data' and 'hash'
	 */
	public function getCoverAndHash($albumId, $userId, $rootFolder) {
		$hash = $this->getCachedCoverHash($albumId, $userId);
		$data = null;

		if ($hash !== null) {
			$data = $this->getCoverFromCache($hash, $userId);
		}
		if ($data === null) {
			$hash = null;
			$data = $this->readCover($albumId, $userId, $rootFolder);
			if ($data !== null) {
				$hash = $this->addCoverToCache($albumId, $userId, $data);
			}
		}

		return ['data' => $data, 'hash' => $hash];
	}

	/**
	 * Get hash of the album cover if it is in the cache
	 *
	 * @param int $albumId
	 * @param string $userId
	 * @return string|null
	 */
	public function getCachedCoverHash($albumId, $userId) {
		return $this->cache->get($userId, 'coverhash_' . $albumId);
	}

	/**
	 * Get all album cover hashes for one user.
	 * @param string $userId
	 * @return array with album IDs as keys and hashes as values
	 */
	public function getAllCachedCoverHashes($userId) {
		$rows = $this->cache->getAll($userId, 'coverhash_');
		$hashes = [];
		foreach ($rows as $row) {
			$albumId = \explode('_', $row['key'])[1];
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
	public function getCoverFromCache($hash, $userId, $asBase64 = false) {
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
	 * @param int $albumId
	 * @param string $userId
	 * @param array $coverData
	 * @return string|null Hash of the cached cover
	 */
	private function addCoverToCache($albumId, $userId, $coverData) {
		$mime = $coverData['mimetype'];
		$content = $coverData['content'];
		$hash = null;

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
				// cache the hash with albumId as a key
				try {
					$this->cache->add($userId, 'coverhash_' . $albumId, $hash);
				} catch (UniqueConstraintViolationException $ex) {
					$this->logger->log("Cover hash for album $albumId is already cached", 'debug');
				}
				// collection.json needs to be regenrated the next time it's fetched
				$this->cache->remove($userId, 'collection');
			} else {
				$this->logger->log("Cover image of album $albumId is large ($size B), skip caching", 'debug');
			}
		}

		return $hash;
	}

	/**
	 * Remove cover image from cache if it is there. Silently do nothing if there
	 * is no cached cover.
	 * @param int $albumId
	 * @param string $userId
	 */
	public function removeCoverFromCache($albumId, $userId) {
		$this->cache->remove($userId, 'coverhash_' . $albumId);
	}

	/**
	 * Read cover image from the file system
	 * @param integer $albumId
	 * @param string $userId
	 * @param Folder $rootFolder
	 * @param int $size
	 * @return array|null Image data in format accepted by \OCA\Music\Http\FileResponse
	 */
	private function readCover($albumId, $userId, $rootFolder, $size=380) {
		$response = null;
		$album = $this->albumBusinessLayer->find($albumId, $userId);
		$coverId = $album->getCoverFileId();

		if ($coverId > 0) {
			$nodes = $rootFolder->getById($coverId);
			if (\count($nodes) > 0) {
				// get the first valid node (there shouldn't be more than one node anyway)
				/* @var $node File */
				$node = $nodes[0];
				$mime = $node->getMimeType();

				if (\strpos($mime, 'audio') === 0) { // embedded cover image
					$cover = $this->extractor->parseEmbeddedCoverArt($node);

					if ($cover !== null) {
						$response = ['mimetype' => $cover['image_mime'], 'content' => $cover['data']];
					}
				} else { // separate image file
					$response = ['mimetype' => $mime, 'content' => $node->getContent()];
				}
			}

			if ($response === null) {
				$this->logger->log("Requested cover not found for album $albumId, coverId=$coverId", 'error');
			} else {
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
			}
			else {
				$srcCropSize = \min($srcWidth, $srcHeight);
				$srcX = ($srcWidth - $srcCropSize) / 2;
				$srcY = ($srcHeight - $srcCropSize) / 2;

				$dstSize = \min($maxSize, $srcCropSize);
				$scaledImg = \imagecreatetruecolor($dstSize, $dstSize);
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
		return $image;
	}
}
