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


namespace OCA\Music\Controller;

use \OCA\Music\AppFramework\Core\API;
use \OCA\Music\AppFramework\Http\Request;
use \OCA\Music\BusinessLayer\TrackBusinessLayer;
use \OCA\Music\BusinessLayer\ArtistBusinessLayer;
use \OCA\Music\BusinessLayer\AlbumBusinessLayer;


class ApiController extends Controller {

	private $trackBusinessLayer;
	private $artistBusinessLayer;
	private $albumBusinessLayer;

	public function __construct(API $api, Request $request,
		TrackBusinessLayer $trackbusinesslayer, ArtistBusinessLayer $artistbusinesslayer,
		AlbumBusinessLayer $albumbusinesslayer){
		parent::__construct($api, $request);
		$this->trackBusinessLayer = $trackbusinesslayer;
		$this->artistBusinessLayer = $artistbusinesslayer;
		$this->albumBusinessLayer = $albumbusinesslayer;
	}

	/**
	 * @CSRFExemption
	 * @IsAdminExemption
	 * @IsSubAdminExemption
	 * @Ajax
	 * @API
	 */
	public function collection() {
		$userId = $this->api->getUserId();
		$path = $this->api->getUserValue('path');
		if (!$path) $path = "/";
		$path = 'files'.$path;

		$allArtists = $this->artistBusinessLayer->findAll($userId);
		$allArtistsById = array();
		foreach ($allArtists as &$artist) $allArtistsById[$artist->id] = $artist->toCollection($this->api);

		$allAlbums = $this->albumBusinessLayer->findAllWithFileInfo($userId);
		$allAlbumsById = array();
		foreach ($allAlbums as &$album) $allAlbumsById[$album->id] = $album->toCollection($this->api);

		$allTracks = $this->trackBusinessLayer->findAllByPath($path, $userId);

		$artists = array();
		foreach ($allTracks as $track) {
			$artist = &$allArtistsById[$track->artistId];
			if (!isset($artist['albums'])) {
				$artist['albums'] = array();
				$artists[] = &$artist;
			}
			$album = &$allAlbumsById[$track->albumId];
			if (!isset($album['tracks'])) {
				$album['tracks'] = array();
				$artist['albums'][] = &$album;
			}

			$album['tracks'][] = $track->toCollection($this->api);
		}

		return $this->renderPlainJSON($artists);
	}

	/**
	 * @CSRFExemption
	 * @IsAdminExemption
	 * @IsSubAdminExemption
	 * @Ajax
	 * @API
	 */
	public function artists() {
		$fulltree = filter_var($this->params('fulltree'), FILTER_VALIDATE_BOOLEAN);
		$includeAlbums = filter_var($this->params('albums'), FILTER_VALIDATE_BOOLEAN);
		$userId = $this->api->getUserId();
		$artists = $this->artistBusinessLayer->findAll($userId);
		foreach($artists as &$artist) {
			$artist = $artist->toAPI($this->api);
			if($fulltree || $includeAlbums) {
				$artistId = $artist['id'];
				$albums = $this->albumBusinessLayer->findAllByArtist($artistId, $userId);
				foreach($albums as &$album) {
					$album = $album->toAPI($this->api);
					if($fulltree) {
						$albumId = $album['id'];
						$tracks = $this->trackBusinessLayer->findAllByAlbum($albumId, $userId, $artistId);
						foreach($tracks as &$track) {
							$track = $track->toAPI($this->api);
						}
						$album['tracks'] = $tracks;
					}
				}
				$artist['albums'] = $albums;
			}
		}
		return $this->renderPlainJSON($artists);
	}

	/**
	 * @CSRFExemption
	 * @IsAdminExemption
	 * @IsSubAdminExemption
	 * @Ajax
	 * @API
	 */
	public function artist() {
		$fulltree = filter_var($this->params('fulltree'), FILTER_VALIDATE_BOOLEAN);
		$userId = $this->api->getUserId();
		$artistId = $this->getIdFromSlug($this->params('artistIdOrSlug'));
		$artist = $this->artistBusinessLayer->find($artistId, $userId);
		$artist = $artist->toAPI($this->api);
		if($fulltree) {
			$artistId = $artist['id'];
			$albums = $this->albumBusinessLayer->findAllByArtist($artistId, $userId);
			foreach($albums as &$album) {
				$album = $album->toAPI($this->api);
				$albumId = $album['id'];
				$tracks = $this->trackBusinessLayer->findAllByAlbum($albumId, $userId, $artistId);
				foreach($tracks as &$track) {
					$track = $track->toAPI($this->api);
				}
				$album['tracks'] = $tracks;
			}
			$artist['albums'] = $albums;
		}
		return $this->renderPlainJSON($artist);
	}

