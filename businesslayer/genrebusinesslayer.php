<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2020
 */

namespace OCA\Music\BusinessLayer;

use \OCA\Music\AppFramework\BusinessLayer\BusinessLayer;
use \OCA\Music\AppFramework\Core\Logger;

use \OCA\Music\Db\Genre;
use \OCA\Music\Db\GenreMapper;
use \OCA\Music\Db\TrackMapper;

use \OCA\Music\Utility\Util;

class GenreBusinessLayer extends BusinessLayer {
	private $trackMapper;
	private $logger;

	public function __construct(GenreMapper $genreMapper, TrackMapper $trackMapper, Logger $logger) {
		parent::__construct($genreMapper);
		$this->trackMapper = $trackMapper;
		$this->logger = $logger;
	}

	/**
	 * Adds a genre if it does not exist already (in case insensitive sense) or updates an existing genre
	 * @param string $name the name of the genre
	 * @param string $userId the name of the user
	 * @return \OCA\Music\Db\Genre The added/updated genre
	 */
	public function addOrUpdateGenre($name, $userId) {
		$name = Util::truncate($name, 64); // some DB setups can't truncate automatically to column max size

		$genre = new Genre();
		$genre->setName($name);
		$genre->setLowerName(\mb_strtolower($name));
		$genre->setUserId($userId);
		return $this->mapper->insertOrUpdate($genre);
	}

	/**
	 * Returns all genres of the user, along with the contained track IDs
	 * @param string $userId
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return Genre[] where each instance has also the trackIds property set
	 */
	public function findAllWithTrackIds($userId, $limit=null, $offset=null) {
		$genres = $this->findAll($userId, $limit, $offset);
		$tracksByGenre = $this->trackMapper->mapGenreIdsToTrackIds($userId);

		foreach ($genres as &$genre) {
			$genre->setTrackIds($tracksByGenre[$genre->getId()]);
		}
		return $genres;
	}

	/**
	 * Retrun all genres of the user, along with count of tracks, albums, and artists on each genre
	 * @param string $userId
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return Genre[]
	 */
	public function findAllWithCounts($userId, $limit=null, $offset=null) {
		return $this->mapper->findAllWithCounts($userId, $limit, $offset);
	}
}
