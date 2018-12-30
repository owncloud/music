<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Gregory Baudet <gregory.baudet@gmail.com>
 * @copyright Gregory Baudet 2018
 */

namespace OCA\Music\Migration;

use OCP\IConfig;
use OCP\ILogger;
use OCP\IDBConnection;
use OCA\Music\BusinessLayer\TrackBusinessLayer;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class PlaylistCleanup implements IRepairStep {

	/** @var IDBConnection */
	private $db;

	/** @var IConfig */
	private $config;

	/** @var TrackBusinessLayer */
	private $tracker;

	public function __construct(IDBConnection $connection, IConfig $config, TrackBusinessLayer $tracker) {
		$this->db = $connection;
		$this->config = $config;
		$this->tracker = $tracker;
	}

	public function getName() {
		return 'Remove playlist files from music_tracks table';
	}

	/**
	 * @inheritdoc
	 */
	public function run(IOutput $output) {
		$installedVersion = $this->config->getAppValue('music', 'installed_version');

		// Get version of the core
		$coreVersion = \OCP\Util::getVersion();

		if ($coreVersion[0] >= 13) {
			$n = $this->removePlaylistFiles();
			$output->info("$n files with audio/mpegurl or audio/x-scpls mime type removed from library");
			// Clean cache
			$this->db->executeQuery("DELETE FROM `*PREFIX*music_cache`");
		}
	}

	private function removePlaylistFiles() {
		//find tracks with mime audio/mpegurl and audio/x-scpls
		$sql = "SELECT `file_id` FROM `*PREFIX*music_tracks` WHERE `mimetype` = 'audio/mpegurl' OR `mimetype` = 'audio/x-scpls'";
		$sth = $this->db->executeQuery($sql);
		$file_ids = $sth->fetchAll(\PDO::FETCH_COLUMN, 0);
		// remove corresponding tracks and albums/artists if not needed anymore
		if (count($file_ids)) {
			$result = $this->tracker->deleteTracks($file_ids);
		}
		return count($file_ids);
	}

}
