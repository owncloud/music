<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @copyright Morris Jobke 2013, 2014
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
