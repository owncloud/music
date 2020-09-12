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
 * @copyright Pauli Järvinen 2016 - 2020
 */

namespace OCA\Music\BusinessLayer;

use \OCA\Music\AppFramework\BusinessLayer\BusinessLayer;
use \OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use \OCA\Music\AppFramework\Core\Logger;

use \OCA\Music\Db\AlbumMapper;
use \OCA\Music\Db\Album;
use \OCA\Music\Db\SortBy;

use \OCA\Music\Utility\Util;

class AlbumBusinessLayer extends BusinessLayer {
	protected $mapper; // eclipse the definition from the base class, to help IDE and Scrutinizer to know the actual type
	private $logger;

	public function __construct(AlbumMapper $albumMapper, Logger $logger) {
		parent::__construct($albumMapper);
		$this->mapper = $albumMapper;
		$this->logger = $logger;
	}

	/**
	 * Return an album
	 * @param integer $albumId the id of the album
	 * @param string $userId the name of the user
	 * @return Album album
	 */
	public function find($albumId, $userId) {
		$album = parent::find($albumId, $userId);
		return $this->injectExtraFields([$album], $userId)[0];
	}

	/**
	 * Returns all albums
	 * @param string $userId the name of the user
	 * @param integer $sortBy Sorting order of the result, default to unspecified
	 * @param integer|null $limit
	 * @param integer|null $offset
	 * @return Album[] albums
	 */
	public function findAll($userId, $sortBy=SortBy::None, $limit=null, $offset=null) {
		$albums = parent::findAll($userId, $sortBy, $limit, $offset);
		return $this->injectExtraFields($albums, $userId, true);
	}

	/**
	 * Returns all albums filtered by artist (both album and track artists are considered)
	 * @param integer $artistId the id of the artist
	 * @param string $userId the name of the user
	 * @return Album[] albums
	 */
	public function findAllByArtist($artistId, $userId) {
		$albums = $this->mapper->findAllByArtist($artistId, $userId);
		return $this->injectExtraFields($albums, $userId);
	}

	/**
	 * Returns all albums filtered by album artist
	 * @param integer $artistId the id of the artist
	 * @param string $userId the name of the user
	 * @return Album[] albums
	 */
	public function findAllByAlbumArtist($artistId, $userId) {
		$albums = $this->mapper->findAllByAlbumArtist($artistId, $userId);
		$albums = $this->injectExtraFields($albums, $userId);
		\usort($albums, ['\OCA\Music\Db\Album', 'compareYearAndName']);
		return $albums;
	}

	/**
	 * Returns all albums filtered by genre
	 * @param int $genreId the genre to include
	 * @param string $userId the name of the user
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return Album[] albums
	 */
	public function findAllByGenre($genreId, $userId, $limit=null, $offset=null) {
		$albums = $this->mapper->findAllByGenre($genreId, $userId, $limit, $offset);
		return $this->injectExtraFields($albums, $userId);
	}

	/**
	 * Returns all albums filtered by release year
	 * @param int $fromYear
	 * @param int $toYear
	 * @param string $userId the name of the user
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return Album[] albums
	 */
	public function findAllByYearRange($fromYear, $toYear, $userId, $limit=null, $offset=null) {
		$reverseOrder = false;
		if ($fromYear > $toYear) {
			$reverseOrder = true;
			Util::swap($fromYear, $toYear);
		}

		// Implement all the custom logic of this function here, without special Mapper function
		$albums = \array_filter($this->findAll($userId), function($album) use ($fromYear, $toYear) {
			$years = $album->getYears();
			return (!empty($years) && \min($years) <= $toYear && \max($years) >= $fromYear);
		});

		\usort($albums, function($album1, $album2) use ($reverseOrder) {
			return $reverseOrder
				? $album2->yearToAPI() - $album1->yearToAPI()
				: $album1->yearToAPI() - $album2->yearToAPI();
		});

		if ($limit !== null || $offset !== null) {
			$albums = \array_slice($albums, $offset ?: 0, $limit);
		}

		return $albums;
	}

	/**
	 * Return all albums with name matching the search criteria
	 * @param string $name
	 * @param string $userId
	 * @param bool $fuzzy
	 * @param integer $limit
	 * @param integer $offset
	 * @return Album[]
	 */
	public function findAllByName($name, $userId, $fuzzy = false, $limit=null, $offset=null) {
		$albums = parent::findAllByName($name, $userId, $fuzzy, $limit, $offset);
		return $this->injectExtraFields($albums, $userId);
	}

