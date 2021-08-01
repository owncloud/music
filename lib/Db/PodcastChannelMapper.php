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

class PodcastChannelMapper extends BaseMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'music_podcast_channels', '\OCA\Music\Db\PodcastChannel', 'title');
	}

	/**
	 * @see \OCA\Music\Db\BaseMapper::findUniqueEntity()
	 * @param PodcastChannel $channel
	 * @return PodcastChannel
	 */
	protected function findUniqueEntity(Entity $episode) : Entity {
		$sql = $this->selectUserEntities("`rss_hash` = ?");
		return $this->findEntity($sql, [$entity->getUserId(), $entity->getRssHash()]);
	}
}
