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
use \OCA\Music\AppFramework\Db\DoesNotExistException;
use \OCA\Music\AppFramework\Db\IMapper;
use \OCA\Music\AppFramework\Db\Mapper;

class AlbumMapper extends Mapper implements IMapper {

	public function __construct(Db $db){
		parent::__construct($db, 'music_albums', '\OCA\Music\Db\Album');
	}

	/**
	 * @param string $condition
	 * @return string
	 */
	private function makeSelectQuery($condition=null){
		return 'SELECT `album`.`name`, `album`.`year`, `album`.`id`, '.
			'`album`.`cover_file_id` '.
			'FROM `*PREFIX*music_albums` `album` '.
			'WHERE `album`.`user_id` = ? ' . $condition;
	}

	/**
	 * returns all albums of a user
	 *
	 * @param string $userId the user ID
	 * @return Album[]
	 */
	public function findAll($userId){
		$sql = $this->makeSelectQuery();
		$params = array($userId);
		return $this->findEntities($sql, $params);
	}

	/**
	 * finds an album by ID
	 *
	 * @param integer $albumId ID of the album
	 * @param string $userId the user ID
	 * @return Album
	 */
	public function find($albumId, $userId){
		$sql = $this->makeSelectQuery('AND `album`.`id` = ?');
		$params = array($userId, $albumId);
		return $this->findEntity($sql, $params);
	}

	/**
	 * returns artist IDs mapped to album IDs
	 *
	 * @param integer[] $albumIds IDs of the albums
	 * @return array the artist IDs of an album are accessible
	 * 				by the album ID inside of this array
	 */
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

	/**
	 * returns albums of a specified artist
	 *
	 * @param integer $artistId ID of the artist
	 * @param strig $userId the user ID
	 * @return Album[]
	 */
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

	/**
	 * returns album that matches a name and year
	 *
	 * @param string $albumName name of the album
	 * @param string|integer $albumYear year of the album release
	 * @param strig $userId the user ID
	 * @return Album
	 */
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

	/**
	 * returns album that matches a name, a year and a artist ID
	 *
	 * @param string|null $albumName name of the album
	 * @param string|integer|null $albumYear year of the album release
	 * @param integer|null $artistId ID of the artist
	 * @param string $userId the user ID
	 * @return Album[]
	 */
	public function findAlbum($albumName, $albumYear, $artistId, $userId) {
		$sql = 'SELECT `album`.`name`, `album`.`year`, `album`.`id`, '.
			'`album`.`cover_file_id` '.
			'FROM `*PREFIX*music_albums` `album` '.
			'JOIN `*PREFIX*music_album_artists` `artists` '.
			'ON `album`.`id` = `artists`.`album_id` '.
			'WHERE `album`.`user_id` = ? ';
		$params = array($userId);

		// add artist id check
		if ($artistId === null) {
			$sql .= 'AND `artists`.`artist_id` IS NULL ';
		} else {
			$sql .= 'AND `artists`.`artist_id` = ? ';
			array_push($params, $artistId);
		}

		// add album name check
		if ($albumName === null) {
			$sql .= 'AND `album`.`name` IS NULL ';
		} else {
			$sql .= 'AND `album`.`name` = ? ';
			array_push($params, $albumName);
		}

		// add album year check
		if ($albumYear === null) {
			$sql .= 'AND `album`.`year` IS NULL ';
		} else {
			$sql .= 'AND `album`.`year` = ? ';
			array_push($params, $albumYear);
		}

		return $this->findEntity($sql, $params);
	}

	/**
	 * @param integer $albumId
	 * @param integer $artistId
	 */
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

	/**
	 * @param integer[] $albumIds
	 */
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

	/**
	 * @param integer $coverFileId
	 * @param integer $parentFolderId
	 */
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

	/**
	 * @param integer $coverFileId
	 */
	public function removeCover($coverFileId){
		$sql = 'UPDATE `*PREFIX*music_albums`
				SET `cover_file_id` = NULL
				WHERE `cover_file_id` = ?';
		$params = array($coverFileId);
		$this->execute($sql, $params);
	}

	/**
	 * @return array
	 */
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

	/**
	 * @param integer $albumId
	 * @param integer $parentFolderId
	 */
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

	/**
	 * @param string $userId
	 * @return integer
	 */
	public function count($userId){
		$sql = 'SELECT COUNT(*) AS count FROM `*PREFIX*music_albums` '.
			'WHERE `user_id` = ?';
		$params = array($userId);
		$result = $this->execute($sql, $params);
		$row = $result->fetchRow();
		return $row['count'];
	}

	/**
	 * @param integer $artistId
	 * @param string $userId
	 * @return integer
	 */
	public function countByArtist($artistId, $userId){
		$sql = 'SELECT COUNT(*) AS count '.
			'FROM `*PREFIX*music_albums` `album` '.
			'JOIN `*PREFIX*music_album_artists` `artists` '.
			'ON `album`.`id` = `artists`.`album_id` '.
			'WHERE `album`.`user_id` = ? AND `artists`.`artist_id` = ? ';
		$params = array($userId, $artistId);
		$result = $this->execute($sql, $params);
		$row = $result->fetchRow();
		return $row['count'];
	}

	/**
	 * @param string $name
	 * @param string $userId
	 * @param bool $fuzzy
	 * @return Album[]
	 */
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
