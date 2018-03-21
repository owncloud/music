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

class AlbumMapper extends BaseMapper {

	public function __construct(IDBConnection $db){
		parent::__construct($db, 'music_albums', '\OCA\Music\Db\Album');
	}

	/**
	 * @param string $condition
	 * @return string
	 */
	private function makeSelectQuery($condition=null){
		return 'SELECT * FROM `*PREFIX*music_albums` `album`'.
			'WHERE `album`.`user_id` = ? ' . $condition;
	}

	/**
	 * returns all albums of a user
	 *
	 * @param string $userId the user ID
	 * @param SortBy $sortBy sort order of the result set
	 * @param integer $limit
	 * @param integer $offset
	 * @return Album[]
	 */
	public function findAll($userId, $sortBy=SortBy::None, $limit=null, $offset=null){
		$sql = $this->makeSelectQuery(
				$sortBy == SortBy::Name ? 'ORDER BY LOWER(`album`.`name`)' : null);
		$params = array($userId);
		return $this->findEntities($sql, $params, $limit, $offset);
	}

	/**
	 * returns artist IDs mapped to album IDs
	 * does not include album_artist_id
	 *
	 * @param integer[] $albumIds IDs of the albums
	 * @return array the artist IDs of an album are accessible by the album ID inside of this array
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
	 * returns release years mapped to album IDs
	 *
	 * @param integer[] $albumIds IDs of the albums
	 * @return array the years of an album are accessible by the album ID inside of this array
	 */
	public function getYearsByAlbumId($albumIds){
		$sql = 'SELECT DISTINCT `track`.`year`, `track`.`album_id` '.
				'FROM `*PREFIX*music_tracks` `track` '.
				'WHERE `track`.`year` IS NOT NULL '.
				'AND `track`.`album_id` IN ' . $this->questionMarks(count($albumIds));
		$result = $this->execute($sql, $albumIds);
		$yearsByAlbum = array();
		while($row = $result->fetch()){
			if(!array_key_exists($row['album_id'], $yearsByAlbum)){
				$yearsByAlbum[$row['album_id']] = array();
			}
			$yearsByAlbum[$row['album_id']][] = $row['year'];
		}
		return $yearsByAlbum;
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
		$sql = 'SELECT * FROM `*PREFIX*music_albums` `album` '.
			'WHERE `album`.`id` IN (SELECT DISTINCT `album`.`id` FROM '.
			'`*PREFIX*music_albums` `album` WHERE `album`.`album_artist_id` = ? AND '.
			'`album`.`user_id` = ? UNION SELECT `track`.`album_id` '.
			'FROM `*PREFIX*music_tracks` `track` WHERE `track`.`artist_id` = ? AND '.
			'`track`.`user_id` = ?) ORDER BY LOWER(`album`.`name`)';
		$params = array($artistId, $userId, $artistId, $userId);
		return $this->findEntities($sql, $params);
	}

	/**
	 * returns album that matches a name, a disc number and an album artist ID
	 *
	 * @param string|null $albumName name of the album
	 * @param string|integer|null $discNumber disk number of this album's disk
	 * @param integer|null $albumArtistId ID of the album artist
	 * @param string $userId the user ID
	 * @return Album[]
	 */
	public function findAlbum($albumName, $discNumber, $albumArtistId, $userId) {
		$sql = 'SELECT * FROM `*PREFIX*music_albums` `album` '.
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
	 * @return true if one or more albums were influenced
	 */
	public function updateFolderCover($coverFileId, $folderId){
		$sql = 'SELECT DISTINCT `tracks`.`album_id`
				FROM `*PREFIX*music_tracks` `tracks`
				JOIN `*PREFIX*filecache` `files` ON `tracks`.`file_id` = `files`.`fileid`
				WHERE `files`.`parent` = ?';
		$params = array($folderId);
		$result = $this->execute($sql, $params);

		$updated = false;
		if ($result->rowCount()){
			$sql = 'UPDATE `*PREFIX*music_albums`
					SET `cover_file_id` = ?
					WHERE `cover_file_id` IS NULL AND `id` IN (?)';
			$params = array($coverFileId, join(",", $result->fetchAll(\PDO::FETCH_COLUMN)));
			$result = $this->execute($sql, $params);
			$updated = $result->rowCount() > 0;
		}

		return $updated;
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
	 * @param integer[] $coverFileIds
	 * @param string[]|null $userIds the users whose music library is targeted; all users are targeted if omitted
	 * @return Album[] albums which got modified, empty array if none
	 */
	public function removeCovers($coverFileIds, $userIds=null){
		// find albums using the given file as cover
		$sql = 'SELECT `id`, `user_id` FROM `*PREFIX*music_albums` WHERE `cover_file_id` IN ' .
			$this->questionMarks(count($coverFileIds));
		$params = $coverFileIds;
		if ($userIds !== null) {
			$sql .= ' AND `user_id` IN ' . $this->questionMarks(count($userIds));
			$params = array_merge($params, $userIds);
		}
		$albums = $this->findEntities($sql, $params);

		// if any albums found, remove the cover from those
		$count = count($albums);
		if ($count) {
			$sql = 'UPDATE `*PREFIX*music_albums`
				SET `cover_file_id` = NULL
				WHERE `id` IN ' . $this->questionMarks($count);
			$params = array_map(function($a) { return $a->getId(); }, $albums);
			$this->execute($sql, $params);
		}

		return $albums;
	}

	/**
	 * @return array of dictionaries with keys [albumId, userId, parentFolderId]
	 */
	public function getAlbumsWithoutCover(){
		$sql = 'SELECT DISTINCT `albums`.`id`, `albums`.`user_id`, `files`.`parent`
				FROM `*PREFIX*music_albums` `albums`
				JOIN `*PREFIX*music_tracks` `tracks` ON `albums`.`id` = `tracks`.`album_id`
				JOIN `*PREFIX*filecache` `files` ON `tracks`.`file_id` = `files`.`fileid`
				WHERE `albums`.`cover_file_id` IS NULL';
		$result = $this->execute($sql);
		$return = Array();
		while($row = $result->fetch()){
			$return[] = [
				'albumId' => $row['id'],
				'userId' => $row['user_id'],
				'parentFolderId' => $row['parent']
			];
		}
		return $return;
	}

	/**
	 * @param integer $albumId
	 * @param integer $parentFolderId
	 * @return true if a cover image was found and added for the album
	 */
	public function findAlbumCover($albumId, $parentFolderId){
		$return = false;
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
			$return = true;
		}
		return $return;
	}

	/**
	 * Returns the count of albums an Artist is featured in
	 * @param integer $artistId
	 * @return integer
	 */
	public function countByArtist($artistId){
		$sql = 'SELECT COUNT(*) AS count FROM '.
			'(SELECT DISTINCT `track`.`album_id` FROM '.
			'`*PREFIX*music_tracks` `track` WHERE `track`.`artist_id` = ? '.
			'UNION SELECT `album`.`id` FROM '.
			'`*PREFIX*music_albums` `album` WHERE `album`.`album_artist_id` = ?) tmp';
		$params = array($artistId, $artistId);
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

	public function findUniqueEntity(Album $album){
		return $this->findEntity(
				'SELECT * FROM `*PREFIX*music_albums` WHERE `user_id` = ? AND `hash` = ?',
				[$album->getUserId(), $album->getHash()]
		);
	}
}
