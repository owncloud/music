<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2013, 2014
 * @copyright Pauli Järvinen 2017 - 2020
 */

namespace OCA\Music\BusinessLayer;

use \OCA\Music\AppFramework\BusinessLayer\BusinessLayer;
use \OCA\Music\AppFramework\Core\Logger;

use \OCA\Music\Db\Artist;
use \OCA\Music\Db\ArtistMapper;
use \OCA\Music\Db\SortBy;

use \OCA\Music\Utility\Util;

class ArtistBusinessLayer extends BusinessLayer {
	private $logger;

	public function __construct(ArtistMapper $artistMapper, Logger $logger) {
		parent::__construct($artistMapper);
		$this->logger = $logger;
	}

	/**
	 * Finds all artists who have at least one album
	 * @param string $userId the name of the user
	 * @param integer $sortBy sort order of the result set
	 * @return \OCA\Music\Db\Artist[] artists
	 */
	public function findAllHavingAlbums($userId, $sortBy=SortBy::None) {
		return $this->mapper->findAllHavingAlbums($userId, $sortBy);
	}

	/**
	 * Returns all artists filtered by genre
	 * @param int $genreId the genre to include
	 * @param string $userId the name of the user
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return \OCA\Music\Db\Artist[] artists
	 */
	public function findAllByGenre($genreId, $userId, $limit=null, $offset=null) {
		return $this->mapper->findAllByGenre($genreId, $userId, $limit, $offset);
	}

	/**
	 * Adds an artist if it does not exist already or updates an existing artist
	 * @param string $name the name of the artist
	 * @param string $userId the name of the user
	 * @return \OCA\Music\Db\Artist The added/updated artist
	 */
	public function addOrUpdateArtist($name, $userId) {
		$artist = new Artist();
		$artist->setName(Util::truncate($name, 256)); // some DB setups can't truncate automatically to column max size
		$artist->setUserId($userId);
		$artist->setHash(\hash('md5', \mb_strtolower($name)));
		return $this->mapper->insertOrUpdate($artist);
	}

	/**
	 * Use the given file as cover art for an artist if there exists an artist
	 * with name matching the file name with no cover already set.
	 * @param File $imageFile
	 * @param string $userId
	 * @return true if the file was set as cover for an artist
	 */
	public function updateCover($imageFile, $userId) {
		$name = \pathinfo($imageFile->getName(), PATHINFO_FILENAME);
		$matches = $this->findAllByName($name, $userId);

		if (!empty($matches)) {
			$artist = $matches[0];
			if ($artist->getCoverFileId() === null) {
				$artist->setCoverFileId($imageFile->getId());
				$this->mapper->update($artist);
				return true;
			}
		}

		return false;
	}

	/**
	 * removes the given cover art files from artists
	 * @param integer[] $coverFileIds the file IDs of the cover images
	 * @param string[]|null $userIds the users whose music library is targeted; all users are targeted if omitted
	 * @return Artist[] artists which got modified, empty array if none
	 */
	public function removeCovers($coverFileIds, $userIds=null) {
		return $this->mapper->removeCovers($coverFileIds, $userIds);
	}
}
