<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2017
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
	 * @return array|null Image data in format accepted by \OCA\Music\Http\FileResponse
	 */
	public function getCover($albumId, $userId, $rootFolder) {
		$hash = $this->getCachedCoverHash($albumId, $userId);
		$response = ($hash === null) ? null : $this->getCoverFromCache($hash, $userId);
		if ($response === null) {
			$response = $this->readCover($albumId, $userId, $rootFolder);
			if ($response !== null) {
				$this->addCoverToCache($albumId, $userId, $response);
			}
		}
		return $response;
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
			$albumId = explode('_', $row['key'])[1];
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
			$delimPos = strpos($cached, '|');
			$mime = substr($cached, 0, $delimPos);
			$content = substr($cached, $delimPos + 1);
			if (!$asBase64) {
				$content = base64_decode($content);
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
	 */
	private function addCoverToCache($albumId, $userId, $coverData) {
		$mime = $coverData['mimetype'];
		$content = $coverData['content'];

		if ($mime && $content) {
			$size = strlen($content);
			if ($size < self::MAX_SIZE_TO_CACHE) {
				$hash = hash('md5', $content);
				// cache the data with hash as a key
				try {
					$this->cache->add($userId, 'cover_' . $hash, $mime . '|' . base64_encode($content));
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
			}
			else {
				$this->logger->log("Cover image of album $albumId is large ($size B), skip caching", 'debug');
			}
		}
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
	 * @return array|null Image data in format accepted by \OCA\Music\Http\FileResponse
	 */
	private function readCover($albumId, $userId, $rootFolder) {
		$response = null;
		$album = $this->albumBusinessLayer->find($albumId, $userId);
		$coverId = $album->getCoverFileId();

		if ($coverId > 0) {
			$nodes = $rootFolder->getById($coverId);
			if (count($nodes) > 0) {
				// get the first valid node (there shouldn't be more than one node anyway)
				/* @var $node File */
				$node = $nodes[0];
				$mime = $node->getMimeType();

				if (0 === strpos($mime, 'audio')) { // embedded cover image
					$cover = $this->extractor->parseEmbeddedCoverArt($node);

					if ($cover !== null) {
						$response = ['mimetype' => $cover['image_mime'], 'content' => $cover['data']];
					}
				}
				else { // separate image file
					$response = ['mimetype' => $mime, 'content' => $node->getContent()];
				}
			}

			if ($response === null) {
				$this->logger->log("Requested cover not found for album $albumId, coverId=$coverId", 'error');
			} else {
				$response['content'] = self::scaleDownIfLarge($response['content'], 200);
			}
		}

		return $response;
	}

	/**
	 * Scale down images to reduce size
	 * Scale down an image keeping it's aspect ratio, if both sides of the image
	 * exceeds the given target size.
	 * @param string $image The image to be scaled down as string
	 * @param integer $size The target size of the smaller side in pixels
	 * @return string The processed image as string
	 */
	public static function scaleDownIfLarge($image, $size) {
		$meta = getimagesizefromstring($image);
		// only process pictures with width and height greater than $size pixels
		if($meta[0]>$size && $meta[1]>$size) {
			$img = imagecreatefromstring($image);
			// scale down the picture so that the smaller dimension will be $sixe pixels
			$ratio = $meta[0]/$meta[1];
			if(1.0 <=  $ratio) {
				$img = imagescale($img, $size*$ratio, $size, IMG_BICUBIC_FIXED);
			} else {
				$img = imagescale($img, $size, $size/$ratio, IMG_BICUBIC_FIXED);
			};
			ob_start();
			ob_clean();
			switch($meta['mime']) {
				case "image/jpeg":
					imagejpeg($img, NULL, 75);
					$image = ob_get_contents();
					break;
				case "image/png":
					imagepng($img, NULL, 7, PNG_ALL_FILTERS);
					$image = ob_get_contents();
					break;
				case "image/gif":
					imagegif($img, NULL);
					$image = ob_get_contents();
					break;
				default:
					break;
			}
			ob_end_clean();
			imagedestroy($img);
		}
		return $image;
	}

}
