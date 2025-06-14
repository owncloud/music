<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2020 - 2025
 */

namespace OCA\Music\Db;

use OCP\IConfig;
use OCP\IDBConnection;

/**
 * @phpstan-extends BaseMapper<Genre>
 */
class GenreMapper extends BaseMapper {
	public function __construct(IDBConnection $db, IConfig $config) {
		parent::__construct($db, $config, 'music_genres', Genre::class, 'name', ['user_id', 'lower_name']);
	}

	/**
	 * Create SQL query which selects genres excluding any empty genres (having no tracks)
	 * @see \OCA\Music\Db\BaseMapper::selectEntities
	 * @return string SQL query
	 */
	protected function selectEntities(string $condition, ?string $extension=null) : string {
		return $this->selectGenres($condition, 'HAVING COUNT(`track`.`id`) > 0 ' . $extension);
	}

	/**
	 * Create SQL query to select genres. Unlike the function selectEntities used by the
	 * base class BaseMapper, this function returns also the genres with no tracks at all.
	 * @return string SQL query
	 */
	private function selectGenres(string $condition, ?string $extension=null) : string {
		return "SELECT
					`*PREFIX*music_genres`.`id`,
					`*PREFIX*music_genres`.`name`,
					`*PREFIX*music_genres`.`lower_name`,
					`*PREFIX*music_genres`.`created`,
					`*PREFIX*music_genres`.`updated`,
					COUNT(`track`.`id`) AS `trackCount`,
					COUNT(DISTINCT(`track`.`album_id`)) AS `albumCount`,
					COUNT(DISTINCT(`track`.`artist_id`)) AS `artistCount`
				FROM `*PREFIX*music_genres`
				LEFT JOIN `*PREFIX*music_tracks` `track`
				ON `track`.`genre_id` = `*PREFIX*music_genres`.`id`
				WHERE $condition
				GROUP BY
					`*PREFIX*music_genres`.`id`,
					`*PREFIX*music_genres`.`name`,
					`*PREFIX*music_genres`.`lower_name`,
					`*PREFIX*music_genres`.`created`,
					`*PREFIX*music_genres`.`updated`
				$extension";
	}
}
