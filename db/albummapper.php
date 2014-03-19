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

use \OCA\Music\AppFramework\Db\DoesNotExistException;

class AlbumMapper extends Mapper {

	public function __construct(API $api){
		parent::__construct($api, 'music_albums');
	}

	private function makeSelectQuery($condition=null){
		return 'SELECT `album`.`name`, `album`.`year`, `album`.`id`, '.
			'`album`.`cover_file_id` '.
			'FROM `*PREFIX*music_albums` `album` '.
			'WHERE `album`.`user_id` = ? ' . $condition;
	}

	private function makeSelectQueryWithFileInfo($condition=null){
		return 'SELECT `album`.`name`, `album`.`year`, `album`.`id`, '.
				'`album`.`cover_file_id`, `file`.`path` as `coverFilePath` '.
				'FROM `*PREFIX*music_albums` `album` '.
				'LEFT OUTER JOIN `*PREFIX*filecache` `file` '.
				'ON `album`.`cover_file_id` = `file`.`fileid` ' .
				'AND `album`.`user_id` = ? ' . $condition;
	}

	public function findAll($userId){
		$sql = $this->makeSelectQuery();
		$params = array($userId);
		return $this->findEntities($sql, $params);
	}

	public function findAllWithFileInfo($userId){
		$sql = $this->makeSelectQueryWithFileInfo();
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
			'`album`.`cover_file_id` '.
			'FROM `*PREFIX*music_albums` `album` '.
			'JOIN `*PREFIX*music_album_artists` `artists` '.
			'ON `album`.`id` = `artists`.`album_id` '.
			'WHERE `album`.`user_id` = ? AND `artists`.`artist_id` = ? ';
		$params = array($userId, $artistId);
		return $this->findEntities($sql, $params);
	}

	public function findByNameAndYear($albumName, $albumYear, $userId){
		if($albumName === null && $albumYear === null) {
			$params = array($userId);
			$sql = $this->makeSelectQuery('AND `album`.`name` IS NULL AND `album`.`year` IS NULL');
		} else if($albumYear === null) {
			$params = array($userId, $albumName);
			$sql = $this->makeSelectQuery('AND `album`.`name` = ? AND `album`.`year` IS NULL');
		} else if($albumName === null) {
			$params = array($userId, $albumYear);
			$sql = $this->makeSelectQuery('AND `album`.`name` IS NULL AND `album`.`year` = ?');
		} else {
			$params = array($userId, $albumName, $albumYear);
			$sql = $this->makeSelectQuery('AND `album`.`name` = ? AND `album`.`year` = ?');
		}
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

	public function updateCover($coverFileId, $parentFolderId){
		$sql = 'UPDATE `*PREFIX*music_albums`
				SET `cover_file_id` = ?
				WHERE `cover_file_id` IS NULL AND `id` IN (
					SELECT DISTINCT `tracks`.`album_id`
					FROM `*PREFIX*music_tracks` `tracks`
					JOIN `*PREFIX*filecache` `files` ON `tracks`.`file_id` = `files`.`fileid`
					WHERE `files`.`parent` = ?
				)';
		$params = array($coverFileId, $parentFolderId);
		$this->execute($sql, $params);
	}

	public function removeCover($coverFileId){
		$sql = 'UPDATE `*PREFIX*music_albums`
				SET `cover_file_id` = NULL
				WHERE `cover_file_id` = ?';
		$params = array($coverFileId);
		$this->execute($sql, $params);
	}

	public function getAlbumsWithoutCover(){
		$sql = 'SELECT DISTINCT `albums`.`id`, `files`.`parent`
				FROM `*PREFIX*music_albums` `albums`
				JOIN `*PREFIX*music_tracks` `tracks` ON `albums`.`id` = `tracks`.`album_id`
				JOIN `*PREFIX*filecache` `files` ON `tracks`.`file_id` = `files`.`fileid`
				WHERE `albums`.`cover_file_id` IS NULL';
		$result = $this->execute($sql);
		$return = Array();
		while($row = $result->fetchRow()){
			array_push($return, Array('albumId' => $row['id'], 'parentFolderId' => $row['parent']));
		}
		return $return;
	}

	public function findAlbumCover($albumId, $parentFolderId){
		$coverNames = array('cover', 'albumart', 'front', 'folder');
		$imagesSql = 'SELECT `fileid`, `name`
					FROM `*PREFIX*filecache`
					JOIN `*PREFIX*mimetypes` ON `*PREFIX*mimetypes`.`id` = `*PREFIX*filecache`.`mimetype`
					WHERE `parent` = ? AND `*PREFIX*mimetypes`.`mimetype` LIKE \'image%\'';
		$params = array($parentFolderId);
		$result = $this->execute($imagesSql, $params);
		$images = $result->fetchAll();
		$imageId = null;
		if (count($images)) {
			usort($images, function ($imageA, $imageB) use ($coverNames) {
				$nameA = strtolower($imageA['name']);
				$nameB = strtolower($imageB['name']);
				$indexA = PHP_INT_MAX;
				$indexB = PHP_INT_MAX;
				foreach ($coverNames as $i => $coverName) {
					if ($indexA === PHP_INT_MAX && strpos($nameA, $coverName) === 0) {
						$indexA = $i;
					}
					if ($indexB === PHP_INT_MAX && strpos($nameB, $coverName) === 0) {
						$indexB = $i;
					}
					if ($indexA !== PHP_INT_MAX  && $indexB !== PHP_INT_MAX) {
						break;
					}
				}
				return $indexA > $indexB;
			});
			$imageId = $images[0]['fileid'];
		};
		$sql = 'UPDATE `*PREFIX*music_albums`
				SET `cover_file_id` = ? WHERE `id` = ?';
		$params = array($imageId, $albumId);
		$this->execute($sql, $params);
	}

	public function count($userId){
		$sql = 'SELECT COUNT(*) FROM `*PREFIX*music_albums` '.
			'WHERE `user_id` = ?';
		$params = array($userId);
		$result = $this->execute($sql, $params);
		$row = $result->fetchRow();
		return $row['COUNT(*)'];
	}

	public function countByArtist($artistId, $userId){
		$sql = 'SELECT COUNT(*) '.
			'FROM `*PREFIX*music_albums` `album` '.
			'JOIN `*PREFIX*music_album_artists` `artists` '.
			'ON `album`.`id` = `artists`.`album_id` '.
			'WHERE `album`.`user_id` = ? AND `artists`.`artist_id` = ? ';
		$params = array($userId, $artistId);
		$result = $this->execute($sql, $params);
		$row = $result->fetchRow();
		return $row['COUNT(*)'];
	}

	public function findAllByName($name, $userId, $fuzzy = false){
		if ($fuzzy) {
			$condition = 'AND LOWER(`album`.`name`) LIKE LOWER(?) ';
			$name = '%' . $name . '%';
		} else {
			$condition = 'AND `album`.`name` = ? ';
		}
		$sql = $this->makeSelectQuery($condition);
		$params = array($userId, $name);
		return $this->findEntities($sql, $params);
	}
}
