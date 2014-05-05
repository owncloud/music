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

use \OCA\Music\AppFramework\Core\Db;
use \OCA\Music\AppFramework\Db\IMapper;
use \OCA\Music\AppFramework\Db\Mapper;

class AmpacheSessionMapper extends Mapper {

	public function __construct(db $db){
		parent::__construct($db, 'music_ampache_sessions', '\OCA\Music\Db\AmpacheSession');
	}

	public function findByToken($token){
		$sql = 'SELECT `user_id` '.
			'FROM `*PREFIX*music_ampache_sessions` '.
			'WHERE `token` = ? AND `expiry` > ?';
		$params = array($token, time());

		$result = $this->execute($sql, $params);

		// false if no row could be fetched
		return $result->fetchRow();
	}

	public function extend($token, $expiry){
		$sql = 'UPDATE `*PREFIX*music_ampache_sessions` '.
			'SET `expiry` = ? '.
			'WHERE `token` = ?';

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
