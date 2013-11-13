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

	public function updatePassphrase($userId, $password){
		$sql = 'SELECT COUNT(*) FROM `*PREFIX*music_ampache_users` '.
			'WHERE `user_id` = ? LIMIT 1';
		$params = array($userId);
		$result = $this->execute($sql, $params);
		$row = $result->fetchRow();

		$hash = hash('sha256', $password);

		if($row['COUNT(*)'] === '0'){
			$sql = 'INSERT INTO `*PREFIX*music_ampache_users` '.
				'(`hash`, `user_id`) VALUES (?, ?)';
		} else {
			$sql = 'UPDATE `*PREFIX*music_ampache_users` '.
				'SET `hash` = ? WHERE `user_id` = ?';
		}
		$params = array($hash, $userId);
		$result = $this->execute($sql, $params);
	}

	public function removeUser($userId){
		$sql = 'DELETE FROM `*PREFIX*music_ampache_users` '.
			'WHERE `user_id` = ?';
		$this->execute($sql, array($userId));
	}

	public function removeAllUser(){
		$sql = 'DELETE FROM `*PREFIX*music_ampache_users`';
		$this->execute($sql);
	}

	public function getPasswordHash($userId){
		$sql = 'SELECT hash FROM `*PREFIX*music_ampache_users` '.
			'WHERE `user_id` = ? LIMIT 1';
		$params = array($userId);
		$result = $this->execute($sql, $params);
		$row = $result->fetchRow();

		if($row === null){
			return null;
		}

		return $row['hash'];
	}
}
