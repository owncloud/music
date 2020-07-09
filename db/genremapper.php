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
	 * @see \OCA\Music\Db\BaseMapper::selectEntities
	 * @param string $condition 
	 * @param string|null $extension
	 * @return string SQL query
	 */
	protected function selectEntities($condition, $extension=null) {
		return "SELECT
					`*PREFIX*music_genres`.`id`,
					`*PREFIX*music_genres`.`name`,
					`*PREFIX*music_genres`.`lower_name`,
					COUNT(`track`.`id`) AS `trackCount`,
					COUNT(DISTINCT(`track`.`album_id`)) AS `albumCount`,
					COUNT(DISTINCT(`track`.`artist_id`)) AS `artistCount`
				FROM `*PREFIX*music_tracks` `track`
				INNER JOIN `*PREFIX*music_genres`
				ON `track`.`genre_id` = `*PREFIX*music_genres`.`id`
				WHERE `track`.`genre_id` IS NOT NULL AND $condition
				GROUP BY `*PREFIX*music_genres`.`id`, `*PREFIX*music_genres`.`name`, `*PREFIX*music_genres`.`lower_name`
				HAVING COUNT(`track`.`id`) > 0";
	}

}
