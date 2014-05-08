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

if (version_compare($installedVersion, '0.1.6-alpha', '<')) {
	$sqls = array(
		'DELETE FROM `*PREFIX*music_artists`;',
		'DELETE FROM `*PREFIX*music_albums`;',
		'DELETE FROM `*PREFIX*music_album_artists`;',
		'DELETE FROM `*PREFIX*music_tracks`;',
		'DELETE FROM `*PREFIX*music_scanned_users`;'
	);
	foreach ($sqls as $sql) {
		$query = \OCP\DB::prepare($sql);
		$query->execute();
	}
}

if (version_compare($installedVersion, '0.1.8.2-beta', '<')) {
	//convert 'ownCloud unknown xxx' to null
	$sqls = array(
		'UPDATE `*PREFIX*music_albums` SET `name` = NULL WHERE `name` = \'ownCloud unknown album\'',
		'UPDATE `*PREFIX*music_artists` SET `name` = NULL WHERE `name` = \'ownCloud unknown artist\'',
	);
	foreach ($sqls as $sql) {
		$query = \OCP\DB::prepare($sql);
		$query->execute();
	}
}
