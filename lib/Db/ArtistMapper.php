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
	 * Overridden from the base implementation to provide support for table-specific rules
	 *
	 * {@inheritdoc}
	 * @see BaseMapper::advFormatSqlCondition()
	 */
	protected function advFormatSqlCondition(string $rule, string $sqlOp) : string {
		// The extra subquery "mysqlhack" seen around some nested queries is needed in order for these to not be insanely slow on MySQL.
		// In case of 'recent_played', the MySQL 5.5.62 errored with "1235 This version of MySQL doesn't yet support 'LIMIT & IN/ALL/ANY/SOME subquery'" without the extra subquery.
		switch ($rule) {
			case 'album':			return "`*PREFIX*music_artists`.`id` IN (SELECT `artist_id` FROM `*PREFIX*music_tracks` `t` JOIN `*PREFIX*music_albums` `a` ON `t`.`album_id` = `a`.`id` WHERE LOWER(`a`.`name`) $sqlOp LOWER(?))";
			case 'artist':			return parent::advFormatSqlCondition('name', $sqlOp); // alias
			case 'song':			return "`*PREFIX*music_artists`.`id` IN (SELECT `artist_id` FROM `*PREFIX*music_tracks` `t` WHERE LOWER(`t`.`title`) $sqlOp LOWER(?))";
			case 'songrating':		return "`*PREFIX*music_artists`.`id` IN (SELECT `artist_id` FROM `*PREFIX*music_tracks` `t` WHERE `t`.`rating` $sqlOp ?)";
			case 'albumrating':		return "`*PREFIX*music_artists`.`id` IN (SELECT `album_artist_id` from `*PREFIX*music_albums` `al` WHERE `al`.`rating` $sqlOp ?)";
			case 'played_times':	return "`*PREFIX*music_artists`.`id` IN (SELECT * FROM (SELECT `artist_id` from `*PREFIX*music_tracks` GROUP BY `artist_id` HAVING SUM(`play_count`) $sqlOp ?) mysqlhack)";
			case 'last_play':		return "`*PREFIX*music_artists`.`id` IN (SELECT * FROM (SELECT `artist_id` from `*PREFIX*music_tracks` GROUP BY `artist_id` HAVING MAX(`last_played`) $sqlOp ?) mysqlhack)";
			case 'played':			// fall through, we give no access to other people's data; not part of the API spec but Ample uses this
			case 'myplayed':		return "`*PREFIX*music_artists`.`id` IN (SELECT * FROM (SELECT `artist_id` from `*PREFIX*music_tracks` GROUP BY `artist_id` HAVING MAX(`last_played`) $sqlOp) mysqlhack)"; // operator "IS NULL" or "IS NOT NULL"
			case 'album_count':		return "`*PREFIX*music_artists`.`id` IN (SELECT * FROM (SELECT `artist_id` FROM `*PREFIX*music_tracks` `t` JOIN `*PREFIX*music_albums` `a` ON `t`.`album_id` = `a`.`id` GROUP BY `artist_id` HAVING COUNT(DISTINCT `a`.`id`) $sqlOp ?) mysqlhack)";
			case 'song_count':		return "`*PREFIX*music_artists`.`id` IN (SELECT * FROM (SELECT `artist_id` FROM `*PREFIX*music_tracks` GROUP BY `artist_id` HAVING COUNT(`id`) $sqlOp ?) mysqlhack)";
			case 'time':			return "`*PREFIX*music_artists`.`id` IN (SELECT * FROM (SELECT `artist_id` FROM `*PREFIX*music_tracks` GROUP BY `artist_id` HAVING SUM(`length`) $sqlOp ?) mysqlhack)";
			case 'genre':			return "`*PREFIX*music_artists`.`id` IN (SELECT * FROM (SELECT `artist_id` FROM `*PREFIX*music_tracks` `t` JOIN `*PREFIX*music_genres` `g` ON `t`.`genre_id` = `g`.`id` GROUP BY `artist_id` HAVING LOWER(GROUP_CONCAT(`g`.`name`)) $sqlOp LOWER(?)) mysqlhack)"; // GROUP_CONCAT not available on PostgreSQL
			case 'song_genre':		return "`*PREFIX*music_artists`.`id` IN (SELECT `artist_id` FROM `*PREFIX*music_tracks` `t` JOIN `*PREFIX*music_genres` `g` ON `t`.`genre_id` = `g`.`id` WHERE LOWER(`g`.`name`) $sqlOp LOWER(?))";
			case 'no_genre':		return "`*PREFIX*music_artists`.`id` IN (SELECT `artist_id` FROM `*PREFIX*music_tracks` `t` JOIN `*PREFIX*music_genres` `g` ON `t`.`genre_id` = `g`.`id` WHERE `g`.`name` " . (($sqlOp == 'IS NOT NULL') ? '=' : '!=') . ' "")';
			case 'playlist':		return "`*PREFIX*music_artists`.`id` IN (SELECT `artist_id` FROM `*PREFIX*music_tracks` `t` WHERE $sqlOp EXISTS (SELECT 1 from `*PREFIX*music_playlists` `p` WHERE `p`.`id` = ? AND `p`.`track_ids` LIKE CONCAT('%|',`t`.`id`, '|%')))";
			case 'playlist_name':	return "`*PREFIX*music_artists`.`id` IN (SELECT `artist_id` FROM `*PREFIX*music_tracks` `t` WHERE EXISTS (SELECT 1 from `*PREFIX*music_playlists` `p` WHERE `p`.`name` $sqlOp ? AND `p`.`track_ids` LIKE CONCAT('%|',`t`.`id`, '|%')))";
			case 'file':			return "`*PREFIX*music_artists`.`id` IN (SELECT `artist_id` FROM `*PREFIX*music_tracks` `t` JOIN `*PREFIX*filecache` `f` ON `t`.`file_id` = `f`.`fileid` WHERE LOWER(`f`.`name`) $sqlOp LOWER(?))";
			case 'recent_played':	return "`*PREFIX*music_artists`.`id` IN (SELECT `artist_id` FROM (SELECT `artist_id`, MAX(`last_played`) FROM `*PREFIX*music_tracks` WHERE `user_id` = ? GROUP BY `artist_id` ORDER BY MAX(`last_played`) DESC LIMIT $sqlOp) mysqlhack)";
			case 'mbid_artist':		return parent::advFormatSqlCondition('mbid', $sqlOp); // alias
			case 'mbid_song':		return "`*PREFIX*music_artists`.`id` IN (SELECT `artist_id` FROM `*PREFIX*music_tracks` `t` WHERE `t`.`mbid` $sqlOp ?)";
			case 'mbid_album':		return "`*PREFIX*music_artists`.`id` IN (SELECT `album_artist_id` from `*PREFIX*music_albums` `al` WHERE `al`.`mbid` $sqlOp ?)";
			case 'has_image':		return "`cover_file_id` $sqlOp"; // operator "IS NULL" or "IS NOT NULL"
			default:				return parent::advFormatSqlCondition($rule, $sqlOp);
		}
	}

	/**
	 * {@inheritdoc}
	 * @see \OCA\Music\Db\BaseMapper::findUniqueEntity()
	 * @param Artist $artist
	 * @return Artist
	 */
	protected function findUniqueEntity(Entity $artist) : Entity {
		$sql = $this->selectUserEntities('`hash` = ?');
		return $this->findEntity($sql, [$artist->getUserId(), $artist->getHash()]);
	}
}
