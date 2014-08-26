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

class ArtistMapper extends Mapper implements IMapper {

	public function __construct(Db $db){
		parent::__construct($db, 'music_artists', '\OCA\Music\Db\Artist');
	}

	/**
	 * @param string $condition
	 */
	private function makeSelectQuery($condition=null){
		return 'SELECT `artist`.`name`, `artist`.`image`, `artist`.`id` '.
			'FROM `*PREFIX*music_artists` `artist` '.
			'WHERE `artist`.`user_id` = ? ' . $condition;
	}

	/**
	 * @param string $userId
	 */
	public function findAll($userId){
		$sql = $this->makeSelectQuery();
		$params = array($userId);
		return $this->findEntities($sql, $params);
	}

	/**
	 * @param integer[] $artistIds
	 * @param string $userId
	 */
	public function findMultipleById($artistIds, $userId){
		$questionMarks = array();
		for($i = 0; $i < count($artistIds); $i++){
			$questionMarks[] = '?';
		}
		$sql = $this->makeSelectQuery('AND `artist`.`id` IN (' .
			implode(',', $questionMarks) .')');
		$params = $artistIds;
		array_unshift($params, $userId);
		return $this->findEntities($sql, $params);
	}


	/**
	 * @param integer $artistId
	 * @param string $userId
	 */
	public function find($artistId, $userId){
		$sql = $this->makeSelectQuery('AND `artist`.`id` = ?');
		$params = array($userId, $artistId);
		return $this->findEntity($sql, $params);
	}

	/**
	 * @param string|null $artistName
	 * @param string $userId
	 * @param bool $fuzzy
	 */
	protected function makeFindByNameSqlAndParams($artistName, $userId, $fuzzy = false) {
		if ($artistName === null) {
			$condition = 'AND `artist`.`name` IS NULL';
			$params = array($userId);
		} elseif($fuzzy) {
			$condition = 'AND LOWER(`artist`.`name`) LIKE LOWER(?)';
			$params = array($userId, '%' . $artistName . '%');
		} else {
			$condition = 'AND `artist`.`name` = ?';
			$params = array($userId, $artistName);
		}
		$sql = $this->makeSelectQuery($condition);
		return array(
			'sql' => $sql,
			'params' => $params,
		);
	}

	/**
	 * @param string|null $artistName
	 * @param string $userId
	 * @param bool $fuzzy
	 */
	public function findByName($artistName, $userId, $fuzzy = false){
		$sqlAndParams = $this->makeFindByNameSqlAndParams($artistName, $userId, $fuzzy);
		return $this->findEntity($sqlAndParams['sql'], $sqlAndParams['params']);
	}

	/**
	 * @param string|null $artistName
	 * @param string $userId
	 * @param bool $fuzzy
	 */
	public function findAllByName($artistName, $userId, $fuzzy = false){
		$sqlAndParams = $this->makeFindByNameSqlAndParams($artistName, $userId, $fuzzy);
		return $this->findEntities($sqlAndParams['sql'], $sqlAndParams['params']);
	}

	/**
	 * @param integer[] $artistIds
	 */
	public function deleteById($artistIds){
		if(count($artistIds) === 0)
			return;
		$questionMarks = array();
		for($i = 0; $i < count($artistIds); $i++){
			$questionMarks[] = '?';
		}
		$sql = 'DELETE FROM `*PREFIX*music_artists` WHERE `id` IN ('. implode(',', $questionMarks) . ')';
		$this->execute($sql, $artistIds);
	}

	/**
	 * @param string $userId
	 */
	public function count($userId){
		$sql = 'SELECT COUNT(*) AS count FROM `*PREFIX*music_artists` '.
			'WHERE `user_id` = ?';
		$params = array($userId);
		$result = $this->execute($sql, $params);
		$row = $result->fetchRow();
		return $row['count'];
	}

}
