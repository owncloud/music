<?php

/**
 * ownCloud - Music app
 *
 * @author Morris Jobke
 * @copyright 2013 Morris Jobke <morris.jobke@gmail.com>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Music\Db;

use \OCA\Music\AppFramework\Db\Mapper;
use \OCA\Music\Core\API;

class AmpacheUserMapper extends Mapper {

	public function __construct(API $api){
		parent::__construct($api, 'music_ampache_users');
	}

	public function getPasswordHashes($userId){
		$sql = 'SELECT `hash` FROM `*PREFIX*music_ampache_users` '.
			'WHERE `user_id` = ?';
		$params = array($userId);
		$result = $this->execute($sql, $params);
		$rows = $result->fetchAll();

		$hashes = array();
		foreach ($rows as $value) {
			$hashes[] = $value['hash'];
		}

		return $hashes;
	}

	public function addUserKey($userId, $hash, $description){
		$sql = 'INSERT INTO `*PREFIX*music_ampache_users` '.
			'(`user_id`, `hash`, `description`) VALUES (?, ?, ?)';
		$params = array($userId, $hash, $description);
		$this->execute($sql, $params);

		$sql = 'SELECT `id` FROM `*PREFIX*music_ampache_users` '.
				'WHERE `user_id` = ? AND `hash` = ?';
		$params = array($userId, $hash);
		$result = $this->execute($sql, $params, 1);
		$row = $result->fetchRow();

		if($row === null){
			return null;
		}

		return $row['id'];
	}

	public function removeUserKey($userId, $id){
		$sql = 'DELETE FROM `*PREFIX*music_ampache_users` '.
				'WHERE `user_id` = ? AND `id` = ?';
		$params = array($userId, $id);
		$this->execute($sql, $params);
	}

	public function getAll($userId) {
		$sql = 'SELECT `id`, `hash`, `description` FROM `*PREFIX*music_ampache_users` '.
			'WHERE `user_id` = ?';
		$params = array($userId);
		$result = $this->execute($sql, $params);
		$rows = $result->fetchAll();

		return $rows;
	}
}
