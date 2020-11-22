<?php declare(strict_types=1);

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

use \OCA\Music\Utility\Util;

use \OCP\AppFramework\Db\Entity;
use \OCP\IDBConnection;

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
	 * @param int $genreId
	 * @param string $userId
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return Artist[]
	 */
	public function findAllByGenre($genreId, $userId, $limit=null, $offset=null) {
		$sql = $this->selectUserEntities('EXISTS '.
				'(SELECT 1 FROM `*PREFIX*music_tracks` `track`
				  WHERE `*PREFIX*music_artists`.`id` = `track`.`artist_id`
				  AND `track`.`genre_id` = ?)');

		$params = [$userId, $genreId];
		return $this->findEntities($sql, $params, $limit, $offset);
	}

	/**
	 * @param integer[] $coverFileIds
	 * @param string[]|null $userIds the users whose music library is targeted; all users are targeted if omitted
	 * @return Artist[] artists which got modified (with incomplete data, only id and user are valid),
	 *         empty array if none
	 */
	public function removeCovers($coverFileIds, $userIds=null) {
		// find albums using the given file as cover
		$sql = 'SELECT `id`, `user_id` FROM `*PREFIX*music_artists` WHERE `cover_file_id` IN ' .
		$this->questionMarks(\count($coverFileIds));
		$params = $coverFileIds;
		if ($userIds !== null) {
			$sql .= ' AND `user_id` IN ' . $this->questionMarks(\count($userIds));
			$params = \array_merge($params, $userIds);
		}
		$artists = $this->findEntities($sql, $params);

		// if any artists found, remove the cover from those
		$count = \count($artists);
		if ($count) {
			$sql = 'UPDATE `*PREFIX*music_artists`
					SET `cover_file_id` = NULL
					WHERE `id` IN ' . $this->questionMarks($count);
			$params = Util::extractIds($artists);
			$this->execute($sql, $params);
		}

		return $artists;
	}

	/**
	 * @see \OCA\Music\Db\BaseMapper::findUniqueEntity()
	 * @param Artist $artist
	 * @return Artist
	 */
	protected function findUniqueEntity(Entity $artist) : Entity {
		$sql = $this->selectUserEntities('`hash` = ?');
		return $this->findEntity($sql, [$artist->getUserId(), $artist->getHash()]);
	}
}
