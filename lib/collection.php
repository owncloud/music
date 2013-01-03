<?php

/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OCA\Media;

//class for managing a music collection
class Collection {
	private $uid;
	private static $artistIdCache = array();
	private static $albumIdCache = array();

	public function __construct($user) {
		$this->uid = $user;
	}

	/**
	 * get the id of an artist (case-insensitive)
	 *
	 * @param string $name
	 * @return int
	 */
	public function getArtistId($name) {
		if (empty($name)) {
			return 0;
		}
		$name = strtolower($name);
		if (!isset(self::$artistIdCache[$name])) {
			$query = \OCP\DB::prepare("SELECT `artist_id` FROM `*PREFIX*media_artists` WHERE lower(`artist_name`) = ?");
			$result = $query->execute(array($name));
			if ($row = $result->fetchRow()) {
				self::$artistIdCache[$name] = $row['artist_id'];
			} else {
				return 0;
			}
		}
		return self::$artistIdCache[$name];
	}

	/**
	 * get the id of an album (case-insensitive)
	 *
	 * @param string $name
	 * @param int $artistId
	 * @return int
	 */
	public function getAlbumId($name, $artistId) {
		if (empty($name)) {
			return 0;
		}
		$name = strtolower($name);
		if (!isset(self::$albumIdCache[$artistId])) {
			self::$albumIdCache[$artistId] = array();
		}

		if (!isset(self::$albumIdCache[$artistId][$name])) {
			$query = \OCP\DB::prepare("SELECT `album_id` FROM `*PREFIX*media_albums` WHERE lower(`album_name`) = ? AND `album_artist` = ?");
			$result = $query->execute(array($name, $artistId));
			if ($row = $result->fetchRow()) {
				self::$albumIdCache[$artistId][$name] = $row['album_id'];
			} else {
				return 0;
			}
		}
		return self::$albumIdCache[$artistId][$name];
	}

