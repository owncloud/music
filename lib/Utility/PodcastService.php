<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2021 - 2024
 */

namespace OCA\Music\Utility;

use OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use OCA\Music\AppFramework\Core\Logger;
use OCA\Music\BusinessLayer\PodcastChannelBusinessLayer;
use OCA\Music\BusinessLayer\PodcastEpisodeBusinessLayer;
use OCA\Music\Db\PodcastChannel;
use OCA\Music\Db\PodcastEpisode;
use OCA\Music\Db\SortBy;


class PodcastService {
	private PodcastChannelBusinessLayer $channelBusinessLayer;
	private PodcastEpisodeBusinessLayer $episodeBusinessLayer;
	private Logger $logger;

	public const STATUS_OK = 0;
	public const STATUS_INVALID_URL = 1;
	public const STATUS_INVALID_RSS = 2;
	public const STATUS_ALREADY_EXISTS = 3;
	public const STATUS_NOT_FOUND = 4;

	public function __construct(
			PodcastChannelBusinessLayer $channelBusinessLayer,
			PodcastEpisodeBusinessLayer $episodeBusinessLayer,
			Logger $logger) {
		$this->channelBusinessLayer = $channelBusinessLayer;
		$this->episodeBusinessLayer = $episodeBusinessLayer;
		$this->logger = $logger;
	}

	/**
	 * Get a specified podcast channel of a user
	 */
	public function getChannel(int $id, string $userId, bool $includeEpisodes) : ?PodcastChannel {
		try {
			$channel = $this->channelBusinessLayer->find($id, $userId);
			if ($includeEpisodes) {
				$channel->setEpisodes($this->episodeBusinessLayer->findAllByChannel($id, $userId));
			}
			return $channel;
		} catch (BusinessLayerException $ex) {
			$this->logger->log("Requested channel $id not found: " . $ex->getMessage(), 'warn');
			return null;
		}
	}

	/**
	 * Get all podcast channels of a user
	 * @return PodcastChannel[]
	 */
	public function getAllChannels(string $userId, bool $includeEpisodes) : array {
		$channels = $this->channelBusinessLayer->findAll($userId, SortBy::Name);

		if ($includeEpisodes) {
			$this->injectEpisodes($channels, $userId, /*$allChannelsIncluded=*/ true);
		}

		return $channels;
	}

	/**
	 * Get a specified podcast episode of a user
	 */
	public function getEpisode(int $id, string $userId) : ?PodcastEpisode {
		try {
			return $this->episodeBusinessLayer->find($id, $userId);
		} catch (BusinessLayerException $ex) {
			$this->logger->log("Requested episode $id not found: " . $ex->getMessage(), 'warn');
			return null;
		}
	}

	/**
	 * Get the latest added podcast episodes
	 * @return PodcastEpisode[]
	 */
	public function getLatestEpisodes(string $userId, int $maxCount) : array {
		return $this->episodeBusinessLayer->findAll($userId, SortBy::Newest, $maxCount);
	}

	/**
	 * Inject episodes to the given podcast channels
	 * @param PodcastChannel[] $channels input/output
	 * @param bool $allChannelsIncluded Set this to true if $channels contains all the podcasts of the user.
	 *									This helps in optimizing the DB query.
	 */
	public function injectEpisodes(array &$channels, string $userId, bool $allChannelsIncluded) : void {
		if ($allChannelsIncluded || \count($channels) >= $this->channelBusinessLayer::MAX_SQL_ARGS) {
			$episodes = $this->episodeBusinessLayer->findAll($userId, SortBy::Newest);
		} else {
			$episodes = $this->episodeBusinessLayer->findAllByChannel(Util::extractIds($channels), $userId);
		}

		$episodesPerChannel = Util::arrayGroupBy($episodes, 'getChannelId');

		foreach ($channels as &$channel) {
			$channel->setEpisodes($episodesPerChannel[$channel->getId()] ?? []);
		}
	}

	/**
	 * Add a followed podcast for a user from an RSS feed
	 * @return array like ['status' => int, 'channel' => ?PodcastChannel]
	 */
	public function subscribe(string $url, string $userId) : array {
		$content = HttpUtil::loadFromUrl($url)['content'];
		if ($content === false) {
			return ['status' => self::STATUS_INVALID_URL, 'channel' => null];
		}

		$xmlTree = \simplexml_load_string($content, \SimpleXMLElement::class, LIBXML_NOCDATA);
		if ($xmlTree === false || !$xmlTree->channel) {
			return ['status' => self::STATUS_INVALID_RSS, 'channel' => null];
		}

		try {
			$channel = $this->channelBusinessLayer->create($userId, $url, $content, $xmlTree->channel);
		} catch (\OCA\Music\AppFramework\Db\UniqueConstraintViolationException $ex) {
			return ['status' => self::STATUS_ALREADY_EXISTS, 'channel' => null];
		}

		$episodes = $this->updateEpisodesFromXml($xmlTree->channel->item, $userId, $channel->getId());
		$channel->setEpisodes($episodes);

		return ['status' => self::STATUS_OK, 'channel' => $channel];
	}

