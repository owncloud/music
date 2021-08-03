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

namespace OCA\Music\Db;

use \OCP\AppFramework\Db\Entity;
use \OCP\IDBConnection;

class PodcastEpisodeMapper extends BaseMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'music_podcast_episodes', '\OCA\Music\Db\PodcastEpisode', 'title');
	}

	/**
	 * @return PodcastEpisode[]
	 */
	public function findAllByChannel(int $channelId, string $userId) : array {
		$sql = $this->selectUserEntities("`channel_id` = ?");
		return $this->findEntities($sql, [$userId, $channelId]);
	}

	public function deleteByChannel(int $channelId, string $userId) : void {
		$this->deleteByCond('`channel_id` = ? AND `user_id` = ?', [$channelId, $userId]);
	}

	public function deleteByChannelExcluding(int $channelId, array $excludedIds, string $userId) : void {
		$excludeCount = \count($excludedIds);
		if ($excludeCount === 0) {
			$this->deleteByChannel($channelId, $userId);
		} else {
			$this->deleteByCond(
				'`channel_id` = ? AND `user_id` = ? AND `id` NOT IN ' . $this->questionMarks($excludeCount),
				\array_merge([$channelId, $userId], $excludedIds)
			);
		}
	}

	/**
	 * @see \OCA\Music\Db\BaseMapper::findUniqueEntity()
	 * @param PodcastEpisode $episode
	 * @return PodcastEpisode
	 */
	protected function findUniqueEntity(Entity $episode) : Entity {
		$sql = $this->selectUserEntities("`guid_hash` = ?");
		return $this->findEntity($sql, [$episode->getUserId(), $episode->getGuidHash()]);
	}
}
