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

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDBConnection;

class AlbumMapper extends BaseMapper {

	public function __construct(IDBConnection $db){
		parent::__construct($db, 'music_albums', '\OCA\Music\Db\Album');
	}

	/**
	 * @param string $condition
	 * @return string
	 */
	private function makeSelectQuery($condition=null){
		return 'SELECT `album`.`name`, `album`.`year`, `album`.`disk`, `album`.`id`, '.
			'`album`.`cover_file_id`, `album`.`mbid`, `album`.`disk`, '.
			'`album`.`mbid_group`, `album`.`mbid_group`, '.
			'`album`.`album_artist_id` FROM `*PREFIX*music_albums` `album`'.
			'WHERE `album`.`user_id` = ? ' . $condition;
	}

	/**
	 * returns all albums of a user
	 *
	 * @param string $userId the user ID
	 * @return Album[]
	 */
	public function findAll($userId){
		$sql = $this->makeSelectQuery('ORDER BY LOWER(`album`.`name`)');
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
	 * does not include album_artist_id
	 *
	 * @param integer[] $albumIds IDs of the albums
	 * @return array the artist IDs of an album are accessible
	 * 				by the album ID inside of this array
	 */
	public function getAlbumArtistsByAlbumId($albumIds){
		$sql = 'SELECT DISTINCT `track`.`artist_id`, `track`.`album_id` '.
			'FROM `*PREFIX*music_tracks` `track`'.
			' WHERE `track`.`album_id` IN ' . $this->questionMarks(count($albumIds));
		$result = $this->execute($sql, $albumIds);
		$artists = array();
		while($row = $result->fetch()){
			if(!array_key_exists($row['album_id'], $artists)){
				$artists[$row['album_id']] = array();
			}
			$artists[$row['album_id']][] = $row['artist_id'];
		}
		return $artists;
	}

	/**
	 * returns albums of a specified artist
	 * The artist may be an album_artist or the artist of a track
	 *
	 * @param integer $artistId ID of the artist
	 * @param string $userId the user ID
	 * @return Album[]
	 */
	public function findAllByArtist($artistId, $userId){
		$sql = 'SELECT `album`.`name`, `album`.`year`, `album`.`id`, '.
			'`album`.`cover_file_id`, `album`.`mbid`, `album`.`disk`, '.
			'`album`.`mbid_group`, `album`.`mbid_group`, '.
			'`album`.`album_artist_id` '.
			'FROM `*PREFIX*music_albums` `album` '.
			'WHERE `album`.`id` IN (SELECT DISTINCT `album`.`id` FROM '.
			'`*PREFIX*music_albums` `album` WHERE `album`.`album_artist_id` = ? AND '.
			'`album`.`user_id` = ? UNION SELECT `track`.`album_id` '.
			'FROM `*PREFIX*music_tracks` `track` WHERE `track`.`artist_id` = ? AND '.
			'`track`.`user_id` = ?) ORDER BY LOWER(`album`.`name`)';
		$params = array($artistId, $userId, $artistId, $userId);
		return $this->findEntities($sql, $params);
	}

	/**
	 * returns album that matches a name and year
	 *
	 * @param string $albumName name of the album
	 * @param string|integer $albumYear year of the album release
	 * @param string $userId the user ID
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
	 * returns album that matches a name, a year and an album artist ID
	 *
	 * @param string|null $albumName name of the album
	 * @param string|integer|null $albumYear year of the album release
	 * @param string|integer|null $discNumber disk number of this album's disk
	 * @param integer|null $albumArtistId ID of the album artist
	 * @param string $userId the user ID
	 * @return Album[]
	 */
	public function findAlbum($albumName, $albumYear, $discNumber, $albumArtistId, $userId) {
		$sql = 'SELECT `album`.`name`, `album`.`year`, `album`.`disk`, `album`.`id`, '.
			'`album`.`cover_file_id`, `album`.`mbid`, `album`.`disk`, '.
			'`album`.`mbid_group`, `album`.`mbid_group`, '.
			'`album`.`album_artist_id` '.
			'FROM `*PREFIX*music_albums` `album` '.
			'WHERE `album`.`user_id` = ? ';
		$params = array($userId);

		// add artist id check
		if ($albumArtistId === null) {
			$sql .= 'AND `album`.`album_artist_id` IS NULL ';
		} else {
			$sql .= 'AND `album`.`album_artist_id` = ? ';
			array_push($params, $albumArtistId);
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

		// add disc number check
		if ($discNumber === null) {
			$sql .= 'AND `album`.`disk` IS NULL ';
		} else {
			$sql .= 'AND `album`.`disk` = ? ';
			array_push($params, $discNumber);
		}

		return $this->findEntity($sql, $params);
	}

	/**
	 * @param integer $coverFileId
	 * @param integer $folderId
	 */
	public function updateFolderCover($coverFileId, $folderId){
		$sql = 'UPDATE `*PREFIX*music_albums`
				SET `cover_file_id` = ?
				WHERE `cover_file_id` IS NULL AND `id` IN (
					SELECT DISTINCT `tracks`.`album_id`
					FROM `*PREFIX*music_tracks` `tracks`
					JOIN `*PREFIX*filecache` `files` ON `tracks`.`file_id` = `files`.`fileid`
					WHERE `files`.`parent` = ?
				)';
		$params = array($coverFileId, $folderId);
		$this->execute($sql, $params);
	}

	/**
	 * @param integer $coverFileId
	 * @param integer $albumId
	 */
	public function setCover($coverFileId, $albumId){
		$sql = 'UPDATE `*PREFIX*music_albums`
				SET `cover_file_id` = ?
				WHERE `id` = ?';
		$params = array($coverFileId, $albumId);
		$this->execute($sql, $params);
	}

	/**
	 * @param integer $coverFileId
	 * @return true if the given file was cover for some album
	 */
	public function removeCover($coverFileId){
		$sql = 'UPDATE `*PREFIX*music_albums`
				SET `cover_file_id` = NULL
				WHERE `cover_file_id` = ?';
		$params = array($coverFileId);
		$result = $this->execute($sql, $params);
		return $result->rowCount() > 0;
	}

	/**
	 * @return array of [albumId, parentFolderId] pairs
	 */
	public function getAlbumsWithoutCover(){
		$sql = 'SELECT DISTINCT `albums`.`id`, `files`.`parent`
				FROM `*PREFIX*music_albums` `albums`
				JOIN `*PREFIX*music_tracks` `tracks` ON `albums`.`id` = `tracks`.`album_id`
				JOIN `*PREFIX*filecache` `files` ON `tracks`.`file_id` = `files`.`fileid`
				WHERE `albums`.`cover_file_id` IS NULL';
		$result = $this->execute($sql);
		$return = Array();
		while($row = $result->fetch()){
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
			$this->setCover($imageId, $albumId);
		}
	}

	/**
	 * Returns the count of albums an Artist is featured in
	 * @param integer $artistId
	 * @param string $userId
	 * @return integer
	 */
	public function countByArtist($artistId, $userId){
		$sql = 'SELECT COUNT(*) AS count '.
			'FROM (SELECT DISTINCT `track`.`album_id` FROM '.
			'`*PREFIX*music_tracks` `track` WHERE `track`.`artist_id` = ? '.
			'AND `track`.`user_id` = ? UNION SELECT `album`.`id` FROM '.
			'`*PREFIX*music_albums` `album` WHERE `album`.`album_artist_id` = ? '.
			'AND `album`.`user_id` = ?) tmp';
		$params = array($artistId, $userId, $artistId, $userId);
		$result = $this->execute($sql, $params);
		$row = $result->fetch();
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
		$sql = $this->makeSelectQuery($condition . 'ORDER BY LOWER(`album`.`name`)');
		$params = array($userId, $name);
		return $this->findEntities($sql, $params);
	}
}