	/**
	 * Deletes a podcast channel from a user
	 * @return int status code
	 */
	public function unsubscribe(int $channelId, string $userId) : int {
		try {
			$this->channelBusinessLayer->delete($channelId, $userId); // throws if not found
			$this->episodeBusinessLayer->deleteByChannel($channelId, $userId); // does not throw
			return self::STATUS_OK;
		} catch (BusinessLayerException $ex) {
			$this->logger->log("Channel $channelId to be unsubscribed not found: " . $ex->getMessage(), 'warn');
			return self::STATUS_NOT_FOUND;
		}
	}

	/**
	 * Check a single podcast channel for updates
	 * @param ?string $prevHash Previous content hash known by the client. If given, the result will tell
	 *							if the channel content has updated from this state. If omitted, the result
	 *							will tell if the channel changed from its previous server-known state.
	 * @param bool $force Value true will cause the channel to be parsed and updated to the database even
	 *					in case the RSS hasn't been changed at all since the previous update. This might be
	 *					useful during the development or if the previous update was unexpectedly aborted.
	 * @return array like ['status' => int, 'updated' => bool, 'channel' => ?PodcastChannel]
	 */
	public function updateChannel(int $id, string $userId, ?string $prevHash = null, bool $force = false) : array {
		$updated = false;
		$status = self::STATUS_OK;

		try {
			$channel = $this->channelBusinessLayer->find($id, $userId);
		} catch (BusinessLayerException $ex) {
			$this->logger->log("Channel $id to be updated not found: " . $ex->getMessage(), 'warn');
			$status = self::STATUS_NOT_FOUND;
			$channel = null;
		}

		if ($channel !== null) {
			$xmlTree = null;
			$content = HttpUtil::loadFromUrl($channel->getRssUrl())['content'];
			if ($content === false) {
				$status = self::STATUS_INVALID_URL;
			} else {
				$xmlTree = \simplexml_load_string($content, \SimpleXMLElement::class, LIBXML_NOCDATA);
			}

			if (!$xmlTree || !$xmlTree->channel) {
				$this->logger->log("RSS feed for the channel {$channel->id} was invalid", 'warn');
				$this->channelBusinessLayer->markUpdateChecked($channel);
				$status = self::STATUS_INVALID_RSS;
			} else if ($this->channelBusinessLayer->updateChannel($channel, $content, $xmlTree->channel, $force)) {
				// update the episodes too if channel content has actually changed or update is forced
				$episodes = $this->updateEpisodesFromXml($xmlTree->channel->item, $userId, $id);
				$channel->setEpisodes($episodes);
				$this->episodeBusinessLayer->deleteByChannelExcluding($id, Util::extractIds($episodes), $userId);
				$updated = true;
			} else if ($prevHash !== null && $prevHash !== $channel->getContentHash()) {
				// the channel content is not new for the server but it is still new for the client
				$channel->setEpisodes($this->episodeBusinessLayer->findAllByChannel($id, $userId));
				$updated = true;
			}
		}

		return [
			'status' => $status,
			'updated' => $updated,
			'channel' => $channel
		];
	}

	/**
	 * Check updates for all channels of the user, one-by-one
	 * @return array like ['changed' => int, 'unchanged' => int, 'failed' => int]
	 *			where each int represent number of channels in that category
	 */
	public function updateAllChannels(
			string $userId, ?float $olderThan = null, bool $force = false, ?callable $progressCallback = null) : array {

		$result = ['changed' => 0, 'unchanged' => 0, 'failed' => 0];

		if ($olderThan === null) {
			$ids = $this->channelBusinessLayer->findAllIds($userId);
		} else {
			$ids = $this->channelBusinessLayer->findAllIdsNotUpdatedForHours($userId, $olderThan);
		}

		foreach ($ids as $id) {
			$channelResult = $this->updateChannel($id, $userId, null, $force);
			if ($channelResult['updated']) {
				$result['changed']++;
			} elseif ($channelResult['status'] === self::STATUS_OK) {
				$result['unchanged']++;
			} else {
				$result['failed']++;
			}

			if ($progressCallback !== null) {
				$progressCallback($channelResult);
			}
		}

		return $result;
	}

	/**
	 * Reset all the subscribed podcasts of the user
	 */
	public function resetAll(string $userId) : void {
		$this->episodeBusinessLayer->deleteAll($userId);
		$this->channelBusinessLayer->deleteAll($userId);
	}

	private function updateEpisodesFromXml(\SimpleXMLElement $items, string $userId, int $channelId) : array {
		$episodes = [];
		// loop the episodes from XML in reverse order to store them to the DB in chronological order
		for ($count = \count($items), $i = $count-1; $i >= 0; --$i) {
			if ($items[$i] !== null) {
				$episodes[] = $this->episodeBusinessLayer->addOrUpdate($userId, $channelId, $items[$i]);
			}
		}
		// return the episodes in inverted chronological order (newest first)
		return \array_reverse($episodes);
	}
}
