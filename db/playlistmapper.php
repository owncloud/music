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
	 * @param integer $limit
	 * @param integer $offset
	 * @return Playlist[]
	 */
	public function findAll($userId, $limit=null, $offset=null){
		$sql = $this->makeSelectQuery();
		$params = array($userId);
		return $this->findEntities($sql, $params, $limit, $offset);
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
