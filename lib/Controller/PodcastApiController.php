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
			$episodes[] = $this->episodeBusinessLayer->addOrUpdate($this->userId, $channel->getId(), $episodeNode);
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
	 * check a single channel for updates
	 * @param int $id Channel ID
	 * @param string|null $prevHash Previous content hash known by the client. If given, the result will tell
	 *								if the channel content has updated from this state. If omitted, the result
	 *								will thell if the channel changed from its previous server-known state.
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function updateChannel(int $id, ?string $prevHash) {
		$updated = false;

		try {
			$channel = $this->channelBusinessLayer->find($id, $this->userId);
		} catch (BusinessLayerException $ex) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, $ex->getMessage());
		}

		$xmlTree = false;
		$content = \file_get_contents($channel->getRssUrl());
		if ($content === null) {
			$this->logger->log("Could not load RSS feed for channel {$channel->id}", 'warn');
		} else {
			$xmlTree = \simplexml_load_string($content, \SimpleXMLElement::class, LIBXML_NOCDATA);
		}

		if ($xmlTree === false || !$xmlTree->channel) {
			$this->logger->log("RSS feed for the chanenl {$channel->id} was invalid", 'warn');
			return new JSONResponse(['success' => false]);
		} else if ($this->channelBusinessLayer->updateChannel($channel, $content, $xmlTree->channel)) {
			// channel content has actually changed, update the episodes too
			$episodes = [];
			foreach ($xmlTree->channel->item as $episodeNode) {
				$episodes[] = $this->episodeBusinessLayer->addOrUpdate($this->userId, $id, $episodeNode);
			}
			$channel->setEpisodes($episodes);
			$this->episodeBusinessLayer->deleteByChannelExcluding($id, Util::extractIds($episodes), $this->userId);
			$updated = true;
		} else if ($prevHash !== null && $prevHash !== $channel->getContentHash()) {
			// the channel content is not new for the server but it is still new for the client
			$channel->setEpisodes($this->episodeBusinessLayer->findAllByChannel($id, $this->userId));
			$updated = true;
		}

		$response = [
			'success' => true,
			'updated' => $updated,
		];
		if ($updated) {
			$response['channel'] = $channel->toApi();
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
		$this->episodeBusinessLayer->deleteAll($this->userId);
		$this->channelBusinessLayer->deleteAll($this->userId);
		return new JSONResponse(['success' => true]);
	}
}
