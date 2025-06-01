<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2021 - 2025
 */

namespace OCA\Music\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\RedirectResponse;

use OCP\Files\IRootFolder;

use OCP\IConfig;
use OCP\IRequest;
use OCP\IURLGenerator;

use OCA\Music\AppFramework\Core\Logger;
use OCA\Music\AppFramework\Utility\FileExistsException;
use OCA\Music\Http\ErrorResponse;
use OCA\Music\Http\RelayStreamResponse;
use OCA\Music\Service\PodcastService;

class PodcastApiController extends Controller {
	private IConfig $config;
	private IURLGenerator $urlGenerator;
	private IRootFolder $rootFolder;
	private PodcastService $podcastService;
	private string $userId;
	private Logger $logger;

	public function __construct(string $appname,
								IRequest $request,
								IConfig $config,
								IURLGenerator $urlGenerator,
								IRootFolder $rootFolder,
								PodcastService $podcastService,
								?string $userId,
								Logger $logger) {
		parent::__construct($appname, $request);
		$this->config = $config;
		$this->urlGenerator = $urlGenerator;
		$this->rootFolder = $rootFolder;
		$this->podcastService = $podcastService;
		$this->userId = $userId ?? ''; // ensure non-null to satisfy Scrutinizer; the null case should happen only when the user has already logged out
		$this->logger = $logger;
	}

	/**
	 * lists all podcasts
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function getAll() {
		$channels = $this->podcastService->getAllChannels($this->userId, /*$includeEpisodes=*/ true);
		return \array_map(fn($c) => $c->toApi($this->urlGenerator), $channels);
	}

	/**
	 * add a followed podcast
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function subscribe(?string $url) {
		if ($url === null) {
			return new ErrorResponse(Http::STATUS_BAD_REQUEST, "Mandatory argument 'url' not given");
		}

		$result = $this->podcastService->subscribe($url, $this->userId);

		switch ($result['status']) {
			case PodcastService::STATUS_OK:
				return $result['channel']->toApi($this->urlGenerator);
			case PodcastService::STATUS_INVALID_URL:
				return new ErrorResponse(Http::STATUS_BAD_REQUEST, "Invalid URL $url");
			case PodcastService::STATUS_INVALID_RSS:
				return new ErrorResponse(Http::STATUS_BAD_REQUEST, "The document at URL $url is not a valid podcast RSS feed");
			case PodcastService::STATUS_ALREADY_EXISTS:
				return new ErrorResponse(Http::STATUS_CONFLICT, 'User already has this podcast channel subscribed');
			default:
				return new ErrorResponse(Http::STATUS_INTERNAL_SERVER_ERROR, "Unexpected status code {$result['status']}");
		}
	}

	/**
	 * deletes a station
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function unsubscribe(int $id) {
		$status = $this->podcastService->unsubscribe($id, $this->userId);

		switch ($status) {
			case PodcastService::STATUS_OK:
				return new JSONResponse(['success' => true]);
			case PodcastService::STATUS_NOT_FOUND:
				return new ErrorResponse(Http::STATUS_NOT_FOUND);
			default:
				return new ErrorResponse(Http::STATUS_INTERNAL_SERVER_ERROR, "Unexpected status code $status");
		}
	}

	/**
	 * get a single podcast channel
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function get(int $id) {
		$channel = $this->podcastService->getChannel($id, $this->userId, /*includeEpisodes=*/ true);

		if ($channel !== null) {
			return $channel->toApi($this->urlGenerator);
		} else {
			return new ErrorResponse(Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * get details for a podcast channel
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function channelDetails(int $id) {
		$channel = $this->podcastService->getChannel($id, $this->userId, /*includeEpisodes=*/ false);

		if ($channel !== null) {
			return $channel->detailsToApi($this->urlGenerator);
		} else {
			return new ErrorResponse(Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * get details for a podcast episode
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function episodeDetails(int $id) {
		$episode = $this->podcastService->getEpisode($id, $this->userId);

		if ($episode !== null) {
			return $episode->detailsToApi();
		} else {
			return new ErrorResponse(Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * get audio stream for a podcast episode
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function episodeStream(int $id) {
		$episode = $this->podcastService->getEpisode($id, $this->userId);

		if ($episode !== null) {
			$streamUrl = $episode->getStreamUrl();
			if ($streamUrl === null) {
				return new ErrorResponse(Http::STATUS_NOT_FOUND, "The podcast episode $id has no stream URL");
			} elseif ($this->config->getSystemValue('music.relay_podcast_stream', true)) {
				return new RelayStreamResponse($streamUrl);
			} else {
				return new RedirectResponse($streamUrl);
			}
		} else {
			return new ErrorResponse(Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * check a single channel for updates
	 * @param int $id Channel ID
	 * @param string|null $prevHash Previous content hash known by the client. If given, the result will tell
	 *								if the channel content has updated from this state. If omitted, the result
	 *								will tell if the channel changed from its previous server-known state.
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function updateChannel(int $id, ?string $prevHash) {
		$updateResult = $this->podcastService->updateChannel($id, $this->userId, $prevHash);

		$response = [
			'success' => ($updateResult['status'] === PodcastService::STATUS_OK),
			'updated' => $updateResult['updated']
		];
		if ($updateResult['updated']) {
			$response['channel'] = $updateResult['channel']->toApi($this->urlGenerator);
		}

		return new JSONResponse($response);
	}

	/**
	 * reset all the subscribed podcasts of the user
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function resetAll() {
		$this->podcastService->resetAll($this->userId);
		return new JSONResponse(['success' => true]);
	}

	/**
	 * export all podcast channels to an OPML file
	 *
	 * @param string $name target file name
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
		try {
			$userFolder = $this->rootFolder->getUserFolder($this->userId);
			$exportedFilePath = $this->podcastService->exportToFile(
					$this->userId, $userFolder, $path, $name, $oncollision);
			return new JSONResponse(['wrote_to_file' => $exportedFilePath]);
		} catch (\OCP\Files\NotFoundException $ex) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, 'folder not found');
		} catch (FileExistsException $ex) {
			return new ErrorResponse(Http::STATUS_CONFLICT, 'file already exists', ['path' => $ex->getPath(), 'suggested_name' => $ex->getAltName()]);
		} catch (\OCP\Files\NotPermittedException $ex) {
			return new ErrorResponse(Http::STATUS_FORBIDDEN, 'user is not allowed to write to the target file');
		}
	}

	/**
	 * parse an OPML file and return list of contained channels
	 *
	 * @param string $filePath path of the file to parse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function parseListFile(string $filePath) {
		try {
			$userFolder = $this->rootFolder->getUserFolder($this->userId);
			$list = $this->podcastService->parseOpml($userFolder, $filePath);
			return $list;
		} catch (\UnexpectedValueException $ex) {
			return new ErrorResponse(Http::STATUS_UNSUPPORTED_MEDIA_TYPE, $ex->getMessage());
		}
	}
}
