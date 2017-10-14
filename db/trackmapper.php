<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @copyright Morris Jobke 2013, 2014
 */

namespace OCA\Music\Db;

use OCP\IDBConnection;

class TrackMapper extends BaseMapper {

	public function __construct(IDBConnection $db){
		parent::__construct($db, 'music_tracks', '\OCA\Music\Db\Track');
	}

	/**
	 * @param string $condition
	 */
	private function makeSelectQueryWithoutUserId($condition){
		return 'SELECT * FROM `*PREFIX*music_tracks` `track` WHERE ' . $condition;
	}

	/**
	 * @param string $condition
	 */
	private function makeSelectQuery($condition=null){
		return $this->makeSelectQueryWithoutUserId('`track`.`user_id` = ? ' . $condition);
	}

	/**
	 * @param string $userId
	 * @param SortBy $sortBy sort order of the result set
	 * @param integer $limit
	 * @param integer $offset
	 * @return Track[]
	 */
	public function findAll($userId, $sortBy=SortBy::None, $limit=null, $offset=null){
		$sql = $this->makeSelectQuery(
				$sortBy == SortBy::Name ? 'ORDER BY LOWER(`track`.`title`)' : null);
		$params = array($userId);
		return $this->findEntities($sql, $params, $limit, $offset);
	}

	/**
	 * @param integer $artistId
	 * @param string $userId
	 * @return Track[]
	 */
	public function findAllByArtist($artistId, $userId){
		$sql = $this->makeSelectQuery('AND `track`.`artist_id` = ? '.
			'ORDER BY LOWER(`track`.`title`)');
		$params = array($userId, $artistId);
		return $this->findEntities($sql, $params);
	}

	/**
	 * @param integer $albumId
	 * @param string $userId
	 * @param integer $artistId
	 * @return Track[]
	 */
	public function findAllByAlbum($albumId, $userId, $artistId = null){
		$sql = $this->makeSelectQuery('AND `track`.`album_id` = ? ');
		$params = array($userId, $albumId);
		if($artistId !== null) {
			$sql .= 'AND `track`.`artist_id` = ? ';
			array_push($params, $artistId);
		}
		$sql .= 'ORDER BY `track`.`number`, LOWER(`track`.`title`)';
		return $this->findEntities($sql, $params);
	}

	/**
	 * @param string $userId
	 * @return int[]
	 */
	public function findAllFileIds($userId){
		$sql = 'SELECT `file_id` FROM `*PREFIX*music_tracks` WHERE `user_id` = ?';
		$result = $this->execute($sql, [$userId]);

		return array_map(function($i) { return $i['file_id']; }, $result->fetchAll());
	}

	/**
	 * Find a track of user matching a file ID
	 * @param integer $fileId
	 * @param string $userId
	 * @return Track
	 * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
	 */
	public function findByFileId($fileId, $userId){
		$sql = $this->makeSelectQuery('AND `track`.`file_id` = ?');
		$params = array($userId, $fileId);
		return $this->findEntity($sql, $params);
	}

	/**
	 * Find tracks of user with multiple file IDs
	 * @param integer[] $fileIds
	 * @param string[] $userIds
	 * @return Track[]
	 */
	public function findByFileIds($fileIds, $userIds){
		$sql = $this->makeSelectQueryWithoutUserId(
				'`track`.`user_id` IN ' . $this->questionMarks(count($userIds)) .
				' AND `track`.`file_id` IN '. $this->questionMarks(count($fileIds)));
		$params = array_merge($userIds, $fileIds);
		return $this->findEntities($sql, $params);
	}

	/**
	 * Finds tracks of all users matching one or multiple file IDs
	 * @param integer[] $fileIds
	 * @return Track[]
	 */
	public function findAllByFileIds($fileIds){
		$sql = $this->makeSelectQueryWithoutUserId('`track`.`file_id` IN '.
				$this->questionMarks(count($fileIds)));
		return $this->findEntities($sql, $fileIds);
	}

	/**
	 * @param integer $artistId
	 * @return integer
	 */
	public function countByArtist($artistId){
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
	public function countByAlbum($albumId){
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
	 * @return Track[]
	 */
	public function findAllByName($name, $userId, $fuzzy = false){
		if ($fuzzy) {
			$condition = 'AND LOWER(`track`.`title`) LIKE LOWER(?) ';
			$name = '%' . $name . '%';
		} else {
			$condition = 'AND `track`.`title` = ? ';
		}
		$sql = $this->makeSelectQuery($condition . 'ORDER BY LOWER(`track`.`title`)');
		$params = array($userId, $name);
		return $this->findEntities($sql, $params);
	}

	/**
	 * @param string $name
	 * @param string $userId
	 * @return Track[]
	 */
	public function findAllByNameRecursive($name, $userId){
		$condition = ' AND (`track`.`artist_id` IN (SELECT `id` FROM `*PREFIX*music_artists` WHERE LOWER(`name`) LIKE LOWER(?)) OR '.
						' `track`.`album_id` IN (SELECT `id` FROM `*PREFIX*music_albums` WHERE LOWER(`name`) LIKE LOWER(?)) OR '.
						' LOWER(`track`.`title`) LIKE LOWER(?) ) ORDER BY LOWER(`track`.`title`)';
		$sql = $this->makeSelectQuery($condition);
		$name = '%' . $name . '%';
		$params = array($userId, $name, $name, $name);
		return $this->findEntities($sql, $params);
	}

	public function findUniqueEntity(Track $track) {
		return $this->findByFileId($track->getFileId(), $track->getUserId());
	}
}
