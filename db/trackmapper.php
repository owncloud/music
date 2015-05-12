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

use OCP\AppFramework\Db\Mapper;
use OCP\IDb;

class TrackMapper extends Mapper {

	public function __construct(IDb $db){
		parent::__construct($db, 'music_tracks', '\OCA\Music\Db\Track');
	}

	/**
	 * @param string $condition
	 */
	private function makeSelectQueryWithoutUserId($condition){
		return 'SELECT `track`.`title`, `track`.`number`, `track`.`id`, '.
			'`track`.`artist_id`, `track`.`album_id`, `track`.`length`, '.
			'`track`.`file_id`, `track`.`bitrate`, `track`.`mimetype` '.
			'FROM `*PREFIX*music_tracks` `track` '.
			'WHERE ' . $condition;
	}

	/**
	 * @param string $condition
	 */
	private function makeSelectQuery($condition=null){
		return $this->makeSelectQueryWithoutUserId('`track`.`user_id` = ? ' . $condition);
	}

	/**
	 * @param string $userId
	 * @param integer $limit
	 * @param integer $offset
	 * @return Track[]
	 */
	public function findAll($userId, $limit=null, $offset=null){
		$sql = $this->makeSelectQuery('ORDER BY `track`.`title`');
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
			'ORDER BY `track`.`title`');
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
		$sql .= 'ORDER BY `track`.`title`';
		return $this->findEntities($sql, $params);
	}

	/**
	 * @param integer $id
	 * @param string $userId
	 * @return Track
	 */
	public function find($id, $userId){
		$sql = $this->makeSelectQuery('AND `track`.`id` = ?');
		$params = array($userId, $id);
		return $this->findEntity($sql, $params);
	}

	/**
	 * @param integer $fileId
	 * @param string $userId
	 * @return Track
	 */
	public function findByFileId($fileId, $userId){
		$sql = $this->makeSelectQuery('AND `track`.`file_id` = ?');
		$params = array($userId, $fileId);
		return $this->findEntity($sql, $params);
	}

	/**
	 * @param integer $fileId
	 * @return Track[]
	 */
	public function findAllByFileId($fileId){
		$sql = $this->makeSelectQueryWithoutUserId('`track`.`file_id` = ? '.
			'ORDER BY `track`.`title`');
		$params = array($fileId);
		return $this->findEntities($sql, $params);
	}

	/**
	 * @param integer $artistId
	 * @param string $userId
	 * @return integer
	 */
	public function countByArtist($artistId, $userId){
		$sql = 'SELECT COUNT(*) AS count FROM `*PREFIX*music_tracks` `track` '.
			'WHERE `track`.`user_id` = ? AND `track`.`artist_id` = ?';
		$params = array($userId, $artistId);
		$result = $this->execute($sql, $params);
		$row = $result->fetch();
		return $row['count'];
	}

	/**
	 * @param integer $albumId
	 * @param string $userId
	 * @return integer
	 */
	public function countByAlbum($albumId, $userId){
		$sql = 'SELECT COUNT(*) AS count FROM `*PREFIX*music_tracks` `track` '.
			'WHERE `track`.`user_id` = ? AND `track`.`album_id` = ?';
		$params = array($userId, $albumId);
		$result = $this->execute($sql, $params);
		$row = $result->fetch();
		return $row['count'];
	}

	/**
	 * @param string $userId
	 * @return integer
	 */
	public function count($userId){
		$sql = 'SELECT COUNT(*) AS count FROM `*PREFIX*music_tracks` '.
			'WHERE `user_id` = ?';
		$params = array($userId);
		$result = $this->execute($sql, $params);
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
		$sql = $this->makeSelectQuery($condition . 'ORDER BY `track`.`title`');
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
						' LOWER(`track`.`title`) LIKE LOWER(?) ) ORDER BY `track`.`title`';
		$sql = $this->makeSelectQuery($condition);
		$name = '%' . $name . '%';
		$params = array($userId, $name, $name, $name);
		return $this->findEntities($sql, $params);
	}
}
