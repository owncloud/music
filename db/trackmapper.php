<?php

/**
 * ownCloud - Music app
 *
 * @author Morris Jobke
 * @copyright 2013 Morris Jobke <morris.jobke@gmail.com>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Music\Db;

use \OCA\Music\AppFramework\Db\Mapper;
use \OCA\Music\Core\API;

class TrackMapper extends Mapper {

	public function __construct(API $api){
		parent::__construct($api, 'music_tracks');
	}

	private function makeSelectQueryWithoutUserId($condition){
		return 'SELECT `track`.`title`, `track`.`number`, `track`.`id`, '.
			'`track`.`artist_id`, `track`.`album_id`, `track`.`length`, '.
			'`track`.`file_id`, `track`.`bitrate`, `track`.`mimetype` '.
			'FROM `*PREFIX*music_tracks` `track` '.
			'WHERE ' . $condition;
	}

	private function makeSelectQuery($condition=null){
		return $this->makeSelectQueryWithoutUserId('`track`.`user_id` = ? ' . $condition);
	}

	public function findAll($userId){
		$sql = $this->makeSelectQuery();
		$params = array($userId);
		return $this->findEntities($sql, $params);
	}

	public function findAllByArtist($artistId, $userId){
		$sql = $this->makeSelectQuery('AND `track`.`artist_id` = ?');
		$params = array($userId, $artistId);
		return $this->findEntities($sql, $params);
	}

	public function findAllByAlbum($albumId, $userId, $artistId = null){
		$sql = $this->makeSelectQuery('AND `track`.`album_id` = ?');
		$params = array($userId, $albumId);
		if($artistId !== null) {
			$sql .= ' AND `track`.`artist_id` = ?';
			array_push($params, $artistId);
		}
		return $this->findEntities($sql, $params);
	}

	public function find($id, $userId){
		$sql = $this->makeSelectQuery('AND `track`.`id` = ?');
		$params = array($userId, $id);
		return $this->findEntity($sql, $params);
	}

	public function findByFileId($fileId, $userId){
		$sql = $this->makeSelectQuery('AND `track`.`file_id` = ?');
		$params = array($userId, $fileId);
		return $this->findEntity($sql, $params);
	}

	public function findAllByFileId($fileId){
		$sql = $this->makeSelectQueryWithoutUserId('`track`.`file_id` = ?');
		$params = array($fileId);
		return $this->findEntities($sql, $params);
	}

	public function countByArtist($artistId, $userId){
		$sql = 'SELECT COUNT(*) FROM `*PREFIX*music_tracks` `track` '.
			'WHERE `track`.`user_id` = ? AND `track`.`artist_id` = ?';
		$params = array($userId, $artistId);
		return $this->findOneQuery($sql, $params);
	}

	public function countByAlbum($albumId, $userId){
		$sql = 'SELECT COUNT(*) FROM `*PREFIX*music_tracks` `track` '.
			'WHERE `track`.`user_id` = ? AND `track`.`album_id` = ?';
		$params = array($userId, $albumId);
		return $this->findOneQuery($sql, $params);
	}

	public function count($userId){
		$sql = 'SELECT COUNT(*) FROM `*PREFIX*music_tracks` '.
			'WHERE `user_id` = ?';
		$params = array($userId);
		$result = $this->execute($sql, $params);
		$row = $result->fetchRow();
		return $row['COUNT(*)'];
	}
}
