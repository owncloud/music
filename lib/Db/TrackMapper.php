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
 * @copyright Pauli Järvinen 2016 - 2021
 */

namespace OCA\Music\Db;

use OCP\IDBConnection;

/**
 * @phpstan-extends BaseMapper<Track>
 */
class TrackMapper extends BaseMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'music_tracks', Track::class, 'title');
	}

	/**
	 * Override the base implementation to include data from multiple tables
	 *
	 * {@inheritdoc}
	 * @see BaseMapper::selectEntities()
	 */
	protected function selectEntities(string $condition, string $extension=null) : string {
		return "SELECT `*PREFIX*music_tracks`.*, `file`.`name` AS `filename`, `file`.`size`,
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
	 * Overridden from the base implementation to add support for sorting by artist.
	 *
	 * {@inheritdoc}
	 * @see BaseMapper::formatSortingClause()
	 */
	protected function formatSortingClause(int $sortBy) : ?string {
		if ($sortBy === SortBy::Parent) {
			return 'ORDER BY LOWER(`artist_name`), LOWER(`title`)';
		} else {
			return parent::formatSortingClause($sortBy);
		}
	}

	/**
	 * Returns all tracks of the given artist (both album and track artists are considered)
	 * @return Track[]
	 */
	public function findAllByArtist(int $artistId, string $userId, ?int $limit=null, ?int $offset=null) : array {
		$sql = $this->selectUserEntities(
				'`artist_id` = ? OR `album_id` IN (SELECT `id` from `*PREFIX*music_albums` WHERE `album_artist_id` = ?) ',
				'ORDER BY LOWER(`title`)');
		$params = [$userId, $artistId, $artistId];
		return $this->findEntities($sql, $params, $limit, $offset);
	}

	/**
	 * @return Track[]
	 */
	public function findAllByAlbum(int $albumId, string $userId, ?int $artistId=null, ?int $limit=null, ?int $offset=null) : array {
		$condition = '`album_id` = ?';
		$params = [$userId, $albumId];

		if ($artistId !== null) {
			$condition .= ' AND `artist_id` = ? ';
			$params[] = $artistId;
		}

		$sql = $this->selectUserEntities($condition,
				'ORDER BY `*PREFIX*music_tracks`.`disk`, `number`, LOWER(`title`)');
		return $this->findEntities($sql, $params, $limit, $offset);
	}

	/**
	 * @return Track[]
	 */
	public function findAllByFolder(int $folderId, string $userId, ?int $limit=null, ?int $offset=null) : array {
		$sql = $this->selectUserEntities('`file`.`parent` = ?', 'ORDER BY LOWER(`title`)');
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
	 * @param string $userId
	 * @return int[]
	 */
	public function findAllFileIds(string $userId) : array {
		$sql = 'SELECT `file_id` FROM `*PREFIX*music_tracks` WHERE `user_id` = ?';
		$result = $this->execute($sql, [$userId]);

		return \array_map(function ($i) {
			return (int)$i['file_id'];
		}, $result->fetchAll());
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
		$name = '%' . $name . '%';
		$params = [$userId, $name, $name, $name];
		return $this->findEntities($sql, $params, $limit, $offset);
	}

	/**
	 * Returns all tracks specified by name and/or artist name
	 * @param string|null $name the name of the track
	 * @param string|null $artistName the name of the artist
	 * @param bool $fuzzy match names using case-insensitive substring search
	 * @param string $userId the name of the user
	 * @return Track[] Tracks matching the criteria
	 */
	public function findAllByNameAndArtistName(?string $name, ?string $artistName, bool $fuzzy, string $userId) : array {
		$sqlConditions = [];
		$params = [$userId];

		if (!empty($name)) {
			if ($fuzzy) {
				$sqlConditions[] = 'LOWER(`title`) LIKE LOWER(?)';
				$params[] = "%$name%";
			} else {
				$sqlConditions[] = '`title` = ?';
				$params[] = $name;
			}
		}

		if (!empty($artistName)) {
			if ($fuzzy) {
				$sqlConditions[] = 'LOWER(`artist`.`name`) LIKE LOWER(?)';
				$params[] = "%$artistName%";
			} else {
				$sqlConditions[] = '`artist`.`name` = ?';
				$params[] = $artistName;
			}
		}

		// at least one condition has to be given, otherwise return an empty set
		if (\count($sqlConditions) > 0) {
			$sql = $this->selectUserEntities(\implode(' AND ', $sqlConditions));
			return $this->findEntities($sql, $params);
		} else {
			return [];
		}
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
		$sql = 'SELECT `track`.`id` AS id, `file`.`name` AS `filename`, `file`.`parent` AS parent
				FROM `*PREFIX*music_tracks` `track`
				JOIN `*PREFIX*filecache` `file`
				ON `track`.`file_id` = `file`.`fileid`
				WHERE `track`.`user_id` = ?';

		$rows = $this->execute($sql, [$userId])->fetchAll();

		// Sort the results according the file names. This can't be made using ORDERBY in the
		// SQL query because then we couldn't use the "natural order" comparison algorithm
		\usort($rows, function ($a, $b) {
			return \strnatcasecmp($a['filename'], $b['filename']);
		});

		// group the files to parent folder "buckets"
		$result = [];
		foreach ($rows as $row) {
			$result[(int)$row['parent']][] = (int)$row['id'];
		}

		return $result;
	}

	/**
	 * Find names and parents of the file system nodes with given IDs within the given storage
	 * @param int[] $nodeIds
	 * @param string $storageId
	 * @return array where keys are the node IDs and values are associative arrays
	 *         like { 'name' => string, 'parent' => int };
	 */
	public function findNodeNamesAndParents(array $nodeIds, string $storageId) : array {
		$result = [];

		if (!empty($nodeIds)) {
			$sql = 'SELECT `fileid`, `name`, `parent` '.
					'FROM `*PREFIX*filecache` `filecache` '.
					'JOIN `*PREFIX*storages` `storages` '.
					'ON `filecache`.`storage` = `storages`.`numeric_id` '.
					'WHERE `storages`.`id` = ? '.
					'AND `filecache`.`fileid` IN '. $this->questionMarks(\count($nodeIds));

			$rows = $this->execute($sql, \array_merge([$storageId], $nodeIds))->fetchAll();

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
	 * @see \OCA\Music\Db\BaseMapper::findUniqueEntity()
	 * @param Track $track
	 * @return Track
	 */
	protected function findUniqueEntity(Entity $track) : Entity {
		return $this->findByFileId($track->getFileId(), $track->getUserId());
	}
}
