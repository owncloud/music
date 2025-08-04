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
 * @copyright Pauli Järvinen 2020 - 2025
 */

namespace OCA\Music\Db;

use OCP\IConfig;
use OCP\IDBConnection;

/**
 * Type hint a base class method to help Scrutinizer
 * @method Bookmark findEntity(string $sql, array $params=[], ?int $limit=null, ?int $offset=null)
 * @phpstan-extends BaseMapper<Bookmark>
 */
class BookmarkMapper extends BaseMapper {
	public function __construct(IDBConnection $db, IConfig $config) {
		parent::__construct($db, $config, 'music_bookmarks', Bookmark::class, 'comment', ['type', 'entry_id', 'user_id']);
	}

	public function findByEntry(int $type, int $entryId, string $userId) : Bookmark {
		$sql = $this->selectUserEntities("`type` = ? AND `entry_id` = ?");
		return $this->findEntity($sql, [$userId, $type, $entryId]);
	}
}
