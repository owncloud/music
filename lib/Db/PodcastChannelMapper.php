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

namespace OCA\Music\Db;

use OCP\IConfig;
use OCP\IDBConnection;

/**
 * Type hint a base class method to help Scrutinizer
 * @method PodcastChannel insert(PodcastChannel $channel)
 * @phpstan-extends BaseMapper<PodcastChannel>
 */
class PodcastChannelMapper extends BaseMapper {
	public function __construct(IDBConnection $db, IConfig $config) {
		parent::__construct($db, $config, 'music_podcast_channels', PodcastChannel::class, 'title');
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
	 * Overridden from the base implementation to provide support for table-specific rules
	 *
	 * {@inheritdoc}
	 * @see BaseMapper::advFormatSqlCondition()
	 */
	protected function advFormatSqlCondition(string $rule, string $sqlOp, string $conv) : string {
		switch ($rule) {
			case 'podcast_episode':	return "`*PREFIX*music_podcast_channels`.`id` IN (SELECT `channel_id` FROM `*PREFIX*music_podcast_episodes` `e` WHERE $conv(`e`.`title`) $sqlOp $conv(?))";
			case 'time':			return "`*PREFIX*music_podcast_channels`.`id` IN (SELECT * FROM (SELECT `channel_id` FROM `*PREFIX*music_podcast_episodes` GROUP BY `channel_id` HAVING SUM(`duration`) $sqlOp ?) mysqlhack)";
			case 'pubdate':			return "`published` $sqlOp ?";
			default:				return parent::advFormatSqlCondition($rule, $sqlOp, $conv);
		}
	}

	/**
	 * @see \OCA\Music\Db\BaseMapper::findUniqueEntity()
	 * @param PodcastChannel $channel
	 * @return PodcastChannel
	 */
	protected function findUniqueEntity(Entity $channel) : Entity {
		assert($channel instanceof PodcastChannel);
		$sql = $this->selectUserEntities("`rss_hash` = ?");
		return $this->findEntity($sql, [$channel->getUserId(), $channel->getRssHash()]);
	}
}
