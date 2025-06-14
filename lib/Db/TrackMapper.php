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
 * @copyright Pauli Järvinen 2016 - 2025
 */

namespace OCA\Music\Db;

use OCP\IConfig;
use OCP\IDBConnection;

/**
 * @method Track findEntity(string $sql, array $params)
 * @method Track[] findEntities(string $sql, array $params, ?int $limit=null, ?int $offset=null)
 * @phpstan-extends BaseMapper<Track>
 */
class TrackMapper extends BaseMapper {
	public function __construct(IDBConnection $db, IConfig $config) {
		parent::__construct($db, $config, 'music_tracks', Track::class, 'title', ['file_id', 'user_id'], 'album_id');
	}

	/**
	 * Override the base implementation to include data from multiple tables
	 *
	 * {@inheritdoc}
	 * @see BaseMapper::selectEntities()
	 */
	protected function selectEntities(string $condition, ?string $extension=null) : string {
		return "SELECT `*PREFIX*music_tracks`.*, `file`.`name` AS `filename`, `file`.`size`, `file`.`mtime` AS `file_mod_time`, `file`.`parent` AS `folder_id`,
						`album`.`name` AS `album_name`, `artist`.`name` AS `artist_name`, `genre`.`name` AS `genre_name`
				FROM `*PREFIX*music_tracks`
				INNER JOIN `*PREFIX*filecache` `file`
				ON `*PREFIX*music_tracks`.`file_id` = `file`.`fileid`
				INNER JOIN `*PREFIX*music_albums` `album`
				ON `*PREFIX*music_tracks`.`album_id` = `album`.`id`
				INNER JOIN `*PREFIX*music_artists` `artist`
				ON `*PREFIX*music_tracks`.`artist_id` = `artist`.`id`
				LEFT JOIN `*PREFIX*music_genres` `genre`
				ON `*PREFIX*music_tracks`.`genre_id` = `genre`.`id`
				WHERE $condition $extension";
	}

	/**
	 * Overridden from the base implementation to add support for sorting by artist, play_count, and last_played.
	 *
	 * {@inheritdoc}
	 * @see BaseMapper::formatSortingClause()
	 */
	protected function formatSortingClause(int $sortBy, bool $invertSort = false) : ?string {
		switch ($sortBy) {
			case SortBy::Parent:
				$dir = $invertSort ? 'DESC' : 'ASC';
				// Note: the alternative form "LOWER(`artist_name`) wouldn't work on PostgreSQL, see https://github.com/owncloud/music/issues/1046 for a similar case
				return "ORDER BY LOWER(`artist`.`name`) $dir, LOWER(`title`) $dir";
			case SortBy::PlayCount:
				$dir = $invertSort ? 'ASC' : 'DESC';
				return "ORDER BY `play_count` $dir";
			case SortBy::LastPlayed:
				$dir = $invertSort ? 'ASC' : 'DESC';
				return "ORDER BY `last_played` $dir";
			default:
				return parent::formatSortingClause($sortBy, $invertSort);
		}
	}

	/**
	 * Returns all tracks of the given artist (both album and track artists are considered)
	 * @param int[] $artistIds
	 * @return Track[]
	 */
	public function findAllByArtist(array $artistIds, string $userId, ?int $limit=null, ?int $offset=null) : array {
		$questionMarks = $this->questionMarks(\count($artistIds));
		$sql = $this->selectUserEntities(
				"`artist_id` IN $questionMarks OR `album_id` IN (SELECT `id` from `*PREFIX*music_albums` WHERE `album_artist_id` IN $questionMarks)",
				'ORDER BY LOWER(`title`)');
		$params = \array_merge([$userId], $artistIds, $artistIds);
		return $this->findEntities($sql, $params, $limit, $offset);
	}

	/**
	 * @param int[] $albumIds
	 * @return Track[]
	 */
	public function findAllByAlbum(array $albumIds, string $userId, ?int $artistId=null, ?int $limit=null, ?int $offset=null) : array {
		$condition = '`album_id` IN ' . $this->questionMarks(\count($albumIds));
		$params = \array_merge([$userId], $albumIds);

		if ($artistId !== null) {
			$condition .= ' AND `artist_id` = ? ';
			$params[] = $artistId;
		}

		$sql = $this->selectUserEntities($condition,
				'ORDER BY `*PREFIX*music_tracks`.`disk`, `number`, LOWER(`file`.`name`)');
		return $this->findEntities($sql, $params, $limit, $offset);
	}

