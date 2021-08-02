<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2021
 */

namespace OCA\Music\Controller;

use \OCP\AppFramework\Controller;
use \OCP\AppFramework\Http;
use \OCP\AppFramework\Http\JSONResponse;

use \OCP\IRequest;

use \OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use \OCA\Music\AppFramework\Core\Logger;
use \OCA\Music\BusinessLayer\PodcastChannelBusinessLayer;
use \OCA\Music\BusinessLayer\PodcastEpisodeBusinessLayer;
use \OCA\Music\Http\ErrorResponse;
use \OCA\Music\Utility\Util;

class PodcastApiController extends Controller {
	private $channelBusinessLayer;
	private $episodeBusinessLayer;
	private $userId;
	private $logger;

	public function __construct(string $appname,
								IRequest $request,
								PodcastChannelBusinessLayer $channelBusinessLayer,
								PodcastEpisodeBusinessLayer $episodeBusinessLayer,
								?string $userId,
								Logger $logger) {
		parent::__construct($appname, $request);
		$this->channelBusinessLayer = $channelBusinessLayer;
		$this->episodeBusinessLayer = $episodeBusinessLayer;
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
		$episodes = $this->episodeBusinessLayer->findAll($this->userId);
		$episodesPerChannel = [];
		foreach ($episodes as $episode) {
			$episodesPerChannel[$episode->getChannelId()][] = $episode;
		}

		$channels = $this->channelBusinessLayer->findAll($this->userId);
		foreach ($channels as &$channel) {
			$channel->setEpisodes($episodesPerChannel[$channel->getId()] ?? []);
		}

		return Util::arrayMapMethod($channels, 'toApi');
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

		$content = \file_get_contents($url);
		if ($content === false) {
			return new ErrorResponse(Http::STATUS_BAD_REQUEST, "Invalid URL $url");
		}

		$xmlTree = \simplexml_load_string($content, \SimpleXMLElement::class, LIBXML_NOCDATA);
		if ($xmlTree === false || !$xmlTree->channel) {
			return new ErrorResponse(Http::STATUS_BAD_REQUEST, "The document at URL $url is not a valid podcast RSS feed");
		}

		try {
			$channel = $this->channelBusinessLayer->create($this->userId, $url, $content, $xmlTree->channel);
		} catch (\OCA\Music\AppFramework\Db\UniqueConstraintViolationException $ex) {
			return new ErrorResponse(Http::STATUS_CONFLICT, 'User already has this podcast channel subscribed');
		}

		$episodes = [];
		foreach ($xmlTree->channel->item as $episodeNode) {
			try {
				$episodes[] = $this->episodeBusinessLayer->create($this->userId, $channel->getId(), $episodeNode);
			} catch (\OCA\Music\AppFramework\Db\UniqueConstraintViolationException $ex) {
				$this->logger->log("Skipping a duplicate podcast episode with guid '{$episodeNode->guid}'", 'debug');
			}
		}

		$channel->setEpisodes($episodes);
		return $channel->toApi();
	}

	/**
	 * deletes a station
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function unsubscribe(int $id) {
		try {
			$this->channelBusinessLayer->delete($id, $this->userId); // throws if not found
			$this->episodeBusinessLayer->deleteByChannel($id, $this->userId); // does not throw
			return new JSONResponse(['success' => true]);
		} catch (BusinessLayerException $ex) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, $ex->getMessage());
		}
	}

	/**
	 * get a single podcast channel
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function get(int $id) {
		try {
			$channel = $this->channelBusinessLayer->find($id, $this->userId);
			$channel->setEpisodes($this->episodeBusinessLayer->findAllByChannel($id, $this->userId));
			return $channel->toApi();
		} catch (BusinessLayerException $ex) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, $ex->getMessage());
		}
	}

	/**
	 * reset all the subscribed podcasts of the user
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function resetAll() {
		$this->episodeBusinessLayer->deleteAll($this->userId);
		$this->channelBusinessLayer->deleteAll($this->userId);
		return new JSONResponse(['success' => true]);
	}
}
