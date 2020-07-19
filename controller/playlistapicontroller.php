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
 * @copyright Pauli Järvinen 2017 - 2020
 */

namespace OCA\Music\Controller;

use \OCP\AppFramework\Controller;
use \OCP\AppFramework\Http;
use \OCP\AppFramework\Http\JSONResponse;

use \OCP\Files\Folder;
use \OCP\IRequest;
use \OCP\IURLGenerator;

use \OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use \OCA\Music\AppFramework\Core\Logger;
use \OCA\Music\BusinessLayer\AlbumBusinessLayer;
use \OCA\Music\BusinessLayer\ArtistBusinessLayer;
use \OCA\Music\BusinessLayer\PlaylistBusinessLayer;
use \OCA\Music\BusinessLayer\TrackBusinessLayer;
use \OCA\Music\Db\Playlist;
use \OCA\Music\Http\ErrorResponse;
use \OCA\Music\Utility\APISerializer;
use \OCA\Music\Utility\PlaylistFileService;
use \OCA\Music\Utility\Util;

class PlaylistApiController extends Controller {
	private $urlGenerator;
	private $playlistBusinessLayer;
	private $artistBusinessLayer;
	private $albumBusinessLayer;
	private $trackBusinessLayer;
	private $playlistFileService;
	private $userId;
	private $userFolder;
	private $l10n;
	private $logger;

	public function __construct($appname,
								IRequest $request,
								IURLGenerator $urlGenerator,
								PlaylistBusinessLayer $playlistBusinessLayer,
								ArtistBusinessLayer $artistBusinessLayer,
								AlbumBusinessLayer $albumBusinessLayer,
								TrackBusinessLayer $trackBusinessLayer,
								PlaylistFileService $playlistFileService,
								$userId,
								Folder $userFolder,
								$l10n,
								Logger $logger) {
		parent::__construct($appname, $request);
		$this->urlGenerator = $urlGenerator;
		$this->playlistBusinessLayer = $playlistBusinessLayer;
		$this->artistBusinessLayer = $artistBusinessLayer;
		$this->albumBusinessLayer = $albumBusinessLayer;
		$this->trackBusinessLayer = $trackBusinessLayer;
		$this->playlistFileService = $playlistFileService;
		$this->userId = $userId;
		$this->userFolder = $userFolder;
		$this->l10n = $l10n;
		$this->logger = $logger;
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
		return [];
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

			$fulltree = \filter_var($fulltree, FILTER_VALIDATE_BOOLEAN);
			if ($fulltree) {
				return $this->toFullTree($playlist);
			} else {
				return $playlist->toAPI();
			}
		} catch (BusinessLayerException $ex) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, $ex->getMessage());
		}
	}

	private function toFullTree($playlist) {
		$songs = [];

		// Get all track information for all the tracks of the playlist
		foreach ($playlist->getTrackIdsAsArray() as $trackId) {
			$song = $this->trackBusinessLayer->find($trackId, $this->userId);
			$song->setAlbum($this->albumBusinessLayer->find($song->getAlbumId(), $this->userId));
			$songs[] = $song->toAPI($this->urlGenerator);
		}

		$result = $playlist->toAPI();
		unset($result['trackIds']);
		$result['tracks'] = $songs;

		return $result;
	}

	/**
	 * update a playlist
	 * @param int $id playlist ID
	 * @param string|null $name
	 * @param string|null $comment
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function update($id, $name, $comment) {
		$result = null;
		if ($name !== null) {
			$result = $this->modifyPlaylist('rename', [$name, $id, $this->userId]); 
		}
		if ($comment !== null) {
			$result = $this->modifyPlaylist('setComment', [$comment, $id, $this->userId]);
		}
		if ($result === null) {
			$result = new ErrorResponse(Http::STATUS_BAD_REQUEST, "at least one of the args ['name', 'comment'] must be given");
		}
		return $result;
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
	 * export the playlist to a file
	 * @param int $id playlist ID
	 * @param string $path parent folder path
	 * @param string $oncollision action to take on file name collision,
	 *								supported values:
	 *								- 'overwrite' The existing file will be overwritten
	 *								- 'keepboth' The new file is named with a suffix to make it unique
	 *								- 'abort' (default) The operation will fail
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function exportToFile($id, $path, $oncollision) {
		try {
			$exportedFilePath = $this->playlistFileService->exportToFile($id, $path, $oncollision);
			return new JSONResponse(['wrote_to_file' => $exportedFilePath]);
		}
		catch (BusinessLayerException $ex) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, 'playlist not found');
		}
		catch (\OCP\Files\NotFoundException $ex) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, 'folder not found');
		}
		catch (\RuntimeException $ex) {
			return new ErrorResponse(Http::STATUS_CONFLICT, $ex->getMessage());
		}
		catch (\OCP\Files\NotPermittedException $ex) {
			return new ErrorResponse(Http::STATUS_FORBIDDEN, 'user is not allowed to write to the target file');
		}
	}

	/**
	 * import playlist contents from a file
	 * @param int $id playlist ID
	 * @param string $filePath path of the file to import
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function importFromFile($id, $filePath) {
		try {
			$result = $this->playlistFileService->importFromFile($id, $filePath);
			$result['playlist'] = $result['playlist']->toAPI();
			return $result;
		}
		catch (BusinessLayerException $ex) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, 'playlist not found');
		}
		catch (\OCP\Files\NotFoundException $ex) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, 'playlist file not found');
		}
	}

	/**
	 * read and parse a playlist file
	 * @param int $fileId ID of the file to parse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function parseFile($fileId) {
		try {
			$result = $this->playlistFileService->parseFile($fileId);
			$result['files'] = \array_map(function($file) {
				return [
					'id' => $file->getId(),
					'name' => $file->getName(),
					'path' => Util::urlEncodePath($this->userFolder->getRelativePath($file->getPath())),
					'mimetype' => $file->getMimeType()
				];
			}, $result['files']);
			return new JSONResponse($result);
		}
		catch (\OCP\Files\NotFoundException $ex) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, 'playlist file not found');
		}
	}

	/**
	 * Modify playlist by calling a supplied method from PlaylistBusinessLayer
	 * @param string $funcName  Name of a function to call from PlaylistBusinessLayer
	 * @param array $funcParams Parameters to pass to the function 'funcName'
	 * @return \OCP\AppFramework\Http\JSONResponse JSON representation of the modified playlist
	 */
	private function modifyPlaylist($funcName, $funcParams) {
		try {
			$playlist = \call_user_func_array([$this->playlistBusinessLayer, $funcName], $funcParams);
			return $playlist->toAPI();
		} catch (BusinessLayerException $ex) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, $ex->getMessage());
		}
	}

	/**
	 * Get integer array passed as parameter to the Playlist API
	 * @param string $listAsString Comma-separated integer values in string
	 * @return int[]
	 */
	private static function toIntArray($listAsString) {
		return \array_map('intval', \explode(',', $listAsString));
	}
}
