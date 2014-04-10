<?php

/**
 * ownCloud - Music app
 *
 * @author Morris Jobke
 * @copyright 2013 Morris Jobke <morris.jobke@gmail.com>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Music\BusinessLayer;

use \OCA\Music\Db\AlbumMapper;
use \OCA\Music\Db\Album;

use \OCA\Music\AppFramework\Core\API;
use \OCA\Music\AppFramework\Db\DoesNotExistException;
use \OCA\Music\AppFramework\Db\MultipleObjectsReturnedException;


class AlbumBusinessLayer extends BusinessLayer {

	public function __construct(AlbumMapper $albumMapper, API $api){
		parent::__construct($albumMapper, $api);
	}

	/**
	 * Return an album
	 * @param string $albumId the id of the album
	 * @param string $userId the name of the user
	 * @return album
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
	 * @return array of albums
	 */
	public function findAll($userId){
		$albums = $this->mapper->findAll($userId);
		return $this->injectArtists($albums);
	}

	/**
	 * Returns all albums
	 * @param string $userId the name of the user
	 * @return array of albums
	 */
	public function findAllWithFileInfo($userId){
		$albums = $this->mapper->findAllWithFileInfo($userId);
		return $albums;
	}

	/**
	 * Returns all albums filtered by artist
	 * @param string $artistId the id of the artist
	 * @return array of albums
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
	 * @return \OCA\Music\Db\Album
	 * @throws \OCA\Music\BusinessLayer\BusinessLayerException
	 */
	public function addAlbumIfNotExist($name, $year, $artistId, $userId){
		try {
			$album = $this->mapper->findAlbum($name, $year, $artistId, $userId);
			$this->api->log('addAlbumIfNotExist - exists - ID: ' . $album->getId(), 'debug');
		} catch(DoesNotExistException $ex){
			$album = new Album();
			$album->setName($name);
			$album->setYear($year);
			$album->setUserId($userId);
			$album = $this->mapper->insert($album);
			$this->api->log('addAlbumIfNotExist - added - ID: ' . $album->getId(), 'debug');
		} catch(MultipleObjectsReturnedException $ex){
			throw new BusinessLayerException($ex->getMessage());
		}
		$this->mapper->addAlbumArtistRelationIfNotExist($album->getId(), $artistId);
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
	 * updates the cover for albums without cover
	 * @param integer $coverFileId the file id of the cover image
	 * @param integer $parentFolderId the file id of the parent of this image
	 */
	public function updateCover($coverFileId, $parentFolderId){
		$this->mapper->updateCover($coverFileId, $parentFolderId);
	}

	/**
	 * removes the cover from albums
	 * @param integer $coverFileId the file id of the cover image
	 */
	public function removeCover($coverFileId){
		$this->mapper->removeCover($coverFileId);
		// find new cover
		$this->findCovers();
	}

	/**
	 * try to find covers from albums without covers
	 */
	public function findCovers(){
		$albums = $this->mapper->getAlbumsWithoutCover();
		foreach ($albums as $album) {
			$this->mapper->findAlbumCover($album['albumId'], $album['parentFolderId']);
		}
	}
}
