<?php

/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OCA\Media;

class Media {
	/**
	 * get the sha256 hash of the password needed for ampache
	 *
	 * @param array $params, parameters passed from OC_Hook
	 */
	public static function loginListener($params) {
		if (isset($params['uid']) and $params['password']) {
			$name = $params['uid'];
			$query = \OCP\DB::prepare("SELECT `user_id` from `*PREFIX*media_users` WHERE `user_id` LIKE ?");
			$uid = $query->execute(array($name))->fetchAll();
			if (count($uid) == 0) {
				$password = hash('sha256', $_POST['password']);
				$query = \OCP\DB::prepare("INSERT INTO `*PREFIX*media_users` (`user_id`, `user_password_sha256`) VALUES (?, ?);");
				$query->execute(array($name, $password));
			}
		}
	}

	/**
	 * get the sha256 hash of the password needed for ampache
	 *
	 * @param array $params, parameters passed from OC_Hook
	 */
	public static function passwordChangeListener($params) {
		if (isset($params['uid']) and $params['password']) {
			$name = $params['uid'];
			$password = hash('sha256', $params['password']);
			$query = \OCP\DB::prepare("UPDATE `*PREFIX*media_users` SET `user_password_sha256` = ? WHERE `user_id` = ?");
			$query->execute(array($password, $name));
		}
	}

	/**
	 *
	 */
	public static function updateFile($params) {
		$path = $params['path'];
		if (!$path) return;
		//fix a bug where there were multiply '/' in front of the path, it should only be one
		while ($path[0] == '/') {
			$path = substr($path, 1);
		}
		$path = '/' . $path;
		$collection = new Collection(\OCP\User::getUser());
		$scanner = new Scanner($collection);
		$scanner->scanFile($path);
	}

	/**
	 *
	 */
	public static function deleteFile($params) {
		$path = $params['path'];
		$collection = new Collection(\OCP\User::getUser());
		$collection->deleteSongByPath($path);
	}

	public static function moveFile($params) {
		$collection = new Collection(\OCP\User::getUser());
		$collection->moveSong($params['oldpath'], $params['newpath']);
	}
}

class SearchProvider extends \OC_Search_Provider {
	function search($query) {
		$collection = new Collection(\OCP\User::getUser());
		$l = \OC_L10N::get('media');
		$app_name = (string)$l->t('Music');
		$artists = $collection->getArtists($query);
		$albums = $collection->getAlbums(0, $query);
		$songs = $collection->getSongs(0, 0, $query);
		$results = array();
		foreach ($artists as $artist) {
			$results[] = new \OC_Search_Result($artist['artist_name'], '', \OCP\Util::linkTo('media', 'index.php') . '#artist=' . urlencode($artist['artist_name']), $app_name);
		}
		foreach ($albums as $album) {
			$artist = $collection->getArtistName($album['album_artist']);
			$results[] = new \OC_Search_Result($album['album_name'], 'by ' . $artist, \OCP\Util::linkTo('media', 'index.php') . '#artist=' . urlencode($artist) . '&album=' . urlencode($album['album_name']), $app_name);
		}
		foreach ($songs as $song) {
			$minutes = floor($song['song_length'] / 60);
			$seconds = $song['song_length'] % 60;
			$artist = $collection->getArtistName($song['song_artist']);
			$album = $collection->getalbumName($song['song_album']);
			$results[] = new \OC_Search_Result($song['song_name'], "by $artist, in $album $minutes:$seconds", \OCP\Util::linkTo('media', 'index.php') . '#artist=' . urlencode($artist) . '&album=' . urlencode($album) . '&song=' . urlencode($song['song_name']), $app_name);
		}
		return $results;
	}
}
