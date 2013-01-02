<?php

/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OCA\Media;

//implementation of ampache's xml api
class Ampache {
	/**
	 * @var Collection $collection
	 */
	private $collection;

	public function error($code, $msg) {
		$tmpl = new \OC_Template('media', 'ampache/error');
		$tmpl->assign('code', $code);
		$tmpl->assign('msg', $msg);
		$tmpl->printPage();
		die();
	}

	/**
	 * do the initial handshake
	 *
	 * @param array $params
	 */
	public function handshake($params) {
		$auth = (isset($params['auth'])) ? $params['auth'] : false;
		$user = (isset($params['user'])) ? $params['user'] : false;
		$time = (isset($params['timestamp'])) ? $params['timestamp'] : false;
		$now = time();
		if ($now - $time > (10 * 60)) {
			$this->error(400, 'timestamp is more then 10 minutes old');
		}
		if ($auth and $user and $time) {
			$query = \OCP\DB::prepare("SELECT `user_id`, `user_password_sha256` FROM `*PREFIX*media_users` WHERE `user_id`=?");
			$result = $query->execute(array($user));
			if ($row = $result->fetchRow()) {
				$pass = $row['user_password_sha256'];
				$key = hash('sha256', $time . $pass);
				if ($key == $auth) {
					$token = hash('sha256', 'oc_media_' . $key);
					$this->collection = new Collection($row['user_id']);
					$date = date('c'); //todo proper update/add/clean dates
					$songs = $this->collection->getSongCount();
					$artists = $this->collection->getArtistCount();
					$albums = $this->collection->getAlbumCount();
					$query = \OCP\DB::prepare("INSERT INTO `*PREFIX*media_sessions` (`token`, `user_id`, `start`) VALUES (?, ?, now());");
					$query->execute(array($token, $user));
					$expire = date('c', time() + 600);

					$tmpl = new \OC_Template('media', 'ampache/handshake');
					$tmpl->assign('token', $token);
					$tmpl->assign('date', $date);
					$tmpl->assign('songs', $songs);
					$tmpl->assign('artists', $artists);
					$tmpl->assign('albums', $albums);
					$tmpl->assign('expire', $expire);
					$tmpl->printPage();
					return;
				}
			}
			$this->error(400, 'Invalid login');
		} else {
			$this->error(400, 'Missing arguments');
		}
	}

	public function ping($params) {
		if (isset($params['auth'])) {
			if ($this->checkAuth($params['auth'])) {
				$this->updateAuth($params['auth']);
			} else {
				$this->error(400, 'Invalid login');
				return;
			}
		}
		$tmpl = new \OC_Template('media', 'ampache/ping');
		$tmpl->printPage();
	}

	public function checkAuth($auth) {
		if (is_array($auth)) {
			if (isset($auth['auth'])) {
				$auth = $auth['auth'];
			} else {
				return false;
			}
		}
		$CONFIG_DBTYPE = \OCP\Config::getSystemValue("dbtype", "sqlite");
		if ($CONFIG_DBTYPE == 'pgsql') {
			$interval = ' \'600s\'::interval ';
		} else {
			$interval = '600';
		}
		//remove old sessions
		$query = \OCP\DB::prepare("DELETE FROM `*PREFIX*media_sessions` WHERE `start`<(NOW() - " . $interval . ")");
		$query->execute();

		$query = \OCP\DB::prepare("SELECT `user_id` FROM `*PREFIX*media_sessions` WHERE `token`=?");
		$result = $query->execute(array($auth));
		if ($row = $result->fetchRow()) {
			$this->collection = new Collection($row['user_id']);
			return true;
		} else {
			return false;
		}
	}

	public function updateAuth($auth) {
		$query = \OCP\DB::prepare("UPDATE `*PREFIX*media_sessions` SET `start`=CURRENT_TIMESTAMP WHERE `token`=?");
		$query->execute(array($auth));
	}

	private function printArtists($artists) {
		header('Content-Type:  text/xml');
		$tmpl = new \OC_Template('media', 'ampache/artists');

		foreach ($artists as $artist) {
			$artistData = array();
			$artistData['albums'] = count($this->collection->getAlbums($artist['artist_id']));
			$artistData['songs'] = count($this->collection->getSongs($artist['artist_id']));
			$artistData['id'] = $artist['artist_id'];
			$artistData['name'] = xmlentities($artist['artist_name']);
			$tmpl->append('artists', $artistData);
		}
		$tmpl->printPage();
	}

	private function printAlbums($albums, $artistName = false) {
		header('Content-Type:  text/xml');
		$tmpl = new \OC_Template('media', 'ampache/albums');
		foreach ($albums as $album) {
			$albumData = array();
			if ($artistName) {
				$albumData['artist_name'] = xmlentities($artistName);
			} else {
				$albumData['artist_name'] = xmlentities($this->collection->getArtistName($album['album_artist']));
			}
			$albumData['songs'] = count($this->collection->getSongs($album['album_artist'], $album['album_id']));
			$albumData['id'] = $album['album_id'];
			$albumData['name'] = xmlentities($album['album_name']);
			$albumData['artist'] = $album['album_artist'];
			$tmpl->append('albums', $albumData);
		}
		$tmpl->printPage();
	}

