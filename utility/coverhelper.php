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

use \OCP\Files\Folder;

/**
 * utility to get cover image for album
 */
class CoverHelper {

	private $albumBusinessLayer;
	private $scanner;
	private $logger;

	public function __construct(
			AlbumBusinessLayer $albumBusinessLayer,
			Scanner $scanner,
			Logger $logger) {
		$this->albumBusinessLayer = $albumBusinessLayer;
		$this->scanner = $scanner;
		$this->logger = $logger;
	}

	/**
	 * get cover image of the album
	 *
	 * @param int $albumId
	 * @return array|null dictionary with keys 'mimetype' and 'content'
	 */
	public function getCover($albumId, $userId, $rootFolder) {
		$response = NULL;
		$album = $this->albumBusinessLayer->find($albumId, $userId);

		$nodes = $rootFolder->getById($album->getCoverFileId());
		if (count($nodes) > 0) {
			// get the first valid node (there shouldn't be more than one node anyway)
			$node = $nodes[0];
			$mime = $node->getMimeType();

			if (0 === strpos($mime, 'audio')) { // embedded cover image
				$cover = $this->scanner->parseEmbeddedCoverArt($node);

				if ($cover !== NULL) {
					$response = ['mimetype' => $cover["image_mime"], 'content' => $cover["data"]];
				}
			}
			else { // separate image file
				$response = ['mimetype' => $mime, 'content' => $node->getContent()];
			}
		}

		if ($response === NULL) {
			$this->logger->log(
					"Requested cover not found for album $albumId, ".
					"coverFileId={$album->getCoverFileId()}", 'error');
		}
		return $response;
	}
}
