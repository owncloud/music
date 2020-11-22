<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Gavin E <no.emai@address.for.me>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Gavin E 2020
 * @copyright Pauli Järvinen 2020
 */

namespace OCA\Music\Db;

use \OCP\AppFramework\Db\Entity;
use \OCP\IDBConnection;

class BookmarkMapper extends BaseMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'music_bookmarks', '\OCA\Music\Db\Bookmark', 'comment');
	}

	/**
	 * @param int $trackId
	 * @param string $userId
	 * @return Bookmark
	 */
	public function findByTrack($trackId, $userId) {
		$sql = $this->selectUserEntities("`track_id` = ?");
		return $this->findEntity($sql, [$userId, $trackId]);
	}

	/**
	 * @see \OCA\Music\Db\BaseMapper::findUniqueEntity()
	 * @param Bookmark $bookmark
	 * @return Bookmark
	 */
	protected function findUniqueEntity(Entity $bookmark) : Entity {
		return $this->findByTrack($bookmark->getTrackId(), $bookmark->getUserId());
	}
}
