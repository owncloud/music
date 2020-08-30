<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2013, 2014
 * @copyright Pauli Järvinen 2020
 */

namespace OCA\Music\Db;

use OCP\AppFramework\Db\Mapper;
use OCP\IDBConnection;

class AmpacheSessionMapper extends Mapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'music_ampache_sessions', '\OCA\Music\Db\AmpacheSession');
	}

	/**
	 * @param string $token
	 * @return string|false
	 */
	public function findByToken($token) {
		$sql = 'SELECT `user_id`
				FROM `*PREFIX*music_ampache_sessions`
				WHERE `token` = ? AND `expiry` > ?';
		$params = [$token, \time()];

		$result = $this->execute($sql, $params);

		$row = $result->fetch();
		return \is_array($row) ? $row['user_id'] : false;
	}

	/**
	 * @param string $token
	 * @param integer $expiry
	 */
	public function extend($token, $expiry) {
		$sql = 'UPDATE `*PREFIX*music_ampache_sessions`
				SET `expiry` = ?
				WHERE `token` = ?';

		$params = [$expiry, $token];
		$this->execute($sql, $params);
	}

	public function cleanUp() {
		$sql = 'DELETE FROM `*PREFIX*music_ampache_sessions`
				WHERE `expiry` < ?';
		$params = [\time()];
		$this->execute($sql, $params);
	}

	/**
	 * @param string $token
	 * @return integer|false
	 */
	public function getExpiryTime($token) {
		$sql = 'SELECT `expiry`
				FROM `*PREFIX*music_ampache_sessions`
				WHERE `token` = ?';
		$params = [$token];

		$result = $this->execute($sql, $params);

		$row = $result->fetch();
		return \is_array($row) ? (int)$row['expiry'] : false;
	}
}
