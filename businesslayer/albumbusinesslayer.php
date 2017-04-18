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
use \OCP\AppFramework\Db\DoesNotExistException;
use \OCP\AppFramework\Db\MultipleObjectsReturnedException;


use \OCA\Music\Db\AlbumMapper;
use \OCA\Music\Db\Album;

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
		$albumArtists = $this->mapper->getAlbumArtistsByAlbumId(array($album->getId()));
		$album->setArtistIds($albumArtists[$album->getId()]);
		return $album;
	}

	/**
	 * Returns all albums
	 * @param string $userId the name of the user
	 * @return Album[] albums
	 */
	public function findAll($userId){
		$albums = $this->mapper->findAll($userId);
		return $this->injectArtists($albums);
	}

	/**
	 * Returns all albums filtered by artist
	 * @param string $artistId the id of the artist
	 * @return Album[] albums
	 */
	public function findAllByArtist($artistId, $userId){
		$albums = $this->mapper->findAllByArtist($artistId, $userId);
		return $this->injectArtists($albums);
	}

	private function injectArtists($albums){
		if(count($albums) === 0) {
			return array();
		}
		$albumIds = array();
		foreach ($albums as $album) {
			$albumIds[] = $album->getId();
		}
		$albumArtists = $this->mapper->getAlbumArtistsByAlbumId($albumIds);
		foreach ($albums as $key => $album) {
			$albums[$key]->setArtistIds($albumArtists[$album->getId()]);
		}
		return $albums;
	}

	/**
	 * Adds an album (if it does not exist already) and returns the new album
	 * @param string $name the name of the album
	 * @param string $year the year of the release
	 * @param string $discnumber the disk number of this album's disk
	 * @param integer $albumArtistId
	 * @param string $userId
	 * @return Album
	 * @throws BusinessLayerException
	 */
	public function addAlbumIfNotExist($name, $year, $discnumber, $albumArtistId, $userId){
		try {
			$album = $this->mapper->findAlbum($name, $year, $discnumber, $albumArtistId, $userId);
			$this->logger->log('addAlbumIfNotExist - exists - ID: ' . $album->getId(), 'debug');
		} catch(DoesNotExistException $ex){
			$album = new Album();
			$album->setName($name);
			$album->setYear($year);
			$album->setDisk($discnumber);
			$album->setUserId($userId);
			$album->setAlbumArtistId($albumArtistId);
			$album = $this->mapper->insert($album);
			$this->logger->log('addAlbumIfNotExist - added - ID: ' . $album->getId(), 'debug');
		} catch(MultipleObjectsReturnedException $ex){
			throw new BusinessLayerException($ex->getMessage());
		}
		return $album;
	}

	/**
	 * Deletes albums
	 * @param array $albumIds the ids of the albums which should be deleted
	 */
	public function deleteById($albumIds){
		$this->mapper->deleteById($albumIds);
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
	 * @param integer $coverFileId the file id of the cover image
	 * @return true if the given file was cover for some album
	 */
	public function removeCover($coverFileId){
		return $this->mapper->removeCover($coverFileId);
	}

	/**
	 * try to find cover arts for albums without covers
	 */
	public function findCovers(){
		$albums = $this->mapper->getAlbumsWithoutCover();
		foreach ($albums as $album) {
			$this->mapper->findAlbumCover($album['albumId'], $album['parentFolderId']);
		}
	}
}
