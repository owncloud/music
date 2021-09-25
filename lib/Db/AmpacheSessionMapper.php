<?php declare(strict_types=1);

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

/**
 * @method AmpacheSession findEntity(string $sql, array $params)
 */
class AmpacheSessionMapper extends Mapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'music_ampache_sessions', AmpacheSession::class);
	}

	/**
	 * @param string $token
	 * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
	 * @return AmpacheSession
	 */
	public function findByToken($token) {
		$sql = 'SELECT *
				FROM `*PREFIX*music_ampache_sessions`
				WHERE `token` = ? AND `expiry` > ?';
		$params = [$token, \time()];

		return $this->findEntity($sql, $params);
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
}
