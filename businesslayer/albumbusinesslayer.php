<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @copyright Morris Jobke 2013, 2014
 */

namespace OCA\Music\BusinessLayer;

use \OCA\Music\AppFramework\BusinessLayer\BusinessLayer;
use \OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use \OCA\Music\AppFramework\Core\Logger;

use \OCA\Music\Db\AlbumMapper;
use \OCA\Music\Db\Album;
use \OCA\Music\Db\SortBy;

class AlbumBusinessLayer extends BusinessLayer {

	private $logger;

	public function __construct(AlbumMapper $albumMapper, Logger $logger){
		parent::__construct($albumMapper);
		$this->logger = $logger;
	}

	/**
	 * Return an album
	 * @param string $albumId the id of the album
	 * @param string $userId the name of the user
	 * @return Album album
	 */
	public function find($albumId, $userId){
		$album = $this->mapper->find($albumId, $userId);
		return $this->injectArtistsAndYears([$album])[0];
	}

	/**
	 * Returns all albums
	 * @param string $userId the name of the user
	 * @param integer $limit
	 * @param integer $offset
	 * @return Album[] albums
	 */
	public function findAll($userId, $sortBy=SortBy::None, $limit=null, $offset=null){
		$albums = $this->mapper->findAll($userId, $sortBy, $limit, $offset);
		return $this->injectArtistsAndYears($albums);
	}

	/**
	 * Returns all albums filtered by artist
	 * @param string $artistId the id of the artist
	 * @return Album[] albums
	 */
	public function findAllByArtist($artistId, $userId){
		$albums = $this->mapper->findAllByArtist($artistId, $userId);
		return $this->injectArtistsAndYears($albums);
	}

	/**
	 * Return all albums with name matching the search criteria
	 * @param string $name
	 * @param string $userId
	 * @param bool $fuzzy
	 * @return Album[]
	 */
	public function findAllByName($name, $userId, $fuzzy = false){
		$albums = parent::findAllByName($name, $userId, $fuzzy);
		return $this->injectArtistsAndYears($albums);
	}

	private function injectArtistsAndYears($albums){
		if (count($albums) > 0) {
			$albumIds = array_map(function($a) {return $a->getId();}, $albums);
			$albumArtists = $this->mapper->getAlbumArtistsByAlbumId($albumIds);
			$years = $this->mapper->getYearsByAlbumId($albumIds);
			foreach ($albums as &$album) {
				$albumId = $album->getId();
				$album->setArtistIds($albumArtists[$albumId]);
				if (array_key_exists($albumId, $years)) {
					$album->setYears($years[$albumId]);
				}
			}
		}
		return $albums;
	}

	/**
	 * Returns the count of albums an Artist is featured in
	 * @param integer $artistId
	 * @return integer
	 */
	public function countByArtist($artistId){
		return $this->mapper->countByArtist($artistId);
	}

	public function findAlbumOwner($albumId){
		$entities = $this->mapper->findById([$albumId]);
		if (count($entities) != 1) {
			throw new \OCA\Music\AppFramework\BusinessLayer\BusinessLayerException(
					'Expected to find one album but got ' . count($entities));
		}
		else {
			return $entities[0]->getUserId();
		}
	}

	/**
	 * Adds an album if it does not exist already or updates an existing album
	 * @param string $name the name of the album
	 * @param string $discnumber the disk number of this album's disk
	 * @param integer $albumArtistId
	 * @param string $userId
	 * @return Album The added/updated album
	 */
	public function addOrUpdateAlbum($name, $discnumber, $albumArtistId, $userId){
		$album = new Album();
		$album->setName($name);
		$album->setDisk($discnumber);
		$album->setUserId($userId);
		$album->setAlbumArtistId($albumArtistId);

		// Generate hash from the set of fields forming the album identity to prevent duplicates.
		// The uniqueness of album name is evaluated in case-insensitive manner.
		$lowerName = mb_strtolower($name);
		$hash = hash('md5', "$lowerName|$discnumber|$albumArtistId");
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
		$albums = $this->mapper->findById([$albumId]);
		return (count($albums) && in_array($albums[0]->getCoverFileId(), $fileIds));
	}

	/**
	 * updates the cover for albums in the specified folder without cover
	 * @param integer $coverFileId the file id of the cover image
	 * @param integer $folderId the file id of the folder where the albums are looked from
	 * @return true if one or more albums were influenced
	 */
	public function updateFolderCover($coverFileId, $folderId){
		return $this->mapper->updateFolderCover($coverFileId, $folderId);
	}

	/**
	 * set cover file for a specified album
	 * @param integer $coverFileId the file id of the cover image
	 * @param integer $albumId the id of the album to be modified
	 */
	public function setCover($coverFileId, $albumId){
		$this->mapper->setCover($coverFileId, $albumId);
	}

	/**
	 * removes the cover art from albums, replacement covers will be searched in a background task
	 * @param integer[] $coverFileIds the file IDs of the cover images
	 * @param string[]|null $userIds the users whose music library is targeted; all users are targeted if omitted
	 * @return Album[] albums which got modified, empty array if none
	 */
	public function removeCovers($coverFileIds, $userIds=null){
		return $this->mapper->removeCovers($coverFileIds, $userIds);
	}

	/**
	 * try to find cover arts for albums without covers
	 * @return array of users whose collections got modified
	 */
	public function findCovers(){
		$affectedUsers = [];
		$albums = $this->mapper->getAlbumsWithoutCover();
		foreach ($albums as $album){
			if ($this->mapper->findAlbumCover($album['albumId'], $album['parentFolderId'])){
				$affectedUsers[$album['userId']] = 1;
			}
		}
		return array_keys($affectedUsers);
	}
}
