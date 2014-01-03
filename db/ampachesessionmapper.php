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

use \OCA\Music\AppFramework\Db\DoesNotExistException;

class AmpacheSessionMapper extends Mapper {

	public function __construct(API $api){
		parent::__construct($api, 'music_ampache_sessions');
	}

	public function find($token){
		$sql = 'SELECT `session`.`user_id` '.
			'FROM `*PREFIX*music_ampache_sessions` `session` '.
			'WHERE `session`.`token` = ? AND `session`.`expiry` > ?';
		$params = array($token, time());

		$result = $this->execute($sql, $params);

		// false if no row could be fetched
		return $result->fetchRow();
	}

	public function extend($token, $expiry){
		$sql = 'UPDATE `*PREFIX*music_ampache_sessions` `session` '.
			'SET `session`.`expiry` = ? '.
			'WHERE `session`.`token` = ?';
		$params = array($expiry, $token);
		$this->execute($sql, $params);
	}

	public function cleanUp(){
		$sql = 'DELETE FROM `*PREFIX*music_ampache_sessions` '.
			'WHERE `expiry` < ?';
		$params = array(time());
		$this->execute($sql, $params);
	}
}
