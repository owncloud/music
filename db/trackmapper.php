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

use \OCA\Music\AppFramework\Core\Db;
use \OCA\Music\AppFramework\Db\IMapper;
use \OCA\Music\AppFramework\Db\Mapper;

class TrackMapper extends Mapper implements IMapper {

	public function __construct(DB $db){
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
	 */
	public function findAll($userId, $limit=null, $offset=null){
		$sql = $this->makeSelectQuery();
		$params = array($userId);
		return $this->findEntities($sql, $params, $limit, $offset);
	}

	/**
	 * @param integer $artistId
	 * @param string $userId
	 */
	public function findAllByArtist($artistId, $userId){
		$sql = $this->makeSelectQuery('AND `track`.`artist_id` = ?');
		$params = array($userId, $artistId);
		return $this->findEntities($sql, $params);
	}

	/**
	 * @param integer $albumId
	 * @param string $userId
	 * @param integer $artistId
	 */
	public function findAllByAlbum($albumId, $userId, $artistId = null){
		$sql = $this->makeSelectQuery('AND `track`.`album_id` = ?');
		$params = array($userId, $albumId);
		if($artistId !== null) {
			$sql .= ' AND `track`.`artist_id` = ?';
			array_push($params, $artistId);
		}
		return $this->findEntities($sql, $params);
	}

	/**
	 * @param integer $id
	 * @param string $userId
	 */
	public function find($id, $userId){
		$sql = $this->makeSelectQuery('AND `track`.`id` = ?');
		$params = array($userId, $id);
		return $this->findEntity($sql, $params);
	}

	/**
	 * @param integer $fileId
	 * @param string $userId
	 */
	public function findByFileId($fileId, $userId){
		$sql = $this->makeSelectQuery('AND `track`.`file_id` = ?');
		$params = array($userId, $fileId);
		return $this->findEntity($sql, $params);
	}

	/**
	 * @param integer $fileId
	 */
	public function findAllByFileId($fileId){
		$sql = $this->makeSelectQueryWithoutUserId('`track`.`file_id` = ?');
		$params = array($fileId);
		return $this->findEntities($sql, $params);
	}

	/**
	 * @param integer $artistId
	 * @param string $userId
	 */
	public function countByArtist($artistId, $userId){
		$sql = 'SELECT COUNT(*) AS count FROM `*PREFIX*music_tracks` `track` '.
			'WHERE `track`.`user_id` = ? AND `track`.`artist_id` = ?';
		$params = array($userId, $artistId);
		$result = $this->execute($sql, $params);
		$row = $result->fetchRow();
		return $row['count'];
	}

	/**
	 * @param integer $albumId
	 * @param string $userId
	 */
	public function countByAlbum($albumId, $userId){
		$sql = 'SELECT COUNT(*) AS count FROM `*PREFIX*music_tracks` `track` '.
			'WHERE `track`.`user_id` = ? AND `track`.`album_id` = ?';
		$params = array($userId, $albumId);
		$result = $this->execute($sql, $params);
		$row = $result->fetchRow();
		return $row['count'];
	}

	/**
	 * @param string $userId
	 */
	public function count($userId){
		$sql = 'SELECT COUNT(*) AS count FROM `*PREFIX*music_tracks` '.
			'WHERE `user_id` = ?';
		$params = array($userId);
		$result = $this->execute($sql, $params);
		$row = $result->fetchRow();
		return $row['count'];
	}

	/**
	 * @param string $name
	 * @param string $userId
	 * @param bool $fuzzy
	 */
	public function findAllByName($name, $userId, $fuzzy = false){
		if ($fuzzy) {
			$condition = 'AND LOWER(`track`.`title`) LIKE LOWER(?) ';
			$name = '%' . $name . '%';
		} else {
			$condition = 'AND `track`.`title` = ? ';
		}
		$sql = $this->makeSelectQuery($condition);
		$params = array($userId, $name);
		return $this->findEntities($sql, $params);
	}

	/**
	 * @param string $name
	 * @param string $userId
	 */
	public function findAllByNameRecursive($name, $userId){
		$condition = ' AND (`track`.`artist_id` IN (SELECT `id` FROM `*PREFIX*music_artists` WHERE LOWER(`name`) LIKE LOWER(?)) OR '.
						' `track`.`album_id` IN (SELECT `id` FROM `*PREFIX*music_albums` WHERE LOWER(`name`) LIKE LOWER(?)) OR '.
						' LOWER(`track`.`title`) LIKE LOWER(?) )';
		$sql = $this->makeSelectQuery($condition);
		$name = '%' . $name . '%';
		$params = array($userId, $name, $name, $name);
		return $this->findEntities($sql, $params);
	}
}
