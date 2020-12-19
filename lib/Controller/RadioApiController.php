<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2020
 */

namespace OCA\Music\Controller;

use \OCP\AppFramework\Controller;
use \OCP\AppFramework\Http;
use \OCP\AppFramework\Http\JSONResponse;

use \OCP\IRequest;

use \OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use \OCA\Music\AppFramework\Core\Logger;
use \OCA\Music\BusinessLayer\RadioStationBusinessLayer;
use \OCA\Music\Http\ErrorResponse;
use \OCA\Music\Utility\PlaylistFileService;
use \OCA\Music\Utility\Util;

class RadioApiController extends Controller {
	private $businessLayer;
	private $playlistFileService;
	private $userId;
	private $logger;

	public function __construct(string $appname,
								IRequest $request,
								RadioStationBusinessLayer $businessLayer,
								PlaylistFileService $playlistFileService,
								?string $userId,
								Logger $logger) {
		parent::__construct($appname, $request);
		$this->businessLayer = $businessLayer;
		$this->playlistFileService = $playlistFileService;
		$this->userId = $userId;
		$this->logger = $logger;
	}

	/**
	 * lists all radio stations
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function getAll() {
		$stations = $this->businessLayer->findAll($this->userId);
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
			$station = $this->businessLayer->create($this->userId, $name, $streamUrl, $homeUrl);
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
		$this->businessLayer->delete($id, $this->userId);
		return [];
	}

	/**
	 * lists a single playlist
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function get(int $id) {
		try {
			$station = $this->businessLayer->find($id, $this->userId);
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
			$station = $this->businessLayer->find($id, $this->userId);
			if ($name !== null) {
				$station->setName($name);
			}
			if ($streamUrl !== null) {
				$station->setStreamUrl($streamUrl);
			}
			if ($homeUrl !== null) {
				$station->setHomeUrl($homeUrl);
			}
			$this->businessLayer->update($station);

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
	public function importFromFile(int $id, string $filePath) {
		try {
			$result = $this->playlistFileService->importFromFile($id, $this->userId, $this->userFolder, $filePath);
			$result['playlist'] = $result['playlist']->toAPI();
			return $result;
		} catch (BusinessLayerException $ex) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, 'playlist not found');
		} catch (\OCP\Files\NotFoundException $ex) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, 'playlist file not found');
		} catch (\UnexpectedValueException $ex) {
			return new ErrorResponse(Http::STATUS_UNSUPPORTED_MEDIA_TYPE, $ex->getMessage());
		}
	}

}
