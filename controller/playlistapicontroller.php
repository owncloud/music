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

namespace OCA\Music\Controller;

use \OCP\AppFramework\Controller;
use \OCP\AppFramework\Http;
use \OCP\AppFramework\Http\JSONResponse;
use \OCP\AppFramework\Db\DoesNotExistException;

use \OCP\IRequest;
use \OCP\IURLGenerator;
use \OCP\Files\Folder;

use \OCA\Music\BusinessLayer\AlbumBusinessLayer;
use \OCA\Music\BusinessLayer\ArtistBusinessLayer;
use \OCA\Music\BusinessLayer\PlaylistBusinessLayer;
use \OCA\Music\BusinessLayer\TrackBusinessLayer;
use \OCA\Music\Db\Playlist;
use \OCA\Music\Utility\APISerializer;

class PlaylistApiController extends Controller {

	private $playlistBusinessLayer;
	private $userId;
	private $userFolder;
	private $artistBusinessLayer;
	private $albumBusinessLayer;
	private $trackBusinessLayer;
	private $urlGenerator;

	public function __construct($appname,
								IRequest $request,
								IURLGenerator $urlGenerator,
								PlaylistBusinessLayer $playlistBusinessLayer,
								ArtistBusinessLayer $artistBusinessLayer,
								AlbumBusinessLayer $albumBusinessLayer,
								TrackBusinessLayer $trackBusinessLayer,
								Folder $userFolder,
								$userId){
		parent::__construct($appname, $request);
		$this->userId = $userId;
		$this->userFolder = $userFolder;
		$this->urlGenerator = $urlGenerator;
		$this->playlistBusinessLayer = $playlistBusinessLayer;
		$this->artistBusinessLayer = $artistBusinessLayer;
		$this->albumBusinessLayer = $albumBusinessLayer;
		$this->trackBusinessLayer = $trackBusinessLayer;
	}

	/**
	 * lists all playlists
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function getAll() {
		$playlists = $this->playlistBusinessLayer->findAll($this->userId);
		$serializer = new APISerializer();

		return $serializer->serialize($playlists);
	}

	/**
	 * creates a playlist
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function create() {
		$playlist = $this->playlistBusinessLayer->insert($this->params('name'), $this->userId);

		// add trackIds to the newly created playlist if provided
		if (!empty($this->params('trackIds'))){
			$playlist = $this->playlistBusinessLayer->addTracks(
					$this->getParamTrackIds(), $playlist->getId(), $this->userId);
		}
		
		return $playlist->toAPI();
	}

	/**
	 * deletes a playlist
	 * @param  int $id playlist ID
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function delete($id) {
		$this->playlistBusinessLayer->delete($id, $this->userId);
		return array();
	}

	/**
	 * lists a single playlist
	 * @param  int $id playlist ID
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function get($id) {
		try {
			$playlist = $this->playlistBusinessLayer->find($id, $this->userId);

			$fulltree = filter_var($this->params('fulltree'), FILTER_VALIDATE_BOOLEAN);
			if ($fulltree) {
				return $this->toFullTree($playlist);
			} else {
				return $playlist->toAPI();
			}

		} catch(DoesNotExistException $ex) {
			return new JSONResponse(array('message' => $ex->getMessage()),
				Http::STATUS_NOT_FOUND);
		}
	}

	private function toFullTree($playlist) {
		$songs = [];

		// Get all track information for all the tracks of the playlist
		foreach($playlist->getTrackIds() as $trackId) {
			$song = $this->trackBusinessLayer->find($trackId, $this->userId);
			$song->setAlbum($this->albumBusinessLayer->find($song->getAlbumId(), $this->userId));
			$song->setArtist($this->artistBusinessLayer->find($song->getArtistId(), $this->userId));
			$songs[] = $song->toCollection($this->urlGenerator, $this->userFolder);
		}

		return array(
			'name' => $playlist->getName(),
			'tracks' => $songs,
			'id' => $playlist->getId(),
		);
	}

	/**
	 * update a playlist
	 * @param  int $id playlist ID
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function update($id) {
		try {
			$playlist = $this->playlistBusinessLayer->rename($this->params('name'), $id, $this->userId);
			return $playlist->toAPI();
		} catch(DoesNotExistException $ex) {
			return new JSONResponse(array('message' => $ex->getMessage()),
				Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * add tracks to a playlist
	 * @param  int $id playlist ID
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function addTracks($id) {
		try {
			$playlist = $this->playlistBusinessLayer->addTracks($this->getParamTrackIds(), $id, $this->userId);
			return $playlist->toAPI();
		} catch(DoesNotExistException $ex) {
			return new JSONResponse(array('message' => $ex->getMessage()),
				Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * removes tracks from a playlist
	 * @param  int $id playlist ID
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function removeTracks($id) {
		try {
			$playlist = $this->playlistBusinessLayer->removeTracks($this->getParamTrackIds(), $id, $this->userId);
			return $playlist->toAPI();
		} catch(DoesNotExistException $ex) {
			return new JSONResponse(array('message' => $ex->getMessage()),
				Http::STATUS_NOT_FOUND);
		}
	}

	private function getParamTrackIds() {
		$trackIds = array();
		foreach (explode(',', $this->params('trackIds')) as $trackId) {
			$trackIds[] = (int) $trackId;
		}
		return $trackIds;
	}
}
