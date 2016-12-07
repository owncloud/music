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
use \OCA\Music\BusinessLayer\TrackBusinessLayer;
use \OCA\Music\Db\Playlist;
use \OCA\Music\Db\PlaylistMapper;
use \OCA\Music\Utility\APISerializer;

class PlaylistApiController extends Controller {

	private $playlistMapper;
	private $userId;
	private $userFolder;
	private $artistBusinessLayer;
	private $albumBusinessLayer;
	private $trackBusinessLayer;
	private $urlGenerator;

	public function __construct($appname,
								IRequest $request,
								IURLGenerator $urlGenerator,
								PlaylistMapper $playlistMapper,
								ArtistBusinessLayer $artistBusinessLayer,
								AlbumBusinessLayer $albumBusinessLayer,
								TrackBusinessLayer $trackBusinessLayer,
								Folder $userFolder,
								$userId){
		parent::__construct($appname, $request);
		$this->userId = $userId;
		$this->userFolder = $userFolder;
		$this->urlGenerator = $urlGenerator;
		$this->playlistMapper = $playlistMapper;
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
		$playlists = $this->playlistMapper->findAll($this->userId);
		foreach ($playlists as $list) {
			$list->setTrackIds($this->playlistMapper->getTracks($list->getId()));
		}
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
		$name = $this->params('name');

		$playlist = new Playlist();
		$playlist->setName($name);
		$playlist->setUserId($this->userId);

		$playlist = $this->playlistMapper->insert($playlist);

		// if trackIds is provided just add them to the playlist
		$trackIds = $this->params('trackIds');
		if (!empty($trackIds)){
			$newTrackIds = array();
			foreach (explode(',', $trackIds) as $trackId) {
				$newTrackIds[] = (int) $trackId;
			}

			$this->playlistMapper->addTracks($newTrackIds, $playlist->getId());

			// set trackIds in model
			$tracks = $this->playlistMapper->getTracks($playlist->getId());
			$playlist->setTrackIds($tracks);

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
		$this->playlistMapper->deleteById([$id]);

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
			$playlist = $this->playlistMapper->find($id, $this->userId);
			$playlist->setTrackIds($this->playlistMapper->getTracks($id));

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
		$name = $this->params('name');

		try {
			$playlist = $this->playlistMapper->find($id, $this->userId);
			$playlist->setName($name);
			$this->playlistMapper->update($playlist);
			$playlist->setTrackIds($this->playlistMapper->getTracks($id));

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
		$newTrackIds = array();
		foreach (explode(',', $this->params('trackIds')) as $trackId) {
			$newTrackIds[] = (int) $trackId;
		}

		try {
			$playlist = $this->playlistMapper->find($id, $this->userId);
			$this->playlistMapper->addTracks($newTrackIds, $id);
			$playlist->setTrackIds($this->playlistMapper->getTracks($id));

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
		$trackIds = array();
		foreach (explode(',', $this->params('trackIds')) as $trackId) {
			$trackIds[] = (int) $trackId;
		}

		try {
			$playlist = $this->playlistMapper->find($id, $this->userId);
			$this->playlistMapper->removeTracks($id, $trackIds);
			$playlist->setTrackIds($this->playlistMapper->getTracks($id));

			return $playlist->toAPI();
		} catch(DoesNotExistException $ex) {
			return new JSONResponse(array('message' => $ex->getMessage()),
				Http::STATUS_NOT_FOUND);
		}
	}
}
