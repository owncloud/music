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


class AlbumBusinessLayer extends BusinessLayer {

	public function __construct(AlbumMapper $albumMapper){
		parent::__construct($albumMapper);
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
	 * Returns all albums filtered by artist
	 * @param string $artistId the id of the artist
	 * @return array of albums
	 */
	public function findAllByArtist($artistId, $userId){
		$albums = $this->mapper->findAllByArtist($artistId, $userId);
		return $this->injectArtists($albums);
	}

	private function injectArtists($albums){
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
}
