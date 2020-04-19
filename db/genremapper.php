<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2020
 */

namespace OCA\Music\Db;

use OCP\IDBConnection;

class GenreMapper extends BaseMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'music_genres', '\OCA\Music\Db\Genre', 'name');
	}

	/**
	 * @see \OCA\Music\Db\BaseMapper::findUniqueEntity()
	 * @param Genre $genre
	 * @return Genre
	 */
	protected function findUniqueEntity($genre) {
		$sql = $this->selectUserEntities('`lower_name` = ?');
		return $this->findEntity($sql, [$genre->getUserId(), $genre->getLowerName()]);
	}

	/**
	 * Count tracks, albums, and artists by genre
	 * @param string $userId
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return Genre[] with also all the count properties set
	 */
	public function findAllWithCounts($userId, $limit=null, $offset=null) {
		$sql = 'SELECT
					`genre`.`id`,
					`genre`.`name`,
					`genre`.`lower_name`,
					COUNT(`track`.`id`) AS `trackCount`,
					COUNT(DISTINCT(`track`.`album_id`)) AS `albumCount`,
					COUNT(DISTINCT(`track`.`artist_id`)) AS `artistCount`
				FROM `*PREFIX*music_tracks` `track`
				INNER JOIN `*PREFIX*music_genres` `genre`
				ON `track`.`genre_id` = `genre`.`id`
				WHERE `track`.`genre_id` IS NOT NULL and `track`.`user_id` = ?
				GROUP BY `genre`.`id`, `genre`.`name`, `genre`.`lower_name`
				ORDER BY `genre`.`lower_name`';

		if ($limit) {
			$sql .= " LIMIT $limit";
		}

		if ($offset) {
			$sql .= " OFFSET $offset";
		}

		$rows = $this->execute($sql, [$userId]);

		return $rows->fetchAll(\PDO::FETCH_CLASS, $this->entityClass);
	}

}
