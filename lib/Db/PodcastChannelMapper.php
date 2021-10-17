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

use OCP\IDBConnection;

/**
 * Type hint a base class methdo to help Scrutinizer
 * @method PodcastChannel insert(PodcastChannel $channel)
 * @phpstan-extends BaseMapper<PodcastChannel>
 */
class PodcastChannelMapper extends BaseMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'music_podcast_channels', PodcastChannel::class, 'title');
	}

	/**
	 * @return int[]
	 */
	public function findAllIdsWithNoUpdateSince(string $userId, \DateTime $timeLimit) : array {
		$sql = "SELECT `id` FROM `{$this->getTableName()}` WHERE `user_id` = ? AND `update_checked` < ?";
		$result = $this->execute($sql, [$userId, $timeLimit->format(BaseMapper::SQL_DATE_FORMAT)]);

		return \array_map('intval', $result->fetchAll(\PDO::FETCH_COLUMN));
	}

	/**
	 * @see \OCA\Music\Db\BaseMapper::findUniqueEntity()
	 * @param PodcastChannel $channel
	 * @return PodcastChannel
	 */
	protected function findUniqueEntity(Entity $channel) : Entity {
		$sql = $this->selectUserEntities("`rss_hash` = ?");
		return $this->findEntity($sql, [$channel->getUserId(), $channel->getRssHash()]);
	}
}
