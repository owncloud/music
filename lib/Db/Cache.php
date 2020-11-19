<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2017 - 2020
 */

namespace OCA\Music\Db;

use OCP\IDBConnection;

use \Doctrine\DBAL\Exception\UniqueConstraintViolationException;

class Cache {
	private $db;

	public function __construct(IDBConnection $db) {
		$this->db = $db;
	}

	/**
	 * @param string $userId
	 * @param string $key
	 * @param string $data
	 */
	public function add($userId, $key, $data) {
		$sql = 'INSERT INTO `*PREFIX*music_cache`
				(`user_id`, `key`, `data`) VALUES (?, ?, ?)';
		$this->db->executeUpdate($sql, [$userId, $key, $data]);
	}

	/**
	 * @param string $userId
	 * @param string $key
	 * @param string $data
	 */
	public function update($userId, $key, $data) {
		$sql = 'UPDATE `*PREFIX*music_cache` SET `data` = ?
				WHERE `user_id` = ? AND `key` = ?';
		$this->db->executeUpdate($sql, [$data, $userId, $key]);
	}

	/**
	 * @param string $userId
	 * @param string $key
	 * @param string $data
	 */
	public function set($userId, $key, $data) {
		try {
			$this->add($userId, $key, $data);
		} catch (UniqueConstraintViolationException $e) {
			$this->update($userId, $key, $data);
		}
	}

	/**
	 * Remove one or several key-value pairs
	 *
	 * @param string $userId User to target, omit to target all users
	 * @param string $key Key to target, omit to target all keys
	 */
	public function remove($userId = null, $key = null) {
		$sql = 'DELETE FROM `*PREFIX*music_cache`';
		$params = [];
		if ($userId !== null) {
			$sql .= ' WHERE `user_id` = ?';
			$params[] = $userId;

			if ($key !== null) {
				$sql .= ' AND `key` = ?';
				$params[] = $key;
			}
		} elseif ($key !== null) {
			$sql .= ' WHERE `key` = ?';
			$params[] = $key;
		}
		$this->db->executeUpdate($sql, $params);
	}

	/**
	 * @param string $userId
	 * @param string $key
	 * @return string|null
	 */
	public function get($userId, $key) {
		$sql = 'SELECT `data` FROM `*PREFIX*music_cache`
				WHERE `user_id` = ? AND `key` = ?';
		$result = $this->db->executeQuery($sql, [$userId, $key]);
		$rows = $result->fetchAll();
		$result->closeCursor();

		return \count($rows) ? $rows[0]['data'] : null;
	}

	/**
	 * Get all key-value pairs of one user, optionally limitting to keys with a given prefix.
	 * @param string $userId
	 * @param string|null $prefix
	 * @return array of arrays with keys 'key', 'data'
	 */
	public function getAll($userId, $prefix = null) {
		$sql = 'SELECT `key`, `data` FROM `*PREFIX*music_cache`
				WHERE `user_id` = ?';
		$params = [$userId];

		if (!empty($prefix)) {
			$sql .= ' AND `key` LIKE ?';
			$params[] = $prefix . '%';
		}

		$result = $this->db->executeQuery($sql, $params);
		$rows = $result->fetchAll();
		$result->closeCursor();

		return $rows;
	}
}
