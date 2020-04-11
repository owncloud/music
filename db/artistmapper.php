<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2013, 2014
 * @copyright Pauli Järvinen 2016 - 2020
 */

namespace OCA\Music\Db;

use OCP\IDBConnection;

class ArtistMapper extends BaseMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'music_artists', '\OCA\Music\Db\Artist', 'name');
	}

	/**
	 * @param string $userId
	 * @param integer $sortBy sort order of the result set
	 * @return Artist[]
	 */
	public function findAllHavingAlbums($userId, $sortBy=SortBy::None) {
		$sql = $this->selectUserEntities('EXISTS '.
				'(SELECT 1 FROM `*PREFIX*music_albums` `album` '.
				' WHERE `*PREFIX*music_artists`.`id` = `album`.`album_artist_id`)',
				($sortBy == SortBy::Name) ? 'ORDER BY LOWER(`name`)' : null);

		$params = [$userId];
		return $this->findEntities($sql, $params);
	}

	/**
	 * @see \OCA\Music\Db\BaseMapper::findUniqueEntity()
	 * @param Artist $artist
	 * @return Artist
	 */
	protected function findUniqueEntity($artist) {
		$sql = $this->selectUserEntities('`hash` = ?');
		return $this->findEntity($sql, [$artist->getUserId(), $artist->getHash()]);
	}
}
