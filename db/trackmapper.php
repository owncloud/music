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

class TrackMapper extends BaseMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'music_tracks', '\OCA\Music\Db\Track');
	}

	/**
	 * @param string $condition
	 */
	private function makeSelectQueryWithoutUserId($condition) {
		return 'SELECT `track`.*, `file`.`name` AS `filename`, `file`.`size` '.
				'FROM `*PREFIX*music_tracks` `track` '.
				'INNER JOIN `*PREFIX*filecache` `file` '.
				'ON `track`.`file_id` = `file`.`fileid` '.
				'WHERE ' . $condition;
	}

	/**
	 * @param string $condition
	 */
	private function makeSelectQuery($condition=null) {
		return $this->makeSelectQueryWithoutUserId('`track`.`user_id` = ? ' . $condition);
	}

	/**
	 * Find a single entity by id and user_id
	 * This has to be overridden from BaseMapper because we utilize also *PREFIX*filecache table.
	 * @param integer $id
	 * @param string $userId
	 * @throws DoesNotExistException if the entity does not exist
	 * @throws MultipleObjectsReturnedException if more than one entity exists
	 * @return Track
	 */
	public function find($id, $userId) {
		$sql = $this->makeSelectQuery('AND `track`.`id` = ?');
		return $this->findEntity($sql, [$userId, $id]);
	}

	/**
	 * Find all entities matching the given IDs. Specifying the owning user is optional.
	 * This has to be overridden from BaseMapper because we utilize also *PREFIX*filecache table.
	 * @param integer[] $ids  IDs of the entities to be found
	 * @param string|null $userId
	 * @return Entity[]
	 */
	public function findById($ids, $userId=null) {
		$count = \count($ids);
		$sql = $this->makeSelectQueryWithoutUserId('`track`.`id` IN '. $this->questionMarks($count));
		if (!empty($userId)) {
			$sql .= ' AND `user_id` = ?';
			$ids[] = $userId;
		}
		return $this->findEntities($sql, $ids);
	}

	/**
	 * @param string $userId
	 * @param integer $sortBy sort order of the result set
	 * @param integer $limit
	 * @param integer $offset
	 * @return Track[]
	 */
	public function findAll($userId, $sortBy=SortBy::None, $limit=null, $offset=null) {
		$sql = $this->makeSelectQuery(
				$sortBy == SortBy::Name ? 'ORDER BY LOWER(`track`.`title`)' : null);
		$params = [$userId];
		return $this->findEntities($sql, $params, $limit, $offset);
	}

	/**
	 * @param integer $artistId
	 * @param string $userId
	 * @return Track[]
	 */
	public function findAllByArtist($artistId, $userId) {
		$sql = $this->makeSelectQuery('AND `track`.`artist_id` = ? '.
			'ORDER BY LOWER(`track`.`title`)');
		$params = [$userId, $artistId];
		return $this->findEntities($sql, $params);
	}

	/**
	 * @param integer $albumId
	 * @param string $userId
	 * @param integer $artistId
	 * @return Track[]
	 */
	public function findAllByAlbum($albumId, $userId, $artistId = null) {
		$sql = $this->makeSelectQuery('AND `track`.`album_id` = ? ');
		$params = [$userId, $albumId];
		if ($artistId !== null) {
			$sql .= 'AND `track`.`artist_id` = ? ';
			\array_push($params, $artistId);
		}
		$sql .= 'ORDER BY `track`.`disk`, `track`.`number`, LOWER(`track`.`title`)';
		return $this->findEntities($sql, $params);
	}

	/**
	 * @param integer $folderId
	 * @param string $userId
	 * @return Track[]
	 */
	public function findAllByFolder($folderId, $userId) {
		$sql = $this->makeSelectQuery('AND `file`.`parent` = ? ');
		$params = [$userId, $folderId];
		$sql .= 'ORDER BY LOWER(`track`.`title`)';
		return $this->findEntities($sql, $params);
	}

	/**
	 * @param string $userId
	 * @return int[]
	 */
	public function findAllFileIds($userId) {
		$sql = 'SELECT `file_id` FROM `*PREFIX*music_tracks` WHERE `user_id` = ?';
		$result = $this->execute($sql, [$userId]);

		return \array_map(function ($i) {
			return $i['file_id'];
		}, $result->fetchAll());
	}

	/**
	 * Find a track of user matching a file ID
	 * @param integer $fileId
	 * @param string $userId
	 * @return Track
	 * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
	 */
	public function findByFileId($fileId, $userId) {
		$sql = $this->makeSelectQuery('AND `track`.`file_id` = ?');
		$params = [$userId, $fileId];
		return $this->findEntity($sql, $params);
	}

	/**
	 * Find tracks of user with multiple file IDs
	 * @param integer[] $fileIds
	 * @param string[] $userIds
	 * @return Track[]
	 */
	public function findByFileIds($fileIds, $userIds) {
		$sql = $this->makeSelectQueryWithoutUserId(
				'`track`.`user_id` IN ' . $this->questionMarks(\count($userIds)) .
				' AND `track`.`file_id` IN '. $this->questionMarks(\count($fileIds)));
		$params = \array_merge($userIds, $fileIds);
		return $this->findEntities($sql, $params);
	}

	/**
	 * Finds tracks of all users matching one or multiple file IDs
	 * @param integer[] $fileIds
	 * @return Track[]
	 */
	public function findAllByFileIds($fileIds) {
		$sql = $this->makeSelectQueryWithoutUserId('`track`.`file_id` IN '.
				$this->questionMarks(\count($fileIds)));
		return $this->findEntities($sql, $fileIds);
	}

	/**
	 * @param integer $artistId
	 * @return integer
	 */
	public function countByArtist($artistId) {
		$sql = 'SELECT COUNT(*) AS count FROM `*PREFIX*music_tracks` `track` '.
			'WHERE `track`.`artist_id` = ?';
		$result = $this->execute($sql, [$artistId]);
		$row = $result->fetch();
		return $row['count'];
	}

	/**
	 * @param integer $albumId
	 * @return integer
	 */
	public function countByAlbum($albumId) {
		$sql = 'SELECT COUNT(*) AS count FROM `*PREFIX*music_tracks` `track` '.
			'WHERE `track`.`album_id` = ?';
		$result = $this->execute($sql, [$albumId]);
		$row = $result->fetch();
		return $row['count'];
	}

	/**
	 * @param string $name
	 * @param string $userId
	 * @param bool $fuzzy
	 * @param integer $limit
	 * @param integer $offset
	 * @return Track[]
	 */
	public function findAllByName($name, $userId, $fuzzy = false, $limit=null, $offset=null) {
		if ($fuzzy) {
			$condition = 'AND LOWER(`track`.`title`) LIKE LOWER(?) ';
			$name = '%' . $name . '%';
		} else {
			$condition = 'AND `track`.`title` = ? ';
		}
		$sql = $this->makeSelectQuery($condition . 'ORDER BY LOWER(`track`.`title`)');
		$params = [$userId, $name];
		return $this->findEntities($sql, $params, $limit, $offset);
	}

	/**
	 * @param string $name
	 * @param string $userId
	 * @return Track[]
	 */
	public function findAllByNameRecursive($name, $userId) {
		$condition = ' AND (`track`.`artist_id` IN (SELECT `id` FROM `*PREFIX*music_artists` WHERE LOWER(`name`) LIKE LOWER(?)) OR '.
						' `track`.`album_id` IN (SELECT `id` FROM `*PREFIX*music_albums` WHERE LOWER(`name`) LIKE LOWER(?)) OR '.
						' LOWER(`track`.`title`) LIKE LOWER(?) ) ORDER BY LOWER(`track`.`title`)';
		$sql = $this->makeSelectQuery($condition);
		$name = '%' . $name . '%';
		$params = [$userId, $name, $name, $name];
		return $this->findEntities($sql, $params);
	}

	/**
	 * Returns track specified by name and/or artist name
	 * @param string|null $name the name of the track
	 * @param string|null $artistName the name of the artist
	 * @param string $userId the name of the user
	 * @return \OCA\Music\Db\Track|null Mathing track if the criteria uniquely defines one
	 */
	public function findByNameAndArtistName($name, $artistName, $userId) {
		$sqlConditions = '';
		$params = [$userId];

		if (!empty($name)) {
			$sqlConditions .= 'AND LOWER(`track`.`title`) LIKE LOWER(?) ';
			$params[] = $name;
		}

		if (!empty($artistName)) {
			$sqlConditions .= 'AND `track`.`artist_id` IN (SELECT `id` FROM `*PREFIX*music_artists` WHERE LOWER(`name`) LIKE LOWER(?))';
			$params[] = $artistName;
		}

		$sql = $this->makeSelectQuery($sqlConditions);
		return $this->findEntity($sql, $params);
	}

	/**
	 * Finds all track IDs of the user along with the parent folder ID of each track
	 * @param string $userId
	 * @return array where keys are folder IDs and values are arrays of track IDs
	 */
	public function findTrackAndFolderIds($userId) {
		$sql = 'SELECT `track`.`id` AS id, `file`.`parent` AS parent '.
				'FROM `*PREFIX*music_tracks` `track` '.
				'JOIN `*PREFIX*filecache` `file` '.
				'ON `track`.`file_id` = `file`.`fileid` '.
				'WHERE `track`.`user_id` = ?';

		$rows = $this->execute($sql, [$userId])->fetchAll();

		$result = [];
		foreach ($rows as $row) {
			$result[$row['parent']][] = $row['id'];
		}

		return $result;
	}

	/**
	 * Find names and paths of the file system nodes with given IDs within the given storage
	 * @param int[] $nodeIds
	 * @param string $storageId
	 * @return array where keys are the node IDs and values are associative arrays
	 *         like { 'name' => string, 'path' => string };
	 */
	public function findNodeNamesAndPaths($nodeIds, $storageId) {
		$result = [];

		if (!empty($nodeIds)) {
			$sql = 'SELECT `fileid`, `name`, `path` '.
					'FROM `*PREFIX*filecache` `filecache` '.
					'JOIN `*PREFIX*storages` `storages` '.
					'ON `filecache`.`storage` = `storages`.`numeric_id` '.
					'WHERE `storages`.`id` = ? '.
					'AND `filecache`.`fileid` IN '. $this->questionMarks(\count($nodeIds));

			$rows = $this->execute($sql, \array_merge([$storageId], $nodeIds))->fetchAll();

			foreach ($rows as $row) {
				$result[$row['fileid']] = [
					'name' => $row['name'],
					'path' => $row['path']
				];
			}
		}

		return $result;
	}

	public function findUniqueEntity(Track $track) {
		return $this->findByFileId($track->getFileId(), $track->getUserId());
	}
}
