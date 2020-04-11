<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Volkan Gezer <volkangezer@gmail.com>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2014
 * @copyright Volkan Gezer 2014
 * @copyright Pauli Järvinen 2016 - 2020
 */

namespace OCA\Music\Db;

use OCP\IDBConnection;

class PlaylistMapper extends BaseMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'music_playlists', '\OCA\Music\Db\Playlist');
	}

	/**
	 * @param string $userId
	 * @param integer $sortBy sort order of the result set
	 * @param integer|null $limit
	 * @param integer|null $offset
	 * @return Playlist[]
	 */
	public function findAll($userId, $sortBy=SortBy::None, $limit=null, $offset=null) {
		$sql = $this->selectUserEntities(
				'', $sortBy == SortBy::Name ? 'ORDER BY LOWER(`name`)' : null
		);
		return $this->findEntities($sql, [$userId], $limit, $offset);
	}

	/**
	 * @param string $name
	 * @param string $userId
	 * @param bool $fuzzy
	 * @param integer|null $limit
	 * @param integer|null $offset
	 * @return Playlist[]
	 */
	public function findAllByName($name, $userId, $fuzzy = false, $limit=null, $offset=null) {
		if ($fuzzy) {
			$condition = 'LOWER(`name`) LIKE LOWER(?) ';
			$name = '%' . $name . '%';
		} else {
			$condition = '`name` = ? ';
		}
		$sql = $this->selectUserEntities($condition, 'ORDER BY LOWER(`name`)');
		return $this->findEntities($sql, [$userId, $name], $limit, $offset);
	}

	/**
	 * @param int $trackId
	 * @return Playlist[]
	 */
	public function findListsContainingTrack($trackId) {
		$sql = $this->selectEntities('`track_ids` LIKE ?');
		$params = ['%|' . $trackId . '|%'];
		return $this->findEntities($sql, $params);
	}

	/**
	 * @see \OCA\Music\Db\BaseMapper::findUniqueEntity()
	 * @param Playlist $playlist
	 * @return Playlist
	 */
	protected function findUniqueEntity($playlist) {
		// The playlist table has no unique constraints, and hence, this function
		// should never be called.
		throw new \BadMethodCallException("not supported");
	}
}
