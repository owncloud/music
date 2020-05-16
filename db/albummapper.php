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

use \OCA\Music\Utility\Util;

use OCP\IDBConnection;

class AlbumMapper extends BaseMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'music_albums', '\OCA\Music\Db\Album', 'name');
	}

	/**
	 * returns artist IDs mapped to album IDs
	 * does not include album_artist_id
	 *
	 * @param integer[]|null $albumIds IDs of the albums; get all albums of the user if null given
	 * @param string $userId the user ID
	 * @return array int => int[], keys are albums IDs and values are arrays of artist IDs
	 */
	public function getPerformingArtistsByAlbumId($albumIds, $userId) {
		$sql = 'SELECT DISTINCT `track`.`album_id`, `track`.`artist_id`
				FROM `*PREFIX*music_tracks` `track`
				WHERE `track`.`user_id` = ? ';
		$params = [$userId];

		if ($albumIds !== null) {
			$sql .= 'AND `track`.`album_id` IN ' . $this->questionMarks(\count($albumIds));
			$params = \array_merge($params, $albumIds);
		}

		$result = $this->execute($sql, $params);
		return $result->fetchAll(\PDO::FETCH_COLUMN|\PDO::FETCH_GROUP);
	}

	/**
	 * returns release years mapped to album IDs
	 *
	 * @param integer[]|null $albumIds IDs of the albums; get all albums of the user if null given
	 * @param string $userId the user ID
	 * @return array int => int[], keys are albums IDs and values are arrays of years
	 */
	public function getYearsByAlbumId($albumIds, $userId) {
		$sql = 'SELECT DISTINCT `track`.`album_id`, `track`.`year`
				FROM `*PREFIX*music_tracks` `track`
				WHERE `track`.`user_id` = ?
				AND `track`.`year` IS NOT NULL ';
		$params = [$userId];

		if ($albumIds !== null) {
			$sql .= 'AND `track`.`album_id` IN ' . $this->questionMarks(\count($albumIds));
			$params = \array_merge($params, $albumIds);
		}

		$result = $this->execute($sql, $params);
		return $result->fetchAll(\PDO::FETCH_COLUMN|\PDO::FETCH_GROUP);
	}

	/**
	 * returns genres mapped to album IDs
	 *
	 * @param integer[]|null $albumIds IDs of the albums; get all albums of the user if null given
	 * @param string $userId the user ID
	 * @return array int => string[], keys are albums IDs and values are arrays of genres
	 */
	public function getGenresByAlbumId($albumIds, $userId) {
		$sql = 'SELECT DISTINCT `album_id`, `genre_id`
				FROM `*PREFIX*music_tracks`
				WHERE `user_id` = ?
				AND `genre_id` IS NOT NULL ';
		$params = [$userId];

		if ($albumIds !== null) {
			$sql .= 'AND `album_id` IN ' . $this->questionMarks(\count($albumIds));
			$params = \array_merge($params, $albumIds);
		}

		$result = $this->execute($sql, $params);
		return $result->fetchAll(\PDO::FETCH_COLUMN|\PDO::FETCH_GROUP);
	}

	/**
	 * returns number of disks per album ID
	 *
	 * @param integer[]|null $albumIds IDs of the albums; get all albums of the user if null given
	 * @param string $userId the user ID
	 * @return array int => int, keys are albums IDs and values are disk counts
	 */
	public function getDiscCountByAlbumId($albumIds, $userId) {
		$sql = 'SELECT `album_id`, MAX(`disk`) AS `disc_count`
				FROM `*PREFIX*music_tracks`
				WHERE `user_id` = ?
				GROUP BY `album_id` ';
		$params = [$userId];

		if ($albumIds !== null) {
			$sql .= 'HAVING `album_id` IN ' . $this->questionMarks(\count($albumIds));
			$params = \array_merge($params, $albumIds);
		}

		$result = $this->execute($sql, $params);
		$diskCountByAlbum = [];
		while ($row = $result->fetch()) {
			$diskCountByAlbum[$row['album_id']] = $row['disc_count'];
		}
		return $diskCountByAlbum;
	}

	/**
	 * Find all user's entities
	 *
	 * Overridden from \OCA\Music\Db\BaseMapper to add support for sorting by artist.
	 *
	 * @param string $userId
	 * @param integer $sortBy sort order of the result set
	 * @param integer|null $limit
	 * @param integer|null $offset
	 * @return Entity[]
	 */
	public function findAll($userId, $sortBy=SortBy::None, $limit=null, $offset=null) {
		if ($sortBy === SortBy::Parent) {
			$sql = 'SELECT `album`.* FROM `*PREFIX*music_albums` `album`
					INNER JOIN `*PREFIX*music_artists` `artist`
					ON `album`.`album_artist_id` = `artist`.`id`
					WHERE `album`.`user_id` = ?
					ORDER BY LOWER(`artist`.`name`)';
			$params = [$userId];
			return $this->findEntities($sql, $params, $limit, $offset);
		} else {
			return parent::findAll($userId, $sortBy, $limit, $offset);
		}
	}

	/**
	 * returns albums of a specified artist
	 * The artist may be an album_artist or the artist of a track
	 *
	 * @param integer $artistId ID of the artist
	 * @param string $userId the user ID
	 * @return Album[]
	 */
	public function findAllByArtist($artistId, $userId) {
		$sql = 'SELECT * FROM `*PREFIX*music_albums` `album` '.
			'WHERE `album`.`id` IN (SELECT DISTINCT `album`.`id` FROM '.
			'`*PREFIX*music_albums` `album` WHERE `album`.`album_artist_id` = ? AND '.
			'`album`.`user_id` = ? UNION SELECT `track`.`album_id` '.
			'FROM `*PREFIX*music_tracks` `track` WHERE `track`.`artist_id` = ? AND '.
			'`track`.`user_id` = ?) ORDER BY LOWER(`album`.`name`)';
		$params = [$artistId, $userId, $artistId, $userId];
		return $this->findEntities($sql, $params);
	}

	/**
	 * returns albums of a specified artist
	 * The artist must album_artist on the album, artists of individual tracks are not considered
	 *
	 * @param integer $artistId ID of the artist
	 * @param string $userId the user ID
	 * @return Album[]
	 */
	public function findAllByAlbumArtist($artistId, $userId) {
		$sql = $this->selectUserEntities('`album_artist_id` = ?');
		$params = [$userId, $artistId];
		return $this->findEntities($sql, $params);
	}

	/**
	 * @param int $genreId
	 * @param string $userId
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return Artist[]
	 */
	public function findAllByGenre($genreId, $userId, $limit=null, $offset=null) {
		$sql = $this->selectUserEntities('EXISTS '.
				'(SELECT 1 FROM `*PREFIX*music_tracks` `track`
				  WHERE `*PREFIX*music_albums`.`id` = `track`.`album_id`
				  AND `track`.`genre_id` = ?)');

		$params = [$userId, $genreId];
		return $this->findEntities($sql, $params, $limit, $offset);
	}

	/**
	 * returns album that matches a name and an album artist ID
	 *
	 * @param string|null $albumName name of the album
	 * @param integer|null $albumArtistId ID of the album artist
	 * @param string $userId the user ID
	 * @return Album[]
	 */
	public function findAlbum($albumName, $albumArtistId, $userId) {
		$sql = $this->selectUserEntities();
		$params = [$userId];

		// add artist id check
		if ($albumArtistId === null) {
			$sql .= ' AND `album_artist_id` IS NULL ';
		} else {
			$sql .= ' AND `album_artist_id` = ? ';
			\array_push($params, $albumArtistId);
		}

		// add album name check
		if ($albumName === null) {
			$sql .= ' AND `name` IS NULL ';
		} else {
			$sql .= ' AND `name` = ? ';
			\array_push($params, $albumName);
		}

		return $this->findEntity($sql, $params);
	}

	/**
	 * @param integer $coverFileId
	 * @param integer $folderId
	 * @return boolean True if one or more albums were influenced
	 */
	public function updateFolderCover($coverFileId, $folderId) {
		$sql = 'SELECT DISTINCT `tracks`.`album_id`
				FROM `*PREFIX*music_tracks` `tracks`
				JOIN `*PREFIX*filecache` `files` ON `tracks`.`file_id` = `files`.`fileid`
				WHERE `files`.`parent` = ?';
		$params = [$folderId];
		$result = $this->execute($sql, $params);

		$updated = false;
		if ($result->rowCount()) {
			$sql = 'UPDATE `*PREFIX*music_albums`
					SET `cover_file_id` = ?
					WHERE `cover_file_id` IS NULL AND `id` IN (?)';
			$params = [$coverFileId, \join(",", $result->fetchAll(\PDO::FETCH_COLUMN))];
			$result = $this->execute($sql, $params);
			$updated = $result->rowCount() > 0;
		}

		return $updated;
	}

	/**
	 * @param integer $coverFileId
	 * @param integer $albumId
	 */
	public function setCover($coverFileId, $albumId) {
		$sql = 'UPDATE `*PREFIX*music_albums`
				SET `cover_file_id` = ?
				WHERE `id` = ?';
		$params = [$coverFileId, $albumId];
		$this->execute($sql, $params);
	}

	/**
	 * @param integer[] $coverFileIds
	 * @param string[]|null $userIds the users whose music library is targeted; all users are targeted if omitted
	 * @return Album[] albums which got modified (with incomplete data, only id and user are valid),
	 *         empty array if none
	 */
	public function removeCovers($coverFileIds, $userIds=null) {
		// find albums using the given file as cover
		$sql = 'SELECT `id`, `user_id` FROM `*PREFIX*music_albums` WHERE `cover_file_id` IN ' .
			$this->questionMarks(\count($coverFileIds));
		$params = $coverFileIds;
		if ($userIds !== null) {
			$sql .= ' AND `user_id` IN ' . $this->questionMarks(\count($userIds));
			$params = \array_merge($params, $userIds);
		}
		$albums = $this->findEntities($sql, $params);

		// if any albums found, remove the cover from those
		$count = \count($albums);
		if ($count) {
			$sql = 'UPDATE `*PREFIX*music_albums`
				SET `cover_file_id` = NULL
				WHERE `id` IN ' . $this->questionMarks($count);
			$params = Util::extractIds($albums);
			$this->execute($sql, $params);
		}

		return $albums;
	}

	/**
	 * @param string|null $userId target user; omit to target all users
	 * @return array of dictionaries with keys [albumId, userId, parentFolderId]
	 */
	public function getAlbumsWithoutCover($userId = null) {
		$sql = 'SELECT DISTINCT `albums`.`id`, `albums`.`user_id`, `files`.`parent`
				FROM `*PREFIX*music_albums` `albums`
				JOIN `*PREFIX*music_tracks` `tracks` ON `albums`.`id` = `tracks`.`album_id`
				JOIN `*PREFIX*filecache` `files` ON `tracks`.`file_id` = `files`.`fileid`
				WHERE `albums`.`cover_file_id` IS NULL';
		$params = [];
		if ($userId !== null) {
			$sql .= ' AND `albums`.`user_id` = ?';
			$params[] = $userId;
		}
		$result = $this->execute($sql, $params);
		$return = [];
		while ($row = $result->fetch()) {
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
	 * @return boolean True if a cover image was found and added for the album
	 */
	public function findAlbumCover($albumId, $parentFolderId) {
		$return = false;
		$coverNames = ['cover', 'albumart', 'front', 'folder'];
		$imagesSql = 'SELECT `fileid`, `name`
					FROM `*PREFIX*filecache`
					JOIN `*PREFIX*mimetypes` ON `*PREFIX*mimetypes`.`id` = `*PREFIX*filecache`.`mimetype`
					WHERE `parent` = ? AND `*PREFIX*mimetypes`.`mimetype` LIKE \'image%\'';
		$params = [$parentFolderId];
		$result = $this->execute($imagesSql, $params);
		$images = $result->fetchAll();
		if (\count($images)) {
			\usort($images, function ($imageA, $imageB) use ($coverNames) {
				$nameA = \strtolower($imageA['name']);
				$nameB = \strtolower($imageB['name']);
				$indexA = PHP_INT_MAX;
				$indexB = PHP_INT_MAX;
				foreach ($coverNames as $i => $coverName) {
					if ($indexA === PHP_INT_MAX && \strpos($nameA, $coverName) === 0) {
						$indexA = $i;
					}
					if ($indexB === PHP_INT_MAX && \strpos($nameB, $coverName) === 0) {
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
	public function countByArtist($artistId) {
		$sql = 'SELECT COUNT(*) AS count FROM '.
			'(SELECT DISTINCT `track`.`album_id` FROM '.
			'`*PREFIX*music_tracks` `track` WHERE `track`.`artist_id` = ? '.
			'UNION SELECT `album`.`id` FROM '.
			'`*PREFIX*music_albums` `album` WHERE `album`.`album_artist_id` = ?) tmp';
		$params = [$artistId, $artistId];
		$result = $this->execute($sql, $params);
		$row = $result->fetch();
		return $row['count'];
	}

	/**
	 * @see \OCA\Music\Db\BaseMapper::findUniqueEntity()
	 * @param Album $album
	 * @return Album
	 */
	protected function findUniqueEntity($album) {
		$sql = $this->selectUserEntities('`hash` = ?');
		return $this->findEntity($sql, [$album->getUserId(), $album->getHash()]);
	}
}
