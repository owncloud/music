<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli J�rvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli J�rvinen 2017
 */

namespace OCA\Music\Db;

use OCP\AppFramework\Db\Mapper;
use OCP\IDBConnection;

class Cache extends Mapper {

	public function __construct(IDBConnection $db){
		// there is no entity for this mapper -> '' as entity class name
		parent::__construct($db, 'music_cache', '');
	}

	/**
	 * @param string $userId
	 * @param string $key
	 * @param string $data
	 */
	public function add($userId, $key, $data){
		$sql = 'INSERT INTO `*PREFIX*music_cache` '.
			'(`user_id`, `key`, `data`) VALUES (?, ?, ?)';
		$result = $this->execute($sql, [$userId, $key, $data]);
		$result->closeCursor();
	}

	/**
	 * @param string $userId
	 * @param string $key
	 */
	public function remove($userId, $key = null){
		$sql = 'DELETE FROM `*PREFIX*music_cache` WHERE `user_id` = ?';
		$params = [$userId];
		if ($key !== null) {
			$sql .= 'AND `key` = ?';
			$params[] = $key;
		}
		$result = $this->execute($sql, $params);
		$result->closeCursor();
	}

	/**
	 * @param string $userId
	 * @param string $key
	 */
	public function get($userId, $key) {
		$sql = 'SELECT `data` FROM `*PREFIX*music_cache` '.
			'WHERE `user_id` = ? AND `key` = ?';
		$result = $this->execute($sql, [$userId, $key]);
		$rows = $result->fetchAll();
		$result->closeCursor();

		return count($rows) ? $rows[0]['data'] : null;
	}
}
