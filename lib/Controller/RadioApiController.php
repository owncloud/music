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

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;

use OCP\Files\Folder;
use OCP\IRequest;

use OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use OCA\Music\AppFramework\Core\Logger;
use OCA\Music\BusinessLayer\RadioStationBusinessLayer;
use OCA\Music\Http\ErrorResponse;
use OCA\Music\Utility\PlaylistFileService;
use OCA\Music\Utility\Util;
use OCA\Music\Utility\RadioMetadata;

class RadioApiController extends Controller {
	private $businessLayer;
	private $playlistFileService;
	private $userId;
	private $userFolder;
	private $logger;

	public function __construct(string $appname,
								IRequest $request,
								RadioStationBusinessLayer $businessLayer,
								PlaylistFileService $playlistFileService,
								?string $userId,
								?Folder $userFolder,
								Logger $logger) {
		parent::__construct($appname, $request);
		$this->businessLayer = $businessLayer;
		$this->playlistFileService = $playlistFileService;
		$this->userId = $userId ?? ''; // ensure non-null to satisfy Scrutinizer; the null case should happen only when the user has already logged out
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
		try {
			$this->businessLayer->delete($id, $this->userId);
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
	 * export all radio stations to a file
	 *
	 * @param string $name target file name without the file extension
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
	public function exportAllToFile(string $name, string $path, string $oncollision) {
		if ($this->userFolder === null) {
			// This shouldn't get actually run. The folder may be null in case the user has already logged out.
			// But in that case, the framework should block the execution before it reaches here.
			return new ErrorResponse(Http::STATUS_UNAUTHORIZED, 'no valid user folder got');
		}
		try {
			$exportedFilePath = $this->playlistFileService->exportRadioStationsToFile(
					$this->userId, $this->userFolder, $path, $name . '.m3u8', $oncollision);
			return new JSONResponse(['wrote_to_file' => $exportedFilePath]);
		} catch (\OCP\Files\NotFoundException $ex) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, 'folder not found');
		} catch (\RuntimeException $ex) {
			return new ErrorResponse(Http::STATUS_CONFLICT, $ex->getMessage());
		} catch (\OCP\Files\NotPermittedException $ex) {
			return new ErrorResponse(Http::STATUS_FORBIDDEN, 'user is not allowed to write to the target file');
		}
	}

	/**
	 * import radio stations from a file
	 * @param string $filePath path of the file to import
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function importFromFile(string $filePath) {
		if ($this->userFolder === null) {
			// This shouldn't get actually run. The folder may be null in case the user has already logged out.
			// But in that case, the framework should block the execution before it reaches here.
			return new ErrorResponse(Http::STATUS_UNAUTHORIZED, 'no valid user folder got');
		}
		try {
			$result = $this->playlistFileService->importRadioStationsFromFile($this->userId, $this->userFolder, $filePath);
			$result['stations'] = Util::arrayMapMethod($result['stations'], 'toApi');
			return $result;
		} catch (\OCP\Files\NotFoundException $ex) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, 'playlist file not found');
		} catch (\UnexpectedValueException $ex) {
			return new ErrorResponse(Http::STATUS_UNSUPPORTED_MEDIA_TYPE, $ex->getMessage());
		}
	}

	/**
	 * reset all the radio stations of the user
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function resetAll() {
		$this->businessLayer->deleteAll($this->userId);
		return new JSONResponse(['success' => true]);
	}

	/**
	* get radio metadata from url
	*
	* @NoAdminRequired
	* @NoCSRFRequired
	*/

	public function getRadioURLData(int $id) {
		try {
			$response = "";
			$station = $this->businessLayer->find($id, $this->userId);
			$stapi = $station->toAPI();
			if (isset($stapi['stream_url'])) {
				$parse_url = parse_url($stapi['stream_url']);
				$response = RadioMetadata::fetchUrlData($parse_url['scheme'] . '://' . $parse_url['host'] . ':' . $parse_url['port'] . '/7.html');
			}
			return new JSONResponse($response);

		} catch (BusinessLayerException $ex) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, $ex->getMessage());
		}
	}

	/**
	* get radio metadata from stream
	*
	* @NoAdminRequired
	* @NoCSRFRequired
	*/

	public function getRadioStreamData(int $id) {
		try {
			$response = "";
			$station = $this->businessLayer->find($id, $this->userId);
			$stapi = $station->toAPI();
			if (isset($stapi['stream_url'])) {
				$response = RadioMetadata::fetchStreamData($stapi['stream_url'], 1, 1);
			}
			return new JSONResponse($response);

		} catch (BusinessLayerException $ex) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, $ex->getMessage());
		}
	}
}