	/**
	 * Add performing artists, release years, genres, and disk counts to the given album objects
	 * @param Album[] $albums
	 * @param string $userId
	 * @param bool $allAlbums Set to true if $albums contains all albums of the user.
	 *                        This has now effect on the outcome but helps in optimizing
	 *                        the database query.
	 * @return Album[]
	 */
	private function injectExtraFields($albums, $userId, $allAlbums = false) {
		if (\count($albums) > 0) {
			// In case we are injecting data to a lot of albums, do not limit the
			// SQL SELECTs to only those albums. Very large amount of SQL host parameters
			// could cause problems with SQLite (see #239) and probably it would be bad for
			// performance also on other DBMSs. For the proper operation of this function,
			// it doesn't matter if we fetch data for some extra albums.
			$albumIds = ($allAlbums || \count($albums) >= 999)
					? null : Util::extractIds($albums);

			$artists = $this->mapper->getPerformingArtistsByAlbumId($albumIds, $userId);
			$years = $this->mapper->getYearsByAlbumId($albumIds, $userId);
			$diskCounts = $this->mapper->getDiscCountByAlbumId($albumIds, $userId);
			$genres = $this->mapper->getGenresByAlbumId($albumIds, $userId);

			foreach ($albums as &$album) {
				$albumId = $album->getId();
				$album->setArtistIds($artists[$albumId]);
				$album->setNumberOfDisks($diskCounts[$albumId]);
				$album->setGenres(Util::arrayGetOrDefault($genres, $albumId));
				$album->setYears(Util::arrayGetOrDefault($years, $albumId));
			}
		}
		return $albums;
	}

	/**
	 * Returns the count of albums where the given Artist is featured in
	 * @param integer $artistId
	 * @return integer
	 */
	public function countByArtist($artistId) {
		return $this->mapper->countByArtist($artistId);
	}

	/**
	 * Returns the count of albums where the given artist is the album artist
	 * @param integer $artistId
	 * @return integer
	 */
	public function countByAlbumArtist($artistId) {
		return $this->mapper->countByAlbumArtist($artistId);
	}

	public function findAlbumOwner($albumId) {
		$entities = $this->findById([$albumId]);
		if (\count($entities) != 1) {
			throw new BusinessLayerException(
					'Expected to find one album but got ' . \count($entities));
		} else {
			return $entities[0]->getUserId();
		}
	}

	/**
	 * Adds an album if it does not exist already or updates an existing album
	 * @param string $name the name of the album
	 * @param integer $albumArtistId
	 * @param string $userId
	 * @return Album The added/updated album
	 */
	public function addOrUpdateAlbum($name, $albumArtistId, $userId) {
		$album = new Album();
		$album->setName(Util::truncate($name, 256)); // some DB setups can't truncate automatically to column max size
		$album->setUserId($userId);
		$album->setAlbumArtistId($albumArtistId);

		// Generate hash from the set of fields forming the album identity to prevent duplicates.
		// The uniqueness of album name is evaluated in case-insensitive manner.
		$lowerName = \mb_strtolower($album->getName());
		$hash = \hash('md5', "$lowerName|$albumArtistId");
		$album->setHash($hash);

		return $this->mapper->insertOrUpdate($album);
	}

	/**
	 * Check if given file is used as cover for the given album
	 * @param int $albumId
	 * @param int[] $fileIds
	 * @return boolean
	 */
	public function albumCoverIsOneOfFiles($albumId, $fileIds) {
		$albums = $this->findById([$albumId]);
		return (\count($albums) && \in_array($albums[0]->getCoverFileId(), $fileIds));
	}

	/**
	 * updates the cover for albums in the specified folder without cover
	 * @param integer $coverFileId the file id of the cover image
	 * @param integer $folderId the file id of the folder where the albums are looked from
	 * @return boolean True if one or more albums were influenced
	 */
	public function updateFolderCover($coverFileId, $folderId) {
		return $this->mapper->updateFolderCover($coverFileId, $folderId);
	}

	/**
	 * set cover file for a specified album
	 * @param integer $coverFileId the file id of the cover image
	 * @param integer $albumId the id of the album to be modified
	 */
	public function setCover($coverFileId, $albumId) {
		$this->mapper->setCover($coverFileId, $albumId);
	}

	/**
	 * removes the cover art from albums, replacement covers will be searched in a background task
	 * @param integer[] $coverFileIds the file IDs of the cover images
	 * @param string[]|null $userIds the users whose music library is targeted; all users are targeted if omitted
	 * @return Album[] albums which got modified, empty array if none
	 */
	public function removeCovers($coverFileIds, $userIds=null) {
		return $this->mapper->removeCovers($coverFileIds, $userIds);
	}

	/**
	 * try to find cover arts for albums without covers
	 * @param string|null $userId target user; omit to target all users
	 * @return array of users whose collections got modified
	 */
	public function findCovers($userId = null) {
		$affectedUsers = [];
		$albums = $this->mapper->getAlbumsWithoutCover($userId);
		foreach ($albums as $album) {
			if ($this->mapper->findAlbumCover($album['albumId'], $album['parentFolderId'])) {
				$affectedUsers[$album['userId']] = 1;
			}
		}
		return \array_keys($affectedUsers);
	}
}
