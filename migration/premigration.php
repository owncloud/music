<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2017
 */

namespace OCA\Music\Migration;

use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class PreMigration implements IRepairStep {

	public function getName() {
		return 'Drop incompatible music database entries';
	}

	/**
	 * @inheritdoc
	 */
	public function run(IOutput $output) {
		$installedVersion = \OCP\Config::getAppValue('music', 'installed_version');

		$sqls = [];

		// Versions prior to 0.3.12 had a separate table for album artists
		if (version_compare($installedVersion, '0.3.12', '<')) {
			$sqls[] = 'DELETE FROM `*PREFIX*music_ampache_sessions`';
			$sqls[] = 'DROP TABLE `*PREFIX*music_album_artists`';
		}

		// Versions prior to 0.3.14 had a separate table for playlist tracks
		if (version_compare($installedVersion, '0.3.14', '<')) {
			$sqls[] = 'DROP TABLE `*PREFIX*music_playlist_tracks`;';
		}

		// DB schema for tracks/albums/artists has been changed for 0.3.16
		if (version_compare($installedVersion, '0.3.16.1', '<')) {
			$sqls[] = 'DELETE FROM `*PREFIX*music_artists`';
			$sqls[] = 'DELETE FROM `*PREFIX*music_albums`';
			$sqls[] = 'DELETE FROM `*PREFIX*music_tracks`';
			$sqls[] = 'DELETE FROM `*PREFIX*music_playlists`';
		}

		// Invalidate the cache if the previous version is new enough to have one.
		// This might not be strictly necessary on all migrations, but it will anyway do no harm.
		if (version_compare($installedVersion, '0.3.15', '>=')) {
			$sqls[] = 'DELETE FROM `*PREFIX*music_cache`';
		}

		foreach ($sqls as $sql) {
			$query = \OCP\DB::prepare($sql);
			$query->execute();
		}
	}
}
