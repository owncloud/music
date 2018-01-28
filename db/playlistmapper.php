<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Volkan Gezer <volkangezer@gmail.com>
 * @copyright Morris Jobke 2014
 * @copyright Volkan Gezer 2014
 */

namespace OCA\Music\Db;

use OCP\IDBConnection;

class PlaylistMapper extends BaseMapper {

	public function __construct(IDBConnection $db){
		parent::__construct($db, 'music_playlists', '\OCA\Music\Db\Playlist');
	}

	/**
	 * @param string $condition
	 */
	private function makeSelectQuery($condition=null){
		return 'SELECT `name`, `id`, `track_ids` ' .
			'FROM `*PREFIX*music_playlists` ' .
			'WHERE `user_id` = ? ' . $condition;
	}

	/**
	 * @param string $userId
	 * @param SortBy $sortBy sort order of the result set
	 * @param integer $limit
	 * @param integer $offset
	 * @return Playlist[]
	 */
	public function findAll($userId, $sortBy=SortBy::None, $limit=null, $offset=null){
		$sql = $this->makeSelectQuery(
				$sortBy == SortBy::Name ? 'ORDER BY LOWER(`name`)' : null
		);
		return $this->findEntities($sql, [$userId], $limit, $offset);
	}

	/**
	 * @param string $name
	 * @param string $userId
	 * @param bool $fuzzy
	 * @return Playlist[]
	 */
	public function findAllByName($name, $userId, $fuzzy = false){
		if ($fuzzy) {
			$condition = 'AND LOWER(`name`) LIKE LOWER(?) ';
			$name = '%' . $name . '%';
		} else {
			$condition = 'AND `name` = ? ';
		}
		$sql = $this->makeSelectQuery($condition . 'ORDER BY LOWER(`name`)');
		return $this->findEntities($sql, [$userId, $name]);
	}

	/**
	 * @param int $trackId
	 * @return Playlist[]
	 */
	 public function findListsContainingTrack($trackId) {
		$sql = 'SELECT * ' .
			'FROM `*PREFIX*music_playlists` ' .
			'WHERE `track_ids` LIKE ?';
		$params = array('%|' . $trackId . '|%');
		return $this->findEntities($sql, $params);
	}
}
