<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2016
 */

namespace OCA\Music\Db;

use OCP\AppFramework\Db\Mapper;
use OCP\IDBConnection;

/**
 * Common base class for data access classes of the Music app
 */
class BaseMapper extends Mapper {

	public function __construct(IDBConnection $db, $tableName, $entityClass=null){
		parent::__construct($db, $tableName, $entityClass);
	}

	/**
	 * @param integer[] $ids  IDs of the entities to be deleted
	 */
	public function deleteById($ids){
		$count = count($ids);
		if($count === 0) {
			return;
		}
		$questionMarks = array();
		for($i = 0; $i < $count; $i++){
			$questionMarks[] = '?';
		}
		$sql = 'DELETE FROM `' . $this->getTableName() . '` WHERE `id` IN ('. implode(',', $questionMarks) . ')';
		$this->execute($sql, $ids);
	}

	/**
	 * @param string $userId
	 */
	public function count($userId){
		$sql = 'SELECT COUNT(*) AS count FROM `' . $this->getTableName() . '` '.
			'WHERE `user_id` = ?';
		$params = array($userId);
		$result = $this->execute($sql, $params);
		$row = $result->fetch();
		return $row['count'];
	}

}
