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
		$sql = $this->selectGenres('`*PREFIX*music_genres`.`user_id` = ? AND `lower_name` = ?');
		return $this->findEntity($sql, [$genre->getUserId(), $genre->getLowerName()]);
	}

	/**
	 * Create SQL query which selects genres excluding any empty genres (having no tracks)
	 * @see \OCA\Music\Db\BaseMapper::selectEntities
	 * @param string $condition
	 * @param string|null $extension
	 * @return string SQL query
	 */
	protected function selectEntities($condition, $extension=null) {
		return $this->selectGenres($condition, 'HAVING COUNT(`track`.`id`) > 0 ' . $extension);
	}

	/**
	 * Create SQL query to select genres. Unlike the function selectEntities used by the
	 * base class BaseMapper, this function returns also the genres with no tracks at all.
	 * @param string $condition
	 * @param string|null $extension
	 * @return string SQL query
	 */
	private function selectGenres($condition, $extension=null) {
		return "SELECT
					`*PREFIX*music_genres`.`id`,
					`*PREFIX*music_genres`.`name`,
					`*PREFIX*music_genres`.`lower_name`,
					COUNT(`track`.`id`) AS `trackCount`,
					COUNT(DISTINCT(`track`.`album_id`)) AS `albumCount`,
					COUNT(DISTINCT(`track`.`artist_id`)) AS `artistCount`
				FROM `*PREFIX*music_genres`
				LEFT JOIN `*PREFIX*music_tracks` `track`
				ON `track`.`genre_id` = `*PREFIX*music_genres`.`id`
				WHERE $condition
				GROUP BY `*PREFIX*music_genres`.`id`, `*PREFIX*music_genres`.`name`, `*PREFIX*music_genres`.`lower_name`
				$extension";
	}
}