	/**
	 * @CSRFExemption
	 * @IsAdminExemption
	 * @IsSubAdminExemption
	 * @Ajax
	 * @API
	 */
	public function albums() {
		$fulltree = filter_var($this->params('fulltree'), FILTER_VALIDATE_BOOLEAN);
		$userId = $this->api->getUserId();
		$albums = $this->albumBusinessLayer->findAll($userId);
		foreach($albums as &$album) {
			$artistIds = $album->getArtistIds();
			$album = $album->toAPI($this->api);
			if($fulltree) {
				$albumId = $album['id'];
				$tracks = $this->trackBusinessLayer->findAllByAlbum($albumId, $userId);
				foreach($tracks as &$track) {
					$track = $track->toAPI($this->api);
				}
				$album['tracks'] = $tracks;
				$artists = $this->artistBusinessLayer->findMultipleById($artistIds, $userId);
				foreach($artists as &$artist) {
					$artist = $artist->toAPI($this->api);
				}
				$album['artists'] = $artists;
			}
		}
		return $this->renderPlainJSON($albums);
	}

	/**
	 * @CSRFExemption
	 * @IsAdminExemption
	 * @IsSubAdminExemption
	 * @Ajax
	 * @API
	 */
	public function album() {
		$fulltree = filter_var($this->params('fulltree'), FILTER_VALIDATE_BOOLEAN);
		$userId = $this->api->getUserId();
		$albumId = $this->getIdFromSlug($this->params('albumIdOrSlug'));
		$album = $this->albumBusinessLayer->find($albumId, $userId);

		$artistIds = $album->getArtistIds();
		$album = $album->toAPI($this->api);
		if($fulltree) {
			$albumId = $album['id'];
			$tracks = $this->trackBusinessLayer->findAllByAlbum($albumId, $userId);
			foreach($tracks as &$track) {
				$track = $track->toAPI($this->api);
			}
			$album['tracks'] = $tracks;
			$artists = $this->artistBusinessLayer->findMultipleById($artistIds, $userId);
			foreach($artists as &$artist) {
				$artist = $artist->toAPI($this->api);
			}
			$album['artists'] = $artists;
		}

		return $this->renderPlainJSON($album);
	}

	/**
	 * @CSRFExemption
	 * @IsAdminExemption
	 * @IsSubAdminExemption
	 * @Ajax
	 * @API
	 */
	public function tracks() {
		$fulltree = filter_var($this->params('fulltree'), FILTER_VALIDATE_BOOLEAN);
		$userId = $this->api->getUserId();
		if($artistId = $this->params('artist')) {
			$tracks = $this->trackBusinessLayer->findAllByArtist($artistId, $userId);
		} elseif($albumId = $this->params('album')) {
			$tracks = $this->trackBusinessLayer->findAllByAlbum($albumId, $userId);
		} else {
			$tracks = $this->trackBusinessLayer->findAll($userId);
		}
		foreach($tracks as &$track) {
			$artistId = $track->getArtistId();
			$albumId = $track->getAlbumId();
			$track = $track->toAPI($this->api);
			if($fulltree) {
				$artist = $this->artistBusinessLayer->find($artistId, $userId);
				$track['artist'] = $artist->toAPI($this->api);
				$album = $this->albumBusinessLayer->find($albumId, $userId);
				$track['album'] = $album->toAPI($this->api);
			}
		}
		return $this->renderPlainJSON($tracks);
	}

	/**
	 * @CSRFExemption
	 * @IsAdminExemption
	 * @IsSubAdminExemption
	 * @Ajax
	 * @API
	 */
	public function track() {
		$userId = $this->api->getUserId();
		$trackId = $this->getIdFromSlug($this->params('trackIdOrSlug'));
		$track = $this->trackBusinessLayer->find($trackId, $userId);
		return $this->renderPlainJSON($track->toAPI($this->api));
	}

	/**
	 * @CSRFExemption
	 * @IsAdminExemption
	 * @IsSubAdminExemption
	 * @Ajax
	 * @API
	 */
	public function trackByFileId() {
		$fileId = $this->params('fileId');
		$userId = $this->api->getUserId();
		$track = $this->trackBusinessLayer->findByFileId($fileId, $userId);
		return $this->renderPlainJSON($track->toAPI($this->api));
	}
}