	private function printSongs($songs, $artistName = false, $albumName = false) {
		header('Content-Type:  text/xml');
		$tmpl = new \OC_Template('media', 'ampache/songs');

		foreach ($songs as $song) {
			$songData = array();
			if ($artistName) {
				$songData['artist_name'] = xmlentities($artistName);
			} else {
				$songData['artist_name'] = xmlentities($this->collection->getArtistName($song['song_artist']));
			}
			if ($albumName) {
				$songData['album_name'] = xmlentities($albumName);
			} else {
				$songData['album_name'] = xmlentities($this->collection->getAlbumName($song['song_album']));
			}
			$songData['id'] = $song['song_id'];
			$songData['name'] = xmlentities($song['song_name']);
			$songData['artist'] = $song['song_artist'];
			$songData['album'] = $song['song_album'];
			$songData['length'] = $song['song_length'];
			$songData['track'] = $song['song_track'];
			$songData['size'] = $song['song_size'];
			$url = \OCP\Util::linkToRemote('ampache') . 'server/xml.server.php/?action=play&song=' . $songData['id'] . '&auth=' . $_GET['auth'];
			$songData['url'] = xmlentities($url);
			$tmpl->append('songs', $songData);
		}
		$tmpl->printPage();
	}

	public function artists($params) {
		if (!$this->checkAuth($params)) {
			$this->error(400, 'Invalid Login');
		}
		$filter = isset($params['filter']) ? $params['filter'] : '';
		$exact = isset($params['exact']) ? ($params['exact'] == 'true') : false;
		$artists = $this->collection->getArtists($filter, $exact);
		$this->printArtists($artists);
	}

	public function artist_songs($params) {
		if (!$this->checkAuth($params)) {
			$this->error(400, 'Invalid Login');
		}
		$filter = isset($params['filter']) ? $params['filter'] : '';
		$songs = $this->collection->getSongs($filter);
		$artist = $this->collection->getArtistName($filter);
		$this->printSongs($songs, $artist);
	}

	public function artist_albums($params) {
		if (!$this->checkAuth($params)) {
			$this->error(400, 'Invalid Login');
		}
		$filter = isset($params['filter']) ? $params['filter'] : '';
		$albums = $this->collection->getAlbums($filter);
		$artist = $this->collection->getArtistName($filter);
		$this->printAlbums($albums, $artist);
	}

	public function albums($params) {
		if (!$this->checkAuth($params)) {
			$this->error(400, 'Invalid Login');
		}
		$filter = isset($params['filter']) ? $params['filter'] : '';
		$exact = isset($params['exact']) ? ($params['exact'] == 'true') : false;
		$albums = $this->collection->getAlbums(0, $filter, $exact);
		$this->printAlbums($albums, false);
	}

	public function album_songs($params) {
		if (!$this->checkAuth($params)) {
			$this->error(400, 'Invalid Login');
		}
		$songs = $this->collection->getSongs(0, $params['filter']);
		if (count($songs) > 0) {
			$artist = $this->collection->getArtistName($songs[0]['song_artist']);
		} else {
			$artist = '';
		}
		$this->printSongs($songs, $artist);
	}

	public function songs($params) {
		if (!$this->checkAuth($params)) {
			$this->error(400, 'Invalid Login');
		}
		$filter = isset($params['filter']) ? $params['filter'] : '';
		$exact = isset($params['exact']) ? ($params['exact'] == 'true') : false;
		$songs = $this->collection->getSongs(0, 0, $filter, $exact);
		$this->printSongs($songs);
	}

	public function song($params) {
		if (!$this->checkAuth($params)) {
			$this->error(400, 'Invalid Login');
		}
		if ($song = $this->collection->getSong($params['filter'])) {
			$this->printSongs(array($song));
		}
	}

	public function play($params) {
		if (!$this->checkAuth($params)) {
			$this->error(400, 'Invalid Login');
		}
		if ($song = $this->collection->getSong($params['song'])) {
			\OC_Util::setupFS($song["song_user"]);

			header('Content-type: ' . \OC_Filesystem::getMimeType($song['song_path']));
			header('Content-Length: ' . $song['song_size']);
			\OC_Filesystem::readfile($song['song_path']);
		}
	}

	public function url_to_song($params) {
		if (!$this->checkAuth($params)) {
			$this->error(400, 'Invalid Login');
		}
		$url = $params['url'];
		$songId = substr($url, strrpos($url, 'song=') + 5);
		if ($song = $this->collection->getSong($songId)) {
			$this->printSongs(array($song));
		}
	}

	public function search_songs($params) {
		if (!$this->checkAuth($params)) {
			$this->error(400, 'Invalid Login');
		}
		$filter = isset($params['filter']) ? $params['filter'] : '';
		$artists = $this->collection->getArtists($filter);
		$albums = $this->collection->getAlbums(0, $filter);
		$songs = $this->collection->getSongs(0, 0, $filter);
		foreach ($artists as $artist) {
			$songs = array_merge($songs, $this->collection->getSongs($artist['artist_id']));
		}
		foreach ($albums as $album) {
			$songs = array_merge($songs, $this->collection->getSongs($album['album_artist'], $album['album_id']));
		}
		$this->printSongs($songs);
	}
}

/**
 * From http://dk1.php.net/manual/en/function.htmlentities.php#106535
 */
function get_xml_entity_at_index_0($CHAR) {
	if (!is_string($CHAR[0]) || (strlen($CHAR[0]) > 1)) {
		die("function: 'get_xml_entity_at_index_0' requires data type: 'char' (single character). '{$CHAR[0]}' does not match this type.");
	}
	switch ($CHAR[0]) {
		case "'":
		case '"':
		case '&':
		case '<':
		case '>':
			return htmlspecialchars($CHAR[0], ENT_QUOTES);
			break;
		default:
			return numeric_entity_4_char($CHAR[0]);
			break;
	}
}

function numeric_entity_4_char($char) {
	return "&#" . str_pad(ord($char), 3, '0', STR_PAD_LEFT) . ";";
}

function xmlentities($string) {
	$not_in_list = "A-Z0-9a-z\s_-";
	return preg_replace_callback("/[^{$not_in_list}]/", '\OCA\Media\get_xml_entity_at_index_0', $string);
}
