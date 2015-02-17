<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @copyright Morris Jobke 2014
 */

namespace OCA\Music\Utility;

use \OCA\Music\AppFramework\Core\Db;


class Helper {

	private $db;

	public function __construct(Db $db){
		$this->db = $db;
	}

	/**
	 * Removes orphaned data from the database
	 */
	public function cleanUp() {
		$sqls = array(
			'UPDATE `*PREFIX*music_albums` SET `cover_file_id` = NULL WHERE `cover_file_id` IS NOT NULL AND `cover_file_id` IN (SELECT `cover_file_id` FROM `*PREFIX*music_albums` LEFT JOIN `*PREFIX*filecache` ON `cover_file_id`=`fileid` WHERE `fileid` IS NULL);',
			'DELETE FROM `*PREFIX*music_tracks` WHERE `file_id` IN (SELECT `file_id` FROM `*PREFIX*music_tracks` LEFT JOIN `*PREFIX*filecache` ON `file_id`=`fileid` WHERE `fileid` IS NULL);',
			'DELETE FROM `*PREFIX*music_albums` WHERE `id` NOT IN (SELECT `album_id` FROM `*PREFIX*music_tracks` GROUP BY `album_id`);',
			'DELETE FROM `*PREFIX*music_album_artists` WHERE `album_id` NOT IN (SELECT `id` FROM `*PREFIX*music_albums` GROUP BY `id`);',
			'DELETE FROM `*PREFIX*music_artists` WHERE `id` NOT IN (SELECT `artist_id` FROM `*PREFIX*music_album_artists` GROUP BY `artist_id`);'
		);

		foreach ($sqls as $sql) {
			$query = $this->db->prepareQuery($sql);
			$query->execute();
		}
	}
}
