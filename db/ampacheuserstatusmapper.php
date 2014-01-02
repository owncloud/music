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

class AmpacheUserStatusMapper extends Mapper {

	public function __construct(API $api){
		parent::__construct($api, 'music_ampache_user_status');
	}

	public function isAmpacheUser($userId){
		$sql = 'SELECT * FROM `*PREFIX*music_ampache_user_status` `user` '.
			'WHERE `user`.`user_id` = ? LIMIT 1';
		$params = array($userId);
		$result = $this->execute($sql, $params);
		$row = $result->fetchRow();

		if($row === false || $row === null){
			return false;
		}else{
			return true;
		}
	}

	public function addAmpacheUser($userId){
		$sql = 'INSERT INTO `*PREFIX*music_ampache_user_status` (`user_id`) '.
			'VALUES (?)';
		$params = array($userId);
		$this->execute($sql, $params);
	}

	public function removeAmpacheUser($userId){
		$sql = 'DELETE FROM `*PREFIX*music_ampache_user_status` '.
			'WHERE `user_id` = ?';
		$params = array($userId);
		$this->execute($sql, $params);
	}
}
