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
use \OCP\IRequest;

use \OCA\Music\AppFramework\Db\DoesNotExistException;

use \OCA\Music\Db\Playlist;
use \OCA\Music\Db\PlaylistMapper;
use \OCA\Music\Utility\APISerializer;

class PlaylistApiController extends Controller {

	private $playlistMapper;
	private $userId;

	public function __construct($appname,
								IRequest $request,
								PlaylistMapper $playlistMapper,
								$userId){
		parent::__construct($appname, $request);
		$this->userId = $userId;
		$this->playlistMapper = $playlistMapper;

	}

	/**
	 * lists all playlists
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function getAll() {
		$playlists = $this->playlistMapper->findAll($this->userId);
		$serializer = new APISerializer();

		return  $serializer->serialize($playlists);
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
		$this->playlistMapper->delete($id);

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

			// set trackIds in model
			$tracks = $this->playlistMapper->getTracks($id);
			$playlist->setTrackIds($tracks);

			return $playlist->toAPI();
		} catch(DoesNotExistException $ex) {
			return new JSONResponse(array('message' => $ex->getMessage()),
				Http::STATUS_NOT_FOUND);
		}
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

			$oldTrackIds = $playlist->getTrackIds();
			$trackIds = array_merge($oldTrackIds, $newTrackIds);
			$playlist->setTrackIds($trackIds);

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
			$this->playlistMapper->removeTracks($id, $trackIds);
		} catch(DoesNotExistException $ex) {
			return new JSONResponse(array('message' => $ex->getMessage()),
				Http::STATUS_NOT_FOUND);
		}
	}
}
