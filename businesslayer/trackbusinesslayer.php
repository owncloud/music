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

use \OCA\Music\Db\TrackMapper;
use \OCA\Music\Db\Track;

use \OCA\Music\AppFramework\Core\API;
use \OCA\Music\AppFramework\Db\DoesNotExistException;
use \OCA\Music\AppFramework\Db\MultipleObjectsReturnedException;


class TrackBusinessLayer extends BusinessLayer {

	public function __construct(TrackMapper $trackMapper, API $api){
		parent::__construct($trackMapper, $api);
	}

	/**
	 * Returns all tracks filtered by path
	 * @param string $path the path used as filter
	 * @param string $userId the name of the user
	 * @return array of tracks
	 */
	public function findAllByPath($path, $userId){
		return $this->mapper->findAllByPath($path, $userId);
	}

	/**
	 * Returns all tracks filtered by artist
	 * @param string $artistId the id of the artist
	 * @param string $userId the name of the user
	 * @return array of tracks
	 */
	public function findAllByArtist($artistId, $userId){
		return $this->mapper->findAllByArtist($artistId, $userId);
	}

	/**
	 * Returns all tracks filtered by album
	 * @param string $albumId the id of the track
	 * @param string $userId the name of the user
	 * @return array of tracks
	 */
	public function findAllByAlbum($albumId, $userId, $artistId = null){
		return $this->mapper->findAllByAlbum($albumId, $userId, $artistId);
	}

	/**
	 * Returns the track for a file id
	 * @param string $fileId the file id of the track
	 * @param string $userId the name of the user
	 * @return track
	 */
	public function findByFileId($fileId, $userId){
		return $this->mapper->findByFileId($fileId, $userId);
	}

	/**
	 * Adds a track (if it does not exist already) and returns the new track
	 * @param string $name the name of the track
	 * @param string $number the number of the track
	 * @param string $artistId the artist id of the track
	 * @param string $albumId the album id of the track
	 * @param string $fileId the file id of the track
	 * @param string $mimetype the mimetype of the track
	 * @param string $userId the name of the user
	 * @return \OCA\Music\Db\Track
	 * @throws \OCA\Music\BusinessLayer\BusinessLayerException
	 */
	public function addTrackIfNotExist($title, $number, $artistId, $albumId, $fileId, $mimetype, $userId){
		try {
			$track = $this->mapper->findByFileId($fileId, $userId);
			$track->setTitle($title);
			$track->setNumber($number);
			$track->setArtistId($artistId);
			$track->setAlbumId($albumId);
			$track->setMimetype($mimetype);
			$track->setUserId($userId);
			$this->mapper->update($track);
			$this->api->log('addTrackIfNotExist - exists & updated - ID: ' . $track->getId(), 'debug');
		} catch(DoesNotExistException $ex){
			$track = new Track();
			$track->setTitle($title);
			$track->setNumber($number);
			$track->setArtistId($artistId);
			$track->setAlbumId($albumId);
			$track->setFileId($fileId);
			$track->setMimetype($mimetype);
			$track->setUserId($userId);
			$track = $this->mapper->insert($track);
			$this->api->log('addTrackIfNotExist - added - ID: ' . $track->getId(), 'debug');
		} catch(MultipleObjectsReturnedException $ex){
			throw new BusinessLayerException($ex->getMessage());
		}
		return $track;
	}

	/**
	 * Deletes a track
	 * @param string $fileId the file id of the track
	 * @param string $userId the name of the user
	 * @return array of two arrays (named 'albumIds', 'artistIds') containing all album ids
	 *		   and artist ids of the deleted track(s)
	 */
	public function deleteTrack($fileId, $userId){
		$tracks = $this->mapper->findAllByFileId($fileId);

		$remaining = array(
			'albumIds' => array(),
			'artistIds' => array()
		);

		foreach($tracks as $track) {
			$artistId = $track->getArtistId();
			$albumId = $track->getAlbumId();
			$this->mapper->delete($track);
			if(!in_array($artistId, $remaining['artistIds'])){
				// only add artists which have no tracks left
				$result = $this->mapper->countByArtist($artistId, $userId);
				if($result === '0') {
					$remaining['artistIds'][] = $artistId;
				}
			}
			if(!in_array($albumId, $remaining['albumIds'])){
				// only add albums which have no tracks left
				$result = $this->mapper->countByAlbum($albumId, $userId);
				if($result === '0') {
					$remaining['albumIds'][] = $albumId;
				}

			}
		}

		return $remaining;
	}

	/**
	 * Returns all tracks filtered by name (of track/album/artist)
	 * @param string $name the name of the track/album/artist
	 * @param string $userId the name of the user
	 * @return array of tracks
	 */
	public function findAllByNameRecursive($name, $userId){
		return $this->mapper->findAllByNameRecursive($name, $userId);
	}
}