	/**
	 * get the id of an song (case-insensitive)
	 *
	 * @param string $name
	 * @param int $artistId
	 * @param int $albumId
	 * @return int
	 */
	public function getSongId($name, $artistId, $albumId) {
		if (empty($name)) {
			return 0;
		}
		$name = strtolower($name);
		if (!isset(self::$albumIdCache[$artistId])) {
			self::$albumIdCache[$artistId] = array();
		}
		if (!isset(self::$albumIdCache[$artistId][$albumId])) {
			self::$albumIdCache[$artistId][$albumId] = array();
		}

		if (!isset(self::$albumIdCache[$artistId][$albumId][$name])) {
			$query = \OCP\DB::prepare("SELECT `song_id` FROM `*PREFIX*media_songs` WHERE
				`song_user`=? AND lower(`song_name`) = ? AND `song_artist` = ? AND `song_album` = ?");
			$result = $query->execute(array($this->uid, $name, $artistId, $albumId));
			if ($row = $result->fetchRow()) {
				self::$albumIdCache[$artistId][$albumId][$name] = $row['song_id'];
			} else {
				return 0;
			}
		}
		return self::$albumIdCache[$artistId][$albumId][$name];
	}

	/**
	 * Get the list of artists that (optionally) match a search string
	 *
	 * @param string $search (optional)
	 * @param bool $exact (optional)
	 * @return array the list of artists found
	 */
	public function getArtists($search = '%', $exact = false) {
		if (!$exact and $search != '%') {
			$search = "%$search%";
		} elseif ($search == '') {
			$search = '%';
		}
		$query = \OCP\DB::prepare("SELECT DISTINCT `artist_name`, `artist_id` FROM `*PREFIX*media_artists`
			INNER JOIN `*PREFIX*media_songs` ON `artist_id`=`song_artist` WHERE `artist_name` LIKE ? AND `song_user` = ? ORDER BY `artist_name`");
		$result = $query->execute(array($search, $this->uid));
		return $result->fetchAll();
	}

	/**
	 * Add an artists to the database
	 *
	 * @param string $name
	 * @return integer the artist_id of the added artist
	 */
	public function addArtist($name) {
		$name = trim($name);
		if ($name == '') {
			return 0;
		}
		//check if the artist is already in the database
		$artistId = $this->getArtistId($name);
		if ($artistId != 0) {
			return $artistId;
		} else {
			$query = \OCP\DB::prepare("INSERT INTO `*PREFIX*media_artists` (`artist_name`) VALUES (?)");
			$query->execute(array($name));
			return $this->getArtistId($name);
		}
	}

	/**
	 * Get the list of albums that (optionally) match an artist and/or search string
	 *
	 * @param integer $artist (optional)
	 * @param string $search (optional)
	 * @param bool $exact (optional)
	 * @return array the list of albums found
	 */
	public function getAlbums($artist = 0, $search = '%', $exact = false) {
		$cmd = "SELECT DISTINCT `album_name`, `album_artist`, `album_id`
			FROM `*PREFIX*media_albums` INNER JOIN `*PREFIX*media_songs` ON `album_id`=`song_album` WHERE `song_user`=? ";
		$params = array($this->uid);
		if ($artist != 0) {
			$cmd .= "AND `album_artist` = ? ";
			array_push($params, $artist);
		}
		if ($search != '%') {
			$cmd .= "AND `album_name` LIKE ? ";
			if (!$exact) {
				$search = "%$search%";
			}
			array_push($params, $search);
		}
		$cmd .= ' ORDER BY `album_name`';
		$query = \OCP\DB::prepare($cmd);
		return $query->execute($params)->fetchAll();
	}

	/**
	 * Add an album to the database
	 *
	 * @param string $name
	 * @param integer $artist
	 * @return integer the album_id of the added artist
	 */
	public function addAlbum($name, $artist) {
		$name = trim($name);
		if ($name == '') {
			return 0;
		}
		//check if the album is already in the database
		$albumId = self::getAlbumId($name, $artist);
		if ($albumId != 0) {
			return $albumId;
		} else {
			$stmt = \OCP\DB::prepare('INSERT INTO `*PREFIX*media_albums` (`album_name` ,`album_artist`) VALUES (?, ?)');
			if (!\OCP\DB::isError($stmt)) {
				$result = $stmt->execute(array($name, $artist));
				if (\OCP\DB::isError($result)) {
					\OC_Log::write('OC_MEDIA_COLLECTION', 'could not add album: ' . \OC_DB::getErrorMessage($result), \OC_Log::ERROR);
				}
			} else {
				\OC_Log::write('OC_MEDIA_COLLECTION', 'could not add album: ' . \OC_DB::getErrorMessage($stmt), \OC_Log::ERROR);
			}
			return $this->getAlbumId($name, $artist);
		}
	}

	/**
	 * Get the songs that (optionally) match an artist and/or album and/or search string
	 *
	 * @param integer $artist (optional)
	 * @param integer $album (optional)
	 * @param string $search (optional)
	 * @param bool $exact (optional)
	 * @return array
	 */
	public function getSongs($artist = 0, $album = 0, $search = '', $exact = false) {
		$params = array($this->uid);
		if ($artist != 0) {
			$artistString = "AND `song_artist` = ?";
			array_push($params, $artist);
		} else {
			$artistString = '';
		}
		if ($album != 0) {
			$albumString = "AND `song_album` = ?";
			array_push($params, $album);
		} else {
			$albumString = '';
		}
		if ($search) {
			if (!$exact) {
				$search = "%$search%";
			}
			$searchString = "AND `song_name` LIKE ?";
			array_push($params, $search);
		} else {
			$searchString = '';
		}
		$query = \OCP\DB::prepare('SELECT * FROM `*PREFIX*media_songs` WHERE
			`song_user`=? ' . $artistString . ' ' . $albumString . ' ' . $searchString . ' ORDER BY `song_track`, `song_name`, `song_path`');
		return $query->execute($params)->fetchAll();
	}

	/**
	 * Add an song to the database
	 *
	 * @param string $name
	 * @param string $path
	 * @param int $artist
	 * @param int $album
	 * @param int $length
	 * @param int $track
	 * @param int $size
	 * @return int the song_id of the added artist
	 */
	public function addSong($name, $path, $artist, $album, $length, $track, $size) {
		$name = trim($name);
		$path = trim($path);
		if ($name == '' or $path == '') {
			return 0;
		}
		//check if the song is already in the database
		$songId = $this->getSongId($name, $artist, $album);
		if ($songId != 0) {
			$songInfo = $this->getSong($songId);
			$this->moveSong($songInfo['song_path'], $path);
			return $songId;
		}

		if ($this->getSongCountByPath($path) !== 0) {
			$this->deleteSongByPath($path);
		}
		$query = \OCP\DB::prepare("INSERT INTO  `*PREFIX*media_songs` (`song_name` ,`song_artist` ,`song_album` ,`song_path` ,`song_user`,`song_length`,`song_track`,`song_size`,`song_playcount`,`song_lastplayed`)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0)");
		$query->execute(array($name, $artist, $album, $path, $this->uid, $length, $track, $size));
		\OCP\DB::insertid(' * PREFIX * media_songs_song');
		return $this->getSongId($name, $artist, $album);
	}

	public function getSongCount() {
		$query = \OCP\DB::prepare("SELECT COUNT(`song_id`) AS `count` FROM `*PREFIX*media_songs` WHERE `song_user` = ?");
		$row = $query->execute(array($this->uid))->fetchRow();
		return $row['count'];
	}

	public function getArtistCount() {
		$query = \OCP\DB::prepare('SELECT COUNT(DISTINCT `artist_id`) AS `count` FROM `*PREFIX*media_artists`
			INNER JOIN `*PREFIX*media_songs` ON `artist_id`=`song_artist` WHERE `song_user` = ?');
		$row = $query->execute(array($this->uid))->fetchRow();
		return $row['count'];
	}

	public function getAlbumCount() {
		$query = \OCP\DB::prepare("SELECT COUNT(DISTINCT `album_id`) AS `count` FROM `*PREFIX*media_albums`
			INNER JOIN `*PREFIX*media_songs` ON `album_id`=`song_album` WHERE `song_user` = ?");
		$row = $query->execute(array($this->uid))->fetchRow();
		return $row['count'];
	}

	public function getArtistName($artistId) {
		$query = \OCP\DB::prepare("SELECT `artist_name` FROM `*PREFIX*media_artists` WHERE `artist_id`=?");
		$result = $query->execute(array($artistId));
		if ($row = $result->fetchRow()) {
			return $row['artist_name'];
		} else {
			return '';
		}
	}

	public function getAlbumName($albumId) {
		$query = \OCP\DB::prepare("SELECT `album_name` FROM `*PREFIX*media_albums` WHERE `album_id`=?");
		$result = $query->execute(array($albumId));
		if ($row = $result->fetchRow()) {
			return $row['album_name'];
		} else {
			return '';
		}
	}

	public function getSong($id) {
		$query = \OCP\DB::prepare("SELECT * FROM `*PREFIX*media_songs` WHERE `song_id`=?");
		$result = $query->execute(array($id));
		if ($row = $result->fetchRow()) {
			return $row;
		} else {
			return '';
		}
	}

	/**
	 * get the number of songs in a directory
	 *
	 * @param string $path
	 * @return int
	 */
	public function getSongCountByPath($path) {
		$query = \OCP\DB::prepare("SELECT COUNT(`song_id`) AS `count` FROM `*PREFIX*media_songs` WHERE `song_path` LIKE ?");
		$result = $query->execute(array("$path%"));
		if ($row = $result->fetchRow()) {
			return $row['count'];
		} else {
			return 0;
		}
	}

	/**
	 * remove a song from the database by path
	 *
	 * @param string $path the path of the song
	 *
	 * if a path of a folder is passed, all songs stored in the folder will be removed from the database
	 */
	public function deleteSongByPath($path) {
		$query = \OCP\DB::prepare("DELETE FROM `*PREFIX*media_songs` WHERE `song_path` LIKE ? AND `song_user` = ?");
		$query->execute(array("$path%", $this->uid));
	}

	/**
	 * increase the play count of a song
	 *
	 * @param int $songId
	 */
	public function registerPlay($songId) {
		$now = time();
		$query = \OCP\DB::prepare('UPDATE `*PREFIX*media_songs` SET `song_playcount` = `song_playcount` + 1, `song_lastplayed` =? WHERE `song_id` =? AND `song_lastplayed` <?');
		$query->execute(array($now, $songId, $now - 60));
	}

	/**
	 * get the id of the song by path
	 *
	 * @param string $path
	 * @return int
	 */
	public function getSongByPath($path) {
		$query = \OCP\DB::prepare("SELECT `song_id` FROM `*PREFIX*media_songs` WHERE `song_path` = ?");
		$result = $query->execute(array($path));
		if ($row = $result->fetchRow()) {
			return $row['song_id'];
		} else {
			return 0;
		}
	}

	/**
	 * set the path of a song
	 *
	 * @param string $oldPath
	 * @param string $newPath
	 */
	public function moveSong($oldPath, $newPath) {
		$query = \OCP\DB::prepare("UPDATE `*PREFIX*media_songs` SET `song_path` = ? WHERE `song_path` = ?");
		$query->execute(array($newPath, $oldPath));
	}

	/**
	 * delete the entire collection cache for this user
	 */
	public function clear() {
		$query = \OCP\DB::prepare("DELETE FROM `*PREFIX*media_songs` WHERE `song_user` = ?");
		$query->execute(array($this->uid));

		//delete all artists with no associated songs
		$query=\OCP\DB::prepare('SELECT `artist_id` FROM `*PREFIX*media_artists` LEFT OUTER JOIN `*PREFIX*media_songs` ON
			`artist_id` = `song_artist` WHERE `song_artist` IS NULL');
		$result = $query->execute();

		$deleteQuery = \OCP\DB::prepare('DELETE FROM `*PREFIX*media_artists` WHERE `artist_id` = ?');
		while ($row = $result->fetchRow()) {
			$deleteQuery->execute(array($row['artist_id']));
		}

		//delete all albums with no associated songs
		$query=\OCP\DB::prepare('SELECT `album_id` FROM `*PREFIX*media_albums` LEFT OUTER JOIN `*PREFIX*media_songs` ON
			`album_id` = `song_album` WHERE `song_album` IS NULL');
		$result = $query->execute();

		$deleteQuery = \OCP\DB::prepare('DELETE FROM `*PREFIX*media_albums` WHERE `album_id` = ?');
		while ($row = $result->fetchRow()) {
			$deleteQuery->execute(array($row['album_id']));
		}
	}
}
