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

use \OCA\AppFramework\Db\Mapper;
use \OCA\AppFramework\Core\API;

use \OCA\AppFramework\Db\DoesNotExistException;

class AlbumMapper extends Mapper {

	public function __construct(API $api){
		parent::__construct($api, 'music_albums');
	}

	private function makeSelectQuery($condition=null){
		return 'SELECT `album`.`name`, `album`.`year`, `album`.`id`, '.
			'`album`.`cover` '.
			'FROM `*PREFIX*music_albums` `album` '.
			'WHERE `album`.`user_id` = ? ' . $condition;
	}

	public function findAll($userId){
		$sql = $this->makeSelectQuery();
		$params = array($userId);
		return $this->findEntities($sql, $params);
	}

	public function find($albumId, $userId){
		$sql = $this->makeSelectQuery('AND `album`.`id` = ?');
		$params = array($userId, $albumId);
		return $this->findEntity($sql, $params);
	}

	public function getAlbumArtistsByAlbumId($albumIds){
		$questionMarks = array();
		for($i = 0; $i < count($albumIds); $i++){
			$questionMarks[] = '?';
		}
		$sql = 'SELECT DISTINCT * FROM `*PREFIX*music_album_artists` `artists`'.
			' WHERE `artists`.`album_id` IN (' . implode(',', $questionMarks) .
			')';
		$result = $this->execute($sql, $albumIds);
		$artists = array();
		while($row = $result->fetchRow()){
			if(!array_key_exists($row['album_id'], $artists)){
				$artists[$row['album_id']] = array();
			}
			$artists[$row['album_id']][] = $row['artist_id'];
		}
		return $artists;
	}

	public function findAllByArtist($artistId, $userId){
		$sql = 'SELECT `album`.`name`, `album`.`year`, `album`.`id`, '.
			'`album`.`cover` '.
			'FROM `*PREFIX*music_albums` `album` '.
			'JOIN `*PREFIX*music_album_artists` `artists` '.
			'ON `album`.`id` = `artists`.`album_id` '.
			'WHERE `album`.`user_id` = ? AND `artists`.`artist_id` = ? ';
		$params = array($userId, $artistId);
		return $this->findEntities($sql, $params);
	}

	public function findByNameAndYear($albumName, $albumYear, $userId){
		$sql = $this->makeSelectQuery('AND `album`.`name` = ? AND `album`.`year` = ?');
		$params = array($userId, $albumName, $albumYear);
		return $this->findEntity($sql, $params);
	}

	public function addAlbumArtistRelationIfNotExist($albumId, $artistId){
		$sql = 'SELECT 1 FROM `*PREFIX*music_album_artists` `relation` '.
			'WHERE `relation`.`album_id` = ? AND `relation`.`artist_id` = ?';
		$params = array($albumId, $artistId);
		try {
			$this->findOneQuery($sql, $params);
			// relation already exists
		} catch(DoesNotExistException $ex){
			$sql = 'INSERT INTO `*PREFIX*music_album_artists` (`album_id`, `artist_id`) '.
				'VALUES (?, ?)';
			$params = array($albumId, $artistId);
			$this->execute($sql, $params);
		}
	}

	public function deleteById($albumIds){
		if(count($albumIds) === 0)
			return;
		$questionMarks = array();
		for($i = 0; $i < count($albumIds); $i++){
			$questionMarks[] = '?';
		}
		$sql = 'DELETE FROM `*PREFIX*music_album_artists` WHERE `album_id` IN ('. implode(',', $questionMarks) . ')';
		$this->execute($sql, $albumIds);
		$sql = 'DELETE FROM `*PREFIX*music_albums` WHERE `id` IN ('. implode(',', $questionMarks) . ')';
		$this->execute($sql, $albumIds);
	}
}
