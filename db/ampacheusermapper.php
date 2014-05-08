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

class AmpacheUserMapper extends Mapper {

	public function __construct(Db $db){
		// there is no entity for this mapper -> '' as entity class name
		parent::__construct($db, 'music_ampache_users', '');
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
