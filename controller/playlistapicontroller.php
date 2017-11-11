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
 * @copyright Pauli Järvinen 2017
 */

namespace OCA\Music\Controller;

use \OCP\AppFramework\Controller;
use \OCP\AppFramework\Http;
use \OCP\AppFramework\Http\JSONResponse;

use \OCP\IRequest;
use \OCP\IURLGenerator;
use \OCP\Files\Folder;

use \OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use \OCA\Music\BusinessLayer\AlbumBusinessLayer;
use \OCA\Music\BusinessLayer\ArtistBusinessLayer;
use \OCA\Music\BusinessLayer\PlaylistBusinessLayer;
use \OCA\Music\BusinessLayer\TrackBusinessLayer;
use \OCA\Music\Db\Playlist;
use \OCA\Music\Http\ErrorResponse;
use \OCA\Music\Utility\APISerializer;

class PlaylistApiController extends Controller {

	private $playlistBusinessLayer;
	private $userId;
	private $userFolder;
	private $artistBusinessLayer;
	private $albumBusinessLayer;
	private $trackBusinessLayer;
	private $urlGenerator;
	private $l10n;

	public function __construct($appname,
								IRequest $request,
								IURLGenerator $urlGenerator,
								PlaylistBusinessLayer $playlistBusinessLayer,
								ArtistBusinessLayer $artistBusinessLayer,
								AlbumBusinessLayer $albumBusinessLayer,
								TrackBusinessLayer $trackBusinessLayer,
								Folder $userFolder,
								$userId,
								$l10n){
		parent::__construct($appname, $request);
		$this->userId = $userId;
		$this->userFolder = $userFolder;
		$this->urlGenerator = $urlGenerator;
		$this->playlistBusinessLayer = $playlistBusinessLayer;
		$this->artistBusinessLayer = $artistBusinessLayer;
		$this->albumBusinessLayer = $albumBusinessLayer;
		$this->trackBusinessLayer = $trackBusinessLayer;
		$this->l10n = $l10n;
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
	public function create($name, $trackIds) {
		$playlist = $this->playlistBusinessLayer->create($name, $this->userId);

		// add trackIds to the newly created playlist if provided
		if (!empty($trackIds)) {
			$playlist = $this->playlistBusinessLayer->addTracks(
					self::toIntArray($trackIds), $playlist->getId(), $this->userId);
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
	public function get($id, $fulltree) {
		try {
			$playlist = $this->playlistBusinessLayer->find($id, $this->userId);

			$fulltree = filter_var($fulltree, FILTER_VALIDATE_BOOLEAN);
			if ($fulltree) {
				return $this->toFullTree($playlist);
			} else {
				return $playlist->toAPI();
			}

		} catch(BusinessLayerException $ex) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, $ex->getMessage());
		}
	}

	private function toFullTree($playlist) {
		$songs = [];

		// Get all track information for all the tracks of the playlist
		foreach($playlist->getTrackIdsAsArray() as $trackId) {
			$song = $this->trackBusinessLayer->find($trackId, $this->userId);
			$song->setAlbum($this->albumBusinessLayer->find($song->getAlbumId(), $this->userId));
			$song->setArtist($this->artistBusinessLayer->find($song->getArtistId(), $this->userId));
			$songs[] = $song->toCollection($this->urlGenerator, $this->userFolder, $this->l10n);
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
	public function update($id, $name) {
		return $this->modifyPlaylist('rename', [$name, $id, $this->userId]);
	}

	/**
	 * add tracks to a playlist
	 * @param  int $id playlist ID
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function addTracks($id, $trackIds) {
		return $this->modifyPlaylist('addTracks', [self::toIntArray($trackIds), $id, $this->userId]);
	}

	/**
	 * removes tracks from a playlist
	 * @param  int $id playlist ID
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function removeTracks($id, $indices) {
		return $this->modifyPlaylist('removeTracks', [self::toIntArray($indices), $id, $this->userId]);
	}

	/**
	 * moves single track on playlist to a new position
	 * @param  int $id playlist ID
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function reorder($id, $fromIndex, $toIndex) {
		return $this->modifyPlaylist('moveTrack',
				[$fromIndex, $toIndex, $id, $this->userId]);
	}

	/**
	 * Modify playlist by calling a supplied method from PlaylistBusinessLayer
	 * @param string $funcName  Name of a function to call from PlaylistBusinessLayer
	 * @param array $funcParams Parameters to pass to the function 'funcName'
	 * @return \OCP\AppFramework\Http\JSONResponse JSON representation of the modified playlist
	 */
	private function modifyPlaylist($funcName, $funcParams) {
		try {
			$playlist = call_user_func_array([$this->playlistBusinessLayer, $funcName], $funcParams);
			return $playlist->toAPI();
		} catch(BusinessLayerException $ex) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, $ex->getMessage());
		}
	}

	/**
	 * Get integer array passed as parameter to the Playlist API
	 * @param string $listAsString Comma-separated integer values in string
	 * @return int[]
	 */
	private static function toIntArray($listAsString) {
		return array_map('intval', explode(',', $listAsString));
	}
}
