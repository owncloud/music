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
 * @copyright Pauli Järvinen 2016 - 2023
 */

namespace OCA\Music\Db;

use OCA\Music\Utility\Util;

use OCP\IDBConnection;

/**
 * @phpstan-extends BaseMapper<Artist>
 */
class ArtistMapper extends BaseMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'music_artists', Artist::class, 'name');
	}

	/**
	 * @param string $userId
	 * @param integer $sortBy sort order of the result set
	 * @return Artist[]
	 */
	public function findAllHavingAlbums(string $userId, int $sortBy=SortBy::None, ?int $limit=null, ?int $offset=null) : array {
		$sql = $this->selectUserEntities('EXISTS '.
				'(SELECT 1 FROM `*PREFIX*music_albums` `album` '.
				' WHERE `*PREFIX*music_artists`.`id` = `album`.`album_artist_id`)',
				($sortBy == SortBy::Name) ? 'ORDER BY LOWER(`name`)' : null);

		$params = [$userId];
		return $this->findEntities($sql, $params, $limit, $offset);
	}

	/**
	 * @param int $genreId
	 * @param string $userId
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return Artist[]
	 */
	public function findAllByGenre(int $genreId, string $userId, ?int $limit=null, ?int $offset=null) : array {
		$sql = $this->selectUserEntities('EXISTS '.
				'(SELECT 1 FROM `*PREFIX*music_tracks` `track`
				  WHERE `*PREFIX*music_artists`.`id` = `track`.`artist_id`
				  AND `track`.`genre_id` = ?)');

		$params = [$userId, $genreId];
		return $this->findEntities($sql, $params, $limit, $offset);
	}

	/**
	 * returns summed track play counts of each aritst of the user, omittig artists which have never been played
	 *
	 * @return array [int => int], keys are artist IDs and values are play count sums; ordered largest counts first
	 */
	public function getArtistTracksPlayCount(string $userId, ?int $limit=null, ?int $offset=null) : array {
		$sql = 'SELECT `artist_id`, SUM(`play_count`) AS `sum_count`
				FROM `*PREFIX*music_tracks`
				WHERE `user_id` = ? AND `play_count` > 0
				GROUP BY `artist_id`
				ORDER BY `sum_count` DESC, `artist_id`'; // the second criterion is just to make the order predictable on even counts

		$result = $this->execute($sql, [$userId], $limit, $offset);
		$playCountByArtist = [];
		while ($row = $result->fetch()) {
			$playCountByArtist[$row['artist_id']] = (int)$row['sum_count'];
		}
		return $playCountByArtist;
	}

	/**
	 * returns the latest play time of each artist of the user, omittig artists which have never been played
	 *
	 * @return array [int => string], keys are artist IDs and values are date-times; ordered latest times first
	 */
	public function getLatestArtistPlayTimes(string $userId, ?int $limit=null, ?int $offset=null) : array {
		$sql = 'SELECT `artist_id`, MAX(`last_played`) AS `latest_time`
				FROM `*PREFIX*music_tracks`
				WHERE `user_id` = ? AND `last_played` IS NOT NULL
				GROUP BY `artist_id`
				ORDER BY `latest_time` DESC';

		$result = $this->execute($sql, [$userId], $limit, $offset);
		$latestTimeByArtist = [];
		while ($row = $result->fetch()) {
			$latestTimeByArtist[$row['artist_id']] = $row['latest_time'];
		}
		return $latestTimeByArtist;
	}

	/**
	 * returns the latest play time of each artist of the user, including artists which have never been played
	 *
	 * @return array [int => ?string], keys are artist IDs and values are date-times (or null for never played);
	 *									ordered furthest times first
	 */
	public function getFurthestArtistPlayTimes(string $userId, ?int $limit=null, ?int $offset=null) : array {
		$sql = 'SELECT `artist_id`, MAX(`last_played`) AS `latest_time`
				FROM `*PREFIX*music_tracks`
				WHERE `user_id` = ?
				GROUP BY `artist_id`
				ORDER BY `latest_time` ASC';

		$result = $this->execute($sql, [$userId], $limit, $offset);
		$latestTimeByArtist = [];
		while ($row = $result->fetch()) {
			$latestTimeByArtist[$row['artist_id']] = $row['latest_time'];
		}
		return $latestTimeByArtist;
	}

	/**
	 * @param integer[] $coverFileIds
	 * @param string[]|null $userIds the users whose music library is targeted; all users are targeted if omitted
	 * @return Artist[] artists which got modified (with incomplete data, only id and user are valid),
	 *         empty array if none
	 */
	public function removeCovers(array $coverFileIds, ?array $userIds=null) : array {
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
