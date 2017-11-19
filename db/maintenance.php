<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2014
 * @copyright Pauli Järvinen 2017
 */

namespace OCA\Music\Db;

use OCP\IDBConnection;

use \OCA\Music\AppFramework\Core\Logger;


class Maintenance {

	/** @var IDBConnection */
	private $db;
	/** @var Logger */
	private $logger;

	public function __construct(IDBConnection $db, Logger $logger){
		$this->db = $db;
		$this->logger = $logger;
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
				SELECT `album_artist_id` FROM `*PREFIX*music_albums`
				UNION
				SELECT `artist_id` FROM `*PREFIX*music_tracks`
			);'
		);

		$updatedRows = 0;
		foreach ($sqls as $sql) {
			$updatedRows += $this->db->executeUpdate($sql);
		}

		return $updatedRows;
	}

	/**
	 * Wipe clean the music database of the given user, or all users
	 * @param string $userId
	 * @param boolean $allUsers
	 */
	public function resetDb($userId, $allUsers = false) {
		if ($userId && $allUsers) {
			throw new InvalidArgumentException('userId should be null if allUsers targeted');
		}
	
		$sqls = array(
				'DELETE FROM `*PREFIX*music_tracks`',
				'DELETE FROM `*PREFIX*music_albums`',
				'DELETE FROM `*PREFIX*music_artists`',
				'UPDATE *PREFIX*music_playlists SET track_ids=NULL',
				'DELETE FROM `*PREFIX*music_cache`'
		);
	
		foreach ($sqls as $sql) {
			$params = [];
			if (!$allUsers) {
				$sql .=  ' WHERE `user_id` = ?';
				$params[] = $userId;
			}
			$this->db->executeUpdate($sql, $params);
		}
	
		if ($allUsers) {
			$this->logger->log("Erased music databases of all users", 'info');
		} else {
			$this->logger->log("Erased music database of user $userId", 'info');
		}
	}
}
