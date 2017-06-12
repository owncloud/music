<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @copyright Morris Jobke 2013
 */

$installedVersion = \OCP\Config::getAppValue('music', 'installed_version');

if (version_compare($installedVersion, '0.3.12', '<')) {
	$sqls = array(
		'DELETE FROM `*PREFIX*music_ampache_sessions`',
		'DROP TABLE `*PREFIX*music_album_artists`;',
	);
	foreach ($sqls as $sql) {
		$query = \OCP\DB::prepare($sql);
		$query->execute();
	}
}

if (version_compare($installedVersion, '0.3.14', '<')) {
	$sqls = array(
		'DROP TABLE `*PREFIX*music_playlist_tracks`;',
	);
	foreach ($sqls as $sql) {
		$query = \OCP\DB::prepare($sql);
		$query->execute();
	}
}

if (version_compare($installedVersion, '0.3.16', '<')) {
	$sqls = array(
		'DELETE FROM `*PREFIX*music_artists`;',
		'DELETE FROM `*PREFIX*music_albums`;',
		'DELETE FROM `*PREFIX*music_tracks`;',
		'DELETE FROM `*PREFIX*music_playlists`',
	);
	foreach ($sqls as $sql) {
		$query = \OCP\DB::prepare($sql);
		$query->execute();
	}
}
