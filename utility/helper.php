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

use OCP\IDBConnection;


class Helper {

	/** @var IDBConnection */
	private $db;

	public function __construct(IDBConnection $db){
		$this->db = $db;
	}

	/**
	 * Removes orphaned data from the database
	 */
	public function cleanUp() {
		$sqls = array(
			'UPDATE `*PREFIX*music_albums` SET `cover_file_id` = NULL
				WHERE `cover_file_id` IS NOT NULL AND `cover_file_id` IN (
					SELECT `cover_file_id` FROM (
						SELECT `cover_file_id` FROM `*PREFIX*music_albums`
						LEFT JOIN `*PREFIX*filecache`
							ON `cover_file_id`=`fileid`
						WHERE `fileid` IS NULL
					) mysqlhack
				);',
			'DELETE FROM `*PREFIX*music_tracks` WHERE `file_id` IN (
				SELECT `file_id` FROM (
					SELECT `file_id` FROM `*PREFIX*music_tracks`
					LEFT JOIN `*PREFIX*filecache`
						ON `file_id`=`fileid`
					WHERE `fileid` IS NULL
					) mysqlhack
				);',
			'DELETE FROM `*PREFIX*music_albums` WHERE `id` IN (
				SELECT `id` FROM (
					SELECT `*PREFIX*music_albums`.`id`
					FROM `*PREFIX*music_albums`
					LEFT JOIN `*PREFIX*music_tracks`
						ON `*PREFIX*music_tracks`.`album_id` = `*PREFIX*music_albums`.`id`
					WHERE `*PREFIX*music_tracks`.`album_id` IS NULL
				) as tmp
			);',
			'DELETE FROM `*PREFIX*music_artists` WHERE `id` NOT IN (
				SELECT DISTINCT * FROM (
					SELECT `artist`.`id` FROM `*PREFIX*music_artists` `artist`
					JOIN `*PREFIX*music_albums` `album` ON
					`artist`.`id` = `album`.`album_artist_id` UNION SELECT `track`.`artist_id`
					FROM `*PREFIX*music_tracks` `track`
				) as tmp
			);'
		);

		foreach ($sqls as $sql) {
			$query = $this->db->prepare($sql);
			$query->execute();
		}
	}
}
