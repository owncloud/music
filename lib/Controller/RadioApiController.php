<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2020 - 2025
 */

namespace OCA\Music\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\RedirectResponse;

use OCP\Files\Folder;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IURLGenerator;

use OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use OCA\Music\AppFramework\Core\Logger;
use OCA\Music\BusinessLayer\RadioStationBusinessLayer;
use OCA\Music\Http\ErrorResponse;
use OCA\Music\Http\FileResponse;
use OCA\Music\Http\RelayStreamResponse;
use OCA\Music\Utility\HttpUtil;
use OCA\Music\Utility\PlaylistFileService;
use OCA\Music\Utility\RadioService;
use OCA\Music\Utility\StreamTokenService;

class RadioApiController extends Controller {
	private IConfig $config;
	private IURLGenerator $urlGenerator;
	private RadioStationBusinessLayer $businessLayer;
	private RadioService $service;
	private StreamTokenService $tokenService;
	private PlaylistFileService $playlistFileService;
	private string $userId;
	private ?Folder $userFolder;
	private Logger $logger;

	public function __construct(string $appname,
								IRequest $request,
								IConfig $config,
								IURLGenerator $urlGenerator,
								RadioStationBusinessLayer $businessLayer,
								RadioService $service,
								StreamTokenService $tokenService,
								PlaylistFileService $playlistFileService,
								?string $userId,
								?Folder $userFolder,
								Logger $logger) {
		parent::__construct($appname, $request);
		$this->config = $config;
		$this->urlGenerator = $urlGenerator;
		$this->businessLayer = $businessLayer;
		$this->service = $service;
		$this->tokenService = $tokenService;
		$this->playlistFileService = $playlistFileService;
		$this->userId = $userId ?? ''; // ensure non-null to satisfy Scrutinizer; may be null when resolveStreamUrl used on public share
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
		return \array_map(fn($s) => $s->toApi(), $stations);
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
			return $station->toApi();
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
	public function update(int $id, ?string $name = null, ?string $streamUrl = null, ?string $homeUrl = null) {
		if ($name === null && $streamUrl === null && $homeUrl === null) {
			return new ErrorResponse(Http::STATUS_BAD_REQUEST, "at least one of the args ['name', 'streamUrl', 'homeUrl'] must be given");
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
			$result['stations'] = \array_map(fn($s) => $s->toApi(), $result['stations']);
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
	* get metadata for a channel
	*
	* @NoAdminRequired
	* @NoCSRFRequired
	*/
	public function getChannelInfo(int $id, ?string $type=null) {
		try {
			$station = $this->businessLayer->find($id, $this->userId);
			$streamUrl = $station->getStreamUrl();

			switch ($type) {
				case 'icy':
					$metadata = $this->service->readIcyMetadata($streamUrl, 3, 5);
					break;
				case 'shoutcast-v1':
					$metadata = $this->service->readShoutcastV1Metadata($streamUrl);
					break;
				case 'shoutcast-v2':
					$metadata = $this->service->readShoutcastV2Metadata($streamUrl);
					break;
				case 'icecast':
					$metadata = $this->service->readIcecastMetadata($streamUrl);
					break;
				default:
					$metadata = $this->service->readIcyMetadata($streamUrl, 3, 5)
							?? $this->service->readShoutcastV2Metadata($streamUrl)
							?? $this->service->readIcecastMetadata($streamUrl)
							?? $this->service->readShoutcastV1Metadata($streamUrl);
					break;
			}

			return new JSONResponse($metadata);
		} catch (BusinessLayerException $ex) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, $ex->getMessage());
		}
	}

	/**
	 * get stream URL for a radio station
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function stationStreamUrl(int $id) {
		try {
			$station = $this->businessLayer->find($id, $this->userId);
			$streamUrl = $station->getStreamUrl();
			$resolved = $this->service->resolveStreamUrl($streamUrl);
			$relayEnabled = $this->streamRelayEnabled();
			if ($relayEnabled && !$resolved['hls']) {
				$resolved['url'] = $this->urlGenerator->linkToRoute('music.radioApi.stationStream', ['id' => $id]);
			}
			return new JSONResponse($resolved);
		} catch (BusinessLayerException $ex) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, $ex->getMessage());
		}
	}

	/**
	 * get audio stream for a radio station
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function stationStream(int $id) {
		try {
			$station = $this->businessLayer->find($id, $this->userId);
			$streamUrl = $station->getStreamUrl();
			$resolved = $this->service->resolveStreamUrl($streamUrl);
			if ($this->streamRelayEnabled()) {
				return new RelayStreamResponse($resolved['url']);
			} else {
				return new RedirectResponse($resolved['url']);
			}
		} catch (BusinessLayerException $ex) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, $ex->getMessage());
		}
	}

	/**
	 * get the actual stream URL from the given public URL
	 *
	 * Available without login since no user data is handled and this may be used on link-shared folder.
	 *
	 * @PublicPage
	 * @NoCSRFRequired
	 */
	public function resolveStreamUrl(string $url, ?string $token) {
		$url = \rawurldecode($url);

		if ($token === null) {
			return new ErrorResponse(Http::STATUS_UNAUTHORIZED, 'a security token must be passed');
		} elseif (!$this->tokenService->urlTokenIsValid($url, \rawurldecode($token))) {
			return new ErrorResponse(Http::STATUS_UNAUTHORIZED, 'the security token is invalid');
		} else {
			$resolved = $this->service->resolveStreamUrl($url);
			$relayEnabled = $this->streamRelayEnabled();
			if ($relayEnabled && !$resolved['hls']) {
				$token = $this->tokenService->tokenForUrl($resolved['url']);
				$resolved['url'] = $this->urlGenerator->linkToRoute('music.radioApi.streamFromUrl',
					['url' => \rawurlencode($resolved['url']), 'token' => \rawurlencode($token)]);
			}
			return new JSONResponse($resolved);
		}
	}

	/**
	 * create a relayed stream for the given URL if relaying enabled; otherwise just redirect to the URL
	 * 
	 * @PublicPage
	 * @NoCSRFRequired
	 */
	public function streamFromUrl(string $url, ?string $token) {
		$url = \rawurldecode($url);

		if ($token === null) {
			return new ErrorResponse(Http::STATUS_UNAUTHORIZED, 'a security token must be passed');
		} elseif (!$this->tokenService->urlTokenIsValid($url, \rawurldecode($token))) {
			return new ErrorResponse(Http::STATUS_UNAUTHORIZED, 'the security token is invalid');
		} elseif ($this->streamRelayEnabled()) {
			return new RelayStreamResponse($url);
		} else {
			return new RedirectResponse($url);
		}
	}

	/**
	 * get manifest of a HLS stream
	 *
	 * This fetches the manifest file from the given URL and returns a modified version of it.
	 * The front-end can't easily stream directly from the original source because of the Content-Security-Policy.
	 *
	 * @PublicPage
	 * @NoCSRFRequired
	 */
	public function hlsManifest(string $url, ?string $token) {
		$url = \rawurldecode($url);

		if (!$this->hlsEnabled()) {
			return new ErrorResponse(Http::STATUS_FORBIDDEN, 'the cloud admin has disabled HLS streaming');
		} elseif ($token === null) {
			return new ErrorResponse(Http::STATUS_UNAUTHORIZED, 'a security token must be passed');
		} elseif (!$this->tokenService->urlTokenIsValid($url, \rawurldecode($token))) {
			return new ErrorResponse(Http::STATUS_UNAUTHORIZED, 'the security token is invalid');
		} else {
			list('content' => $content, 'status_code' => $status, 'content_type' => $contentType)
				= $this->service->getHlsManifest($url);

			return new FileResponse([
				'content' => $content,
				'mimetype' => $contentType
			], $status);
		}
	}

	/**
	 * get one segment of a HLS stream
	 *
	 * The segment is fetched from the given URL and relayed as such to the client.
	 *
	 * @PublicPage
	 * @NoCSRFRequired
	 */
	public function hlsSegment(string $url, ?string $token) {
		$url = \rawurldecode($url);

		if (!$this->hlsEnabled()) {
			return new ErrorResponse(Http::STATUS_FORBIDDEN, 'the cloud admin has disabled HLS streaming');
		} elseif ($token === null) {
			return new ErrorResponse(Http::STATUS_UNAUTHORIZED, 'a security token must be passed');
		} elseif (!$this->tokenService->urlTokenIsValid($url, \rawurldecode($token))) {
			return new ErrorResponse(Http::STATUS_UNAUTHORIZED, 'the security token is invalid');
		} else {
			list('content' => $content, 'status_code' => $status, 'content_type' => $contentType)
				= HttpUtil::loadFromUrl($url);

			return new FileResponse([
				'content' => $content,
				'mimetype' => $contentType ?? 'application/octet-stream'
			], $status);
		}
	}

	private function hlsEnabled() : bool {
		$enabled = (bool)$this->config->getSystemValue('music.enable_radio_hls', true);
		if ($this->userId === '') {
			$enabled = (bool)$this->config->getSystemValue('music.enable_radio_hls_on_share', $enabled);
		}
		return $enabled;
	}

	private function streamRelayEnabled() : bool {
		$enabled = (bool)$this->config->getSystemValue('music.relay_radio_stream', true);
		if ($this->userId === '') {
			$enabled = (bool)$this->config->getSystemValue('music.relay_radio_stream_on_share', $enabled);
		}
		return $enabled;
	}
}