	/**
	 * @return Track[]
	 */
	public function findAllByFolder(int $folderId, string $userId, ?int $limit=null, ?int $offset=null) : array {
		$sql = $this->selectUserEntities('`file`.`parent` = ?', 'ORDER BY LOWER(`file`.`name`)');
		$params = [$userId, $folderId];
		return $this->findEntities($sql, $params, $limit, $offset);
	}

	/**
	 * @return Track[]
	 */
	public function findAllByGenre(int $genreId, string $userId, ?int $limit=null, ?int $offset=null) : array {
		$sql = $this->selectUserEntities('`genre_id` = ?', 'ORDER BY LOWER(`title`)');
		$params = [$userId, $genreId];
		return $this->findEntities($sql, $params, $limit, $offset);
	}

	/**
	 * @return int[]
	 */
	public function findAllFileIds(string $userId) : array {
		$sql = 'SELECT `file_id` FROM `*PREFIX*music_tracks` WHERE `user_id` = ?';
		$result = $this->execute($sql, [$userId]);
		return $result->fetchAll(\PDO::FETCH_COLUMN);
	}

	/**
	 * Find a track of user matching a file ID
	 * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
	 */
	public function findByFileId(int $fileId, string $userId) : Track {
		$sql = $this->selectUserEntities('`file_id` = ?');
		$params = [$userId, $fileId];
		return $this->findEntity($sql, $params);
	}

	/**
	 * Find tracks of user with multiple file IDs
	 * @param integer[] $fileIds
	 * @param string[] $userIds
	 * @return Track[]
	 */
	public function findByFileIds(array $fileIds, array $userIds) : array {
		$sql = $this->selectEntities(
				'`*PREFIX*music_tracks`.`user_id` IN ' . $this->questionMarks(\count($userIds)) .
				' AND `file_id` IN '. $this->questionMarks(\count($fileIds)));
		$params = \array_merge($userIds, $fileIds);
		return $this->findEntities($sql, $params);
	}

	/**
	 * Finds tracks of all users matching one or multiple file IDs
	 * @param integer[] $fileIds
	 * @return Track[]
	 */
	public function findAllByFileIds(array $fileIds) : array {
		$sql = $this->selectEntities('`file_id` IN '.
				$this->questionMarks(\count($fileIds)));
		return $this->findEntities($sql, $fileIds);
	}

	public function countByArtist(int $artistId) : int {
		$sql = 'SELECT COUNT(*) AS `count` FROM `*PREFIX*music_tracks` WHERE `artist_id` = ?';
		$result = $this->execute($sql, [$artistId]);
		$row = $result->fetch();
		return (int)$row['count'];
	}

	public function countByAlbum(int $albumId) : int {
		$sql = 'SELECT COUNT(*) AS `count` FROM `*PREFIX*music_tracks` WHERE `album_id` = ?';
		$result = $this->execute($sql, [$albumId]);
		$row = $result->fetch();
		return (int)$row['count'];
	}

	/**
	 * @return integer Duration in seconds
	 */
	public function totalDurationOfAlbum(int $albumId) : int {
		$sql = 'SELECT SUM(`length`) AS `duration` FROM `*PREFIX*music_tracks` WHERE `album_id` = ?';
		$result = $this->execute($sql, [$albumId]);
		$row = $result->fetch();
		return (int)$row['duration'];
	}

	/**
	 * @return integer Duration in seconds
	 */
	public function totalDurationByArtist(int $artistId) : int {
		$sql = 'SELECT SUM(`length`) AS `duration` FROM `*PREFIX*music_tracks` WHERE `artist_id` = ?';
		$result = $this->execute($sql, [$artistId]);
		$row = $result->fetch();
		return (int)$row['duration'];
	}

	/**
	 * Get durations of the given tracks.
	 * @param integer[] $trackIds
	 * @return array {int => int} where keys are track IDs and values are corresponding durations
	 */
	public function getDurations(array $trackIds) : array {
		$result = [];

		if (!empty($trackIds)) {
			$sql = 'SELECT `id`, `length` FROM `*PREFIX*music_tracks` WHERE `id` IN ' .
						$this->questionMarks(\count($trackIds));
			$rows = $this->execute($sql, $trackIds)->fetchAll();
			foreach ($rows as $row) {
				$result[$row['id']] = (int)$row['length'];
			}
		}
		return $result;
	}

