<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2017 - 2024
 */

namespace OCA\Music\Migration;

use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class PreMigration implements IRepairStep {

	private IDBConnection $db;
	private IConfig $config;

	public function __construct(IDBConnection $connection, IConfig $config) {
		$this->db = $connection;
		$this->config = $config;
	}

	public function getName() {
		return 'Drop any incompatible music database entries';
	}

	/**
	 * @inheritdoc
	 */
	public function run(IOutput $output) {
		$installedVersion = $this->config->getAppValue('music', 'installed_version');

		// Drop obsolete tables created by previous versions if they still exist.
		// No need to check version numbers here.
		$this->dropTables([
				'music_album_artists',
				'music_playlist_tracks'
		]);

		// Wipe clean the tables which have changed so that the old data does not
		// fulfill the new schema.
		$tablesToErase = [];

		if (\version_compare($installedVersion, '0.3.16.1', '<')) {
			$tablesToErase[] = 'music_artists';
			$tablesToErase[] = 'music_albums';
			$tablesToErase[] = 'music_tracks';
			$tablesToErase[] = 'music_playlists';
		}

		if (\version_compare($installedVersion, '1.3.0-alpha2', '<')) {
			$tablesToErase[] = 'music_bookmarks';
		}

		if (\version_compare($installedVersion, '1.9.0', '<')) {
			$tablesToErase[] = 'music_ampache_sessions';
		}

		// Invalidate the cache on each update (if there is one).
		// This might not be strictly necessary on all migrations, but it will do no harm.
		$tablesToErase[] = 'music_cache';

		$this->eraseTables($tablesToErase);
	}

	private function dropTables(array $tables) {
		foreach ($tables as $table) {
			if ($this->db->tableExists($table)) {
				$this->db->dropTable($table);
			}
		}
	}

	private function eraseTables(array $tables) {
		foreach ($tables as $table) {
			if ($this->db->tableExists($table)) {
				$this->db->executeQuery("DELETE FROM `*PREFIX*$table`");
			}
		}
	}
}
