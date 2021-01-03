<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2020, 2021
 */

namespace OCA\Music\Controller;

use \OCP\AppFramework\Controller;
use \OCP\AppFramework\Http;
use \OCP\AppFramework\Http\JSONResponse;

use \OCP\Files\Folder;
use \OCP\IRequest;

use \OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use \OCA\Music\AppFramework\Core\Logger;
use \OCA\Music\BusinessLayer\RadioSourceBusinessLayer;
use \OCA\Music\BusinessLayer\RadioStationBusinessLayer;
use \OCA\Music\Http\ErrorResponse;
use \OCA\Music\Utility\PlaylistFileService;
use \OCA\Music\Utility\Util;

class RadioApiController extends Controller {
	private $sourceBusinessLayer;
	private $stationBusinessLayer;
	private $playlistFileService;
	private $userId;
	private $userFolder;
	private $logger;

	public function __construct(string $appname,
								IRequest $request,
								RadioSourceBusinessLayer $sourceBusinessLayer,
								RadioStationBusinessLayer $stationBusinessLayer,
								PlaylistFileService $playlistFileService,
								?string $userId,
								?Folder $userFolder,
								Logger $logger) {
		parent::__construct($appname, $request);
		$this->sourceBusinessLayer = $sourceBusinessLayer;
		$this->stationBusinessLayer = $stationBusinessLayer;
		$this->playlistFileService = $playlistFileService;
		$this->userId = $userId;
		$this->userFolder = $userFolder;
		$this->logger = $logger;
	}

	/**
	 * lists all radio stations
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function getAll() {
		$stations = $this->stationBusinessLayer->findAll($this->userId);
		return Util::arrayMapMethod($stations, 'toApi');
	}

	/**
	 * creates a station
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function create($name, $streamUrl, $homeUrl) {
		if ($streamUrl === null) {
			return new ErrorResponse(Http::STATUS_BAD_REQUEST, "Mandatory argument 'streamUrl' not given");
		} else {
			$station = $this->stationBusinessLayer->create($this->userId, $name, $streamUrl, $homeUrl);
			return $station->toApi();
		}
	}

	/**
	 * deletes a station
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function delete(int $id) {
		try {
			$this->stationBusinessLayer->delete($id, $this->userId);
			return [];
		} catch (BusinessLayerException $ex) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, $ex->getMessage());
		}
	}

	/**
	 * get a single radio station
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function get(int $id) {
		try {
			$station = $this->stationBusinessLayer->find($id, $this->userId);
			return $station->toAPI();
		} catch (BusinessLayerException $ex) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, $ex->getMessage());
		}
	}

	/**
	 * update a station
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function update(int $id, string $name = null, string $streamUrl = null, string $homeUrl = null) {
		if ($name === null && $streamUrl === null && $homeUrl === null) {
			return new ErrorResponse(Http::STATUS_BAD_REQUEST, "at least one of the args ['name', 'streamUrl', 'homrUrl'] must be given");
		}

		try {
			$station = $this->stationBusinessLayer->find($id, $this->userId);
			if ($name !== null) {
				$station->setName($name);
			}
			if ($streamUrl !== null) {
				$station->setStreamUrl($streamUrl);
			}
			if ($homeUrl !== null) {
				$station->setHomeUrl($homeUrl);
			}
			$this->stationBusinessLayer->update($station);

			return new JSONResponse($station->toApi());
		} catch (BusinessLayerException $ex) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, $ex->getMessage());
		}
	}

	/**
	 * export the station to a file
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
	public function exportToFile(int $id, string $path, string $oncollision) {
		try {
			$exportedFilePath = $this->playlistFileService->exportToFile(
					$id, $this->userId, $this->userFolder, $path, $oncollision);
			return new JSONResponse(['wrote_to_file' => $exportedFilePath]);
		} catch (BusinessLayerException $ex) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, 'playlist not found');
		} catch (\OCP\Files\NotFoundException $ex) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, 'folder not found');
		} catch (\RuntimeException $ex) {
			return new ErrorResponse(Http::STATUS_CONFLICT, $ex->getMessage());
		} catch (\OCP\Files\NotPermittedException $ex) {
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
	public function importFromFile(string $filePath) {
		try {
			$result = $this->playlistFileService->importRadioStationsFromFile($this->userId, $this->userFolder, $filePath);
			$result['new_sources'] = $this->addStationsAsAllowedSources($result['stations']);
			$result['new_sources'] = Util::arrayMapMethod($result['new_sources'], 'getUrl');
			$result['stations'] = Util::arrayMapMethod($result['stations'], 'toApi');
			return $result;
		} catch (\OCP\Files\NotFoundException $ex) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, 'playlist file not found');
		} catch (\UnexpectedValueException $ex) {
			return new ErrorResponse(Http::STATUS_UNSUPPORTED_MEDIA_TYPE, $ex->getMessage());
		} catch (BusinessLayerException $ex) {
			return new ErrorResponse(Http::STATUS_BAD_REQUEST, $ex->getMessage());
		}
	}

	/**
	 * reset all the radio stations of the user
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function resetAll() {
		$this->stationBusinessLayer->deleteAll($this->userId);
		return new JSONResponse(['success' => true]);
	}

	/**
	 * lists all allowed radio stream sources
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function getAllSources() {
		$stations = $this->sourceBusinessLayer->findAll($this->userId);
		return Util::arrayMapMethod($stations, 'toApi');
	}

	/**
	 * add an allowed radio stream source
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function addSource($url) {
		try {
			$source = $this->sourceBusinessLayer->addIfNotExists($this->userId, $url);
			if ($source !== null) {
				return $source->toApi();
			} else {
				return new \OCA\Music\Http\ErrorResponse(Http::CONFLICT, 'The URL was already listed as allowed');
			}
		} catch (BusinessLayerException $ex) {
			return new ErrorResponse(Http::STATUS_BAD_REQUEST, $ex->getMessage());
		}
	}

	/**
	 * deletes an allowed radio stream source
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function deleteSource(int $id) {
		try {
			$this->sourceBusinessLayer->delete($id, $this->userId);
			return [];
		} catch (BusinessLayerException $ex) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, $ex->getMessage());
		}
	}

	/**
	 * get a single allowed radio stream source
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function getSource(int $id) {
		try {
			$source = $this->sourceBusinessLayer->find($id, $this->userId);
			return $source->toAPI();
		} catch (BusinessLayerException $ex) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, $ex->getMessage());
		}
	}

	/**
	 * update an allowed radio stream source
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function updateSource(int $id, string $url) {
		try {
			$source = $this->sourceBusinessLayer->find($id, $this->userId);
			$source->setUrl($url);
			$this->sourceBusinessLayer->update($source);
			return new JSONResponse($source->toApi());
		} catch (BusinessLayerException $ex) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, $ex->getMessage());
		}
	}

	/**
	 * @param RadioStation[] $stations
	 * @return string[]
	 */
	private function addStationsAsAllowedSources(array $stations) : array {
		$newSources = [];
		foreach ($stations as $station) {
			$source = $this->sourceBusinessLayer->addIfNotExists($this->userId, $station->getStreamUrl());
			if ($source !== null) {
				$newSources[] = $source;
			}
		}
		return $newSources;
	}
}