	/**
	 * @return Track[]
	 */
	public function findAllByNameRecursive(string $name, string $userId, ?int $limit=null, ?int $offset=null) {
		$condition = '( LOWER(`artist`.`name`) LIKE LOWER(?) OR
						LOWER(`album`.`name`) LIKE LOWER(?) OR
						LOWER(`title`) LIKE LOWER(?) )';
		$sql = $this->selectUserEntities($condition, 'ORDER BY LOWER(`title`)');
		$name = BaseMapper::prepareSubstringSearchPattern($name);
		$params = [$userId, $name, $name, $name];
		return $this->findEntities($sql, $params, $limit, $offset);
	}

	/**
	 * Returns all tracks specified by name, artist name, and/or album name
	 * @param string|null $name the name of the track
	 * @param string|null $artistName the name of the artist
	 * @param string $userId the name of the user
	 * @return Track[] Tracks matching the criteria
	 */
	public function findAllByNameArtistOrAlbum(?string $name, ?string $artistName, ?string $albumName, string $userId) : array {
		$sqlConditions = [];
		$params = [$userId];

		if (!empty($name)) {
			$sqlConditions[] = 'LOWER(`title`) = LOWER(?)';
			$params[] = $name;
		}

		if (!empty($artistName)) {
			$sqlConditions[] = 'LOWER(`artist`.`name`) = LOWER(?)';
			$params[] = $artistName;
		}

		if (!empty($albumName)) {
			$sqlConditions[] = 'LOWER(`album`.`name`) = LOWER(?)';
			$params[] = $albumName;
		}

		// at least one condition has to be given, otherwise return an empty set
		if (\count($sqlConditions) > 0) {
			$sql = $this->selectUserEntities(\implode(' AND ', $sqlConditions));
			return $this->findEntities($sql, $params);
		} else {
			return [];
		}
	}

	const FAVORITE_TRACK = 0x1;
	const FAVORITE_ALBUM = 0x2;
	const FAVORITE_ARTIST = 0x4;

	/**
	 * Returns all tracks specified by various criteria, all of which are optional
	 * @param int[] $genres Array of genre IDs
	 * @param int[] $artists Array of artist IDs
	 * @param int|null $fromYear Earliest release year to include
	 * @param int|null $toYear Latest release year to include
	 * @param int|null $favorite Bit mask of FAVORITE_TRACK, FAVORITE_ALBUM, FAVORITE_ARTIST (given favorite types are ORed in the query)
	 * @param int $sortBy Sorting rule as defined in the class SortBy
	 * @param string $userId the name of the user
	 * @return Track[] Tracks matching the criteria
	 */
	public function findAllByCriteria(
			array $genres, array $artists, ?int $fromYear, ?int $toYear, ?int $favorite,
			int $sortBy, bool $invertSort, string $userId, ?int $limit=null, ?int $offset=null) : array {

		$sqlConditions = [];
		$params = [$userId];

		if (!empty($genres)) {
			$sqlConditions[] = '`genre_id` IN ' . $this->questionMarks(\count($genres));
			$params = \array_merge($params, $genres);
		}

		if (!empty($artists)) {
			$sqlConditions[] = '`artist_id` IN ' . $this->questionMarks(\count($artists));
			$params = \array_merge($params, $artists);
		}

		if (!empty($fromYear)) {
			$sqlConditions[] = '`year` >= ?';
			$params[] = $fromYear;
		}

		if (!empty($toYear)) {
			$sqlConditions[] = '`year` <= ?';
			$params[] = $toYear;
		}

		if (!empty($favorite)) {
			$favConds = [];
			if ($favorite & self::FAVORITE_TRACK) {
				$favConds[] = '`*PREFIX*music_tracks`.`starred` IS NOT NULL';
			}
			if ($favorite & self::FAVORITE_ALBUM) {
				$favConds[] = '`album`.`starred` IS NOT NULL';
			}
			if ($favorite & self::FAVORITE_ARTIST) {
				$favConds[] = '`artist`.`starred` IS NOT NULL';
			}
			$sqlConditions[] = '(' . \implode(' OR ', $favConds) . ')';
		} 

		$sql = $this->selectUserEntities(\implode(' AND ', $sqlConditions), $this->formatSortingClause($sortBy, $invertSort));
		return $this->findEntities($sql, $params, $limit, $offset);
	}

	/**
	 * Find most frequently played tracks
	 * @return Track[]
	 */
	public function findFrequentPlay(string $userId, ?int $limit=null, ?int $offset=null) : array {
		$sql = $this->selectUserEntities('`play_count` > 0', 'ORDER BY `play_count` DESC, LOWER(`title`)');
		return $this->findEntities($sql, [$userId], $limit, $offset);
	}

	/**
	 * Find most recently played tracks
	 * @return Track[]
	 */
	public function findRecentPlay(string $userId, ?int $limit=null, ?int $offset=null) : array {
		$sql = $this->selectUserEntities('`last_played` IS NOT NULL', 'ORDER BY `last_played` DESC');
		return $this->findEntities($sql, [$userId], $limit, $offset);
	}

	/**
	 * Find least recently played tracks
	 * @return Track[]
	 */
	public function findNotRecentPlay(string $userId, ?int $limit=null, ?int $offset=null) : array {
		$sql = $this->selectUserEntities(null, 'ORDER BY `last_played` ASC');
		return $this->findEntities($sql, [$userId], $limit, $offset);
	}

	/**
	 * Finds all track IDs of the user along with the parent folder ID of each track
	 * @return array where keys are folder IDs and values are arrays of track IDs
	 */
	public function findTrackAndFolderIds(string $userId) : array {
		$sql = 'SELECT `track`.`id` AS id, `file`.`name` AS `filename`, `file`.`parent` AS `parent`
				FROM `*PREFIX*music_tracks` `track`
				JOIN `*PREFIX*filecache` `file`
				ON `track`.`file_id` = `file`.`fileid`
				WHERE `track`.`user_id` = ?';

		$rows = $this->execute($sql, [$userId])->fetchAll();

		// Sort the results according the file names. This can't be made using ORDERBY in the
		// SQL query because then we couldn't use the "natural order" comparison algorithm
		\usort($rows, fn($a, $b) => \strnatcasecmp($a['filename'], $b['filename']));

		// group the files to parent folder "buckets"
		$result = [];
		foreach ($rows as $row) {
			$result[(int)$row['parent']][] = (int)$row['id'];
		}

		return $result;
	}

	/**
	 * Find names and parents of the file system nodes with given IDs
	 * @param int[] $nodeIds
	 * @return array where keys are the node IDs and values are associative arrays like { 'name' => string, 'parent' => int };
	 */
	public function findNodeNamesAndParents(array $nodeIds) : array {
		$result = [];

		if (!empty($nodeIds)) {
			$sql = 'SELECT `fileid`, `name`, `parent`
					FROM `*PREFIX*filecache` `filecache`
					WHERE `filecache`.`fileid` IN '. $this->questionMarks(\count($nodeIds));

			$rows = $this->execute($sql, $nodeIds)->fetchAll();

			foreach ($rows as $row) {
				$result[$row['fileid']] = [
					'name' => $row['name'],
					'parent' => (int)$row['parent']
				];
			}
		}

		return $result;
	}

	/**
	 * Returns all genre IDs associated with the given artist
	 * @return int[]
	 */
	public function getGenresByArtistId(int $artistId, string $userId) : array {
		$sql = 'SELECT DISTINCT(`genre_id`) FROM `*PREFIX*music_tracks` WHERE
				`genre_id` IS NOT NULL AND `user_id` = ? AND `artist_id` = ?';
		$rows = $this->execute($sql, [$userId, $artistId]);
		return $rows->fetchAll(\PDO::FETCH_COLUMN);
	}

	/**
	 * Returns all tracks IDs of the user, organized by the genre_id.
	 * @return array where keys are genre IDs and values are arrays of track IDs
	 */
	public function mapGenreIdsToTrackIds(string $userId) : array {
		$sql = 'SELECT `id`, `genre_id` FROM `*PREFIX*music_tracks`
				WHERE `genre_id` IS NOT NULL and `user_id` = ?';
		$rows = $this->execute($sql, [$userId])->fetchAll();

		$result = [];
		foreach ($rows as $row) {
			$result[(int)$row['genre_id']][] = (int)$row['id'];
		}

		return $result;
	}

	/**
	 * Returns file IDs of the tracks which do not have genre scanned. This is not the same
	 * thing as unknown genre, which means that the genre has been scanned but was not found
	 * from the track metadata.
	 * @return int[]
	 */
	public function findFilesWithoutScannedGenre(string $userId) : array {
		$sql = 'SELECT `track`.`file_id` FROM `*PREFIX*music_tracks` `track`
				INNER JOIN `*PREFIX*filecache` `file`
				ON `track`.`file_id` = `file`.`fileid`
				WHERE `genre_id` IS NULL and `user_id` = ?';
		$rows = $this->execute($sql, [$userId]);
		return $rows->fetchAll(\PDO::FETCH_COLUMN);
	}

	/**
	 * Update "last played" timestamp and increment the total play count of the track.
	 * The DB row is updated *without* updating the `updated` column.
	 * @return bool true if the track was found and updated, false otherwise
	 */
	public function recordTrackPlayed(int $trackId, string $userId, \DateTime $timeOfPlay) : bool {
		$sql = 'UPDATE `*PREFIX*music_tracks`
				SET `last_played` = ?, `play_count` = `play_count` + 1
				WHERE `user_id` = ? AND `id` = ?';
		$params = [$timeOfPlay->format(BaseMapper::SQL_DATE_FORMAT), $userId, $trackId];
		$result = $this->execute($sql, $params);
		return ($result->rowCount() > 0);
	}

	/**
	 * Marks tracks as dirty, ultimately requesting the user to rescan them
	 * @param int[] $fileIds file IDs of the tracks to mark as dirty
	 * @param string[]|null $userIds the target users; if omitted, the tracks matching the
	 *                      $fileIds are marked for all users
	 * @return int number of rows affected
	 */
	public function markTracksDirty(array $fileIds, ?array $userIds=null) : int {
		$sql = 'UPDATE `*PREFIX*music_tracks`
				SET `dirty` = 1
				WHERE `file_id` IN ' . $this->questionMarks(\count($fileIds));
		$params = $fileIds;

		if (!empty($userIds)) {
			$sql .= ' AND `user_id` IN ' . $this->questionMarks(\count($userIds));
			$params = \array_merge($params, $userIds);
		}

		$result = $this->execute($sql, $params);
		return $result->rowCount();
	}

	/**
	 * Overridden from the base implementation to provide support for table-specific rules
	 *
	 * {@inheritdoc}
	 * @see BaseMapper::advFormatSqlCondition()
	 */
	protected function advFormatSqlCondition(string $rule, string $sqlOp, string $conv) : string {
		// The extra subquery "mysqlhack" seen around some nested queries is needed in order for these to not be insanely slow on MySQL.
		$condForRule = [
			'anywhere'			=> self::formatAdvSearchAnywhereCond($sqlOp, $conv),
			'album'				=> "$conv(`album`.`name`) $sqlOp $conv(?)",
			'artist'			=> "$conv(`artist`.`name`) $sqlOp $conv(?)",
			'album_artist'		=> "`album_id` IN (SELECT `al`.`id` from `*PREFIX*music_albums` `al` JOIN `*PREFIX*music_artists` `ar` ON `al`.`album_artist_id` = `ar`.`id` WHERE $conv(`ar`.`name`) $sqlOp $conv(?))",
			'album_artist_id'	=> "$sqlOp `album_id` IN (SELECT `id` from `*PREFIX*music_albums` WHERE `album_artist_id` = ?)", // our own API extension
			'track'				=> "`number` $sqlOp ?",
			'year'				=> "`year` $sqlOp ?",
			'albumrating'		=> "`album`.`rating` $sqlOp ?",
			'artistrating'		=> "`artist`.`rating` $sqlOp ?",
			'favorite_album'	=> "$conv(`album`.`name`) $sqlOp $conv(?) AND `album`.`starred` IS NOT NULL",
			'favorite_artist'	=> "$conv(`artist`.`name`) $sqlOp $conv(?) AND `artist`.`starred` IS NOT NULL",
			'played_times'		=> "`play_count` $sqlOp ?",
			'last_play'			=> "`last_played` $sqlOp ?",
			'myplayed'			=> "`last_played` $sqlOp", // operator "IS NULL" or "IS NOT NULL"
			'myplayedalbum'		=> "`album_id` IN (SELECT * FROM (SELECT `album_id` from `*PREFIX*music_tracks` GROUP BY `album_id` HAVING MAX(`last_played`) $sqlOp) mysqlhack)", // operator "IS NULL" or "IS NOT NULL"
			'myplayedartist'	=> "`artist_id` IN (SELECT * FROM (SELECT `artist_id` from `*PREFIX*music_tracks` GROUP BY `artist_id` HAVING MAX(`last_played`) $sqlOp) mysqlhack)", // operator "IS NULL" or "IS NOT NULL"
			'time'				=> "`length` $sqlOp ?",
			'bitrate'			=> "`bitrate` $sqlOp ?",
			'song_genre'		=> "$conv(`genre`.`name`) $sqlOp $conv(?)",
			'album_genre'		=> "`album_id` IN (SELECT * FROM (SELECT `album_id` FROM `*PREFIX*music_tracks` `t` JOIN `*PREFIX*music_genres` `g` ON `t`.`genre_id` = `g`.`id` GROUP BY `album_id` HAVING $conv(" . $this->sqlGroupConcat('`g`.`name`') . ") $sqlOp $conv(?)) mysqlhack)",
			'artist_genre'		=> "`artist_id` IN (SELECT * FROM (SELECT `artist_id` FROM `*PREFIX*music_tracks` `t` JOIN `*PREFIX*music_genres` `g` ON `t`.`genre_id` = `g`.`id` GROUP BY `artist_id` HAVING $conv(" . $this->sqlGroupConcat('`g`.`name`') . ") $sqlOp $conv(?)) mysqlhack)",
			'no_genre'			=> ($sqlOp == 'IS NOT NULL') ? '`genre`.`name` = ""' : '`genre`.`name` != ""',
			'playlist'			=> "$sqlOp EXISTS (SELECT 1 from `*PREFIX*music_playlists` `p` WHERE `p`.`id` = ? AND `p`.`track_ids` LIKE " . $this->sqlConcat("'%|'", "`*PREFIX*music_tracks`.`id`", "'|%'") . ')',
			'playlist_name'		=> "EXISTS (SELECT 1 from `*PREFIX*music_playlists` `p` WHERE $conv(`p`.`name`) $sqlOp $conv(?) AND `p`.`track_ids` LIKE " . $this->sqlConcat("'%|'", "`*PREFIX*music_tracks`.`id`", "'|%'") . ')',
			'recent_played'		=> "`*PREFIX*music_tracks`.`id` IN (SELECT * FROM (SELECT `id` FROM `*PREFIX*music_tracks` WHERE `user_id` = ? ORDER BY `last_played` DESC LIMIT $sqlOp) mysqlhack)",
			'file'				=> "$conv(`file`.`name`) $sqlOp $conv(?)",
			'mbid_album'		=> "`album`.`mbid` $sqlOp ?",
			'mbid_artist'		=> "`artist`.`mbid` $sqlOp ?"
		];

		// Add alias rules
		$condForRule['played'] = $condForRule['myplayed'];		// we give no access to other people's data; not part of the API spec but Ample uses this
		$condForRule['genre'] = $condForRule['song_genre'];
		$condForRule['song'] = parent::advFormatSqlCondition('title', $sqlOp, $conv);
		$condForRule['mbid_song'] = parent::advFormatSqlCondition('mbid', $sqlOp, $conv);

		return $condForRule[$rule] ?? parent::advFormatSqlCondition($rule, $sqlOp, $conv);
	}

	private static function formatAdvSearchAnywhereCond(string $sqlOp, string $conv) : string {
		$fields = [
			"`*PREFIX*music_tracks`.`title`",
			"`file`.`name`",
			"`artist`.`name`",
			"`album`.`name`",
			"`genre`.`name`"
		];
		$parts = \array_map(fn($field) => "$conv($field) $sqlOp $conv(?)", $fields);

		$negativeOp = \in_array($sqlOp, ['NOT LIKE', '!=', 'NOT REGEXP']);
		$cond = \implode($negativeOp ? ' AND ' : ' OR ', $parts);

		return "($cond)";
	}
}
