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

use OCP\IDBConnection;

class ArtistMapper extends BaseMapper {

	public function __construct(IDBConnection $db){
		parent::__construct($db, 'music_artists', '\OCA\Music\Db\Artist');
	}

	/**
	 * @param string $condition
	 */
	private function makeSelectQuery($condition=null){
		return 'SELECT `artist`.`name`, `artist`.`image`, `artist`.`id`, '.
			'`artist`.`mbid`, `artist`.`hash` FROM `*PREFIX*music_artists` `artist` '.
			'WHERE `artist`.`user_id` = ? ' . $condition;
	}

	/**
	 * @param string $userId
	 * @param SortBy $sortBy sort order of the result set
	 * @param integer $limit
	 * @param integer $offset
	 * @return Artist[]
	 */
	public function findAll($userId, $sortBy=SortBy::None, $limit=null, $offset=null){
		$sql = $this->makeSelectQuery(
				$sortBy == SortBy::Name ? 'ORDER BY LOWER(`artist`.`name`)' : null);
		$params = array($userId);
		return $this->findEntities($sql, $params, $limit, $offset);
	}

	/**
	 * @param integer[] $artistIds
	 * @param string $userId
	 * @return Artist[]
	 */
	public function findMultipleById($artistIds, $userId){
		$sql = $this->makeSelectQuery('AND `artist`.`id` IN '
			. $this->questionMarks(count($artistIds))
			. ' ORDER BY LOWER(`artist`.`name`)');
		$params = $artistIds;
		array_unshift($params, $userId);
		return $this->findEntities($sql, $params);
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
		$sql = $this->makeSelectQuery($condition . ' ORDER BY LOWER(`artist`.`name`)');
		return array(
			'sql' => $sql,
			'params' => $params,
		);
	}

	/**
	 * @param string|null $artistName
	 * @param string $userId
	 * @param bool $fuzzy
	 * @return Artist
	 */
	public function findByName($artistName, $userId, $fuzzy = false){
		$sqlAndParams = $this->makeFindByNameSqlAndParams($artistName, $userId, $fuzzy);
		return $this->findEntity($sqlAndParams['sql'], $sqlAndParams['params']);
	}

	/**
	 * @param string|null $artistName
	 * @param string $userId
	 * @param bool $fuzzy
	 * @return Artist[]
	 */
	public function findAllByName($artistName, $userId, $fuzzy = false){
		$sqlAndParams = $this->makeFindByNameSqlAndParams($artistName, $userId, $fuzzy);
		return $this->findEntities($sqlAndParams['sql'], $sqlAndParams['params']);
	}

	public function findUniqueEntity(Artist $artist){
		return $this->findEntity(
				'SELECT * FROM `*PREFIX*music_artists` WHERE `user_id` = ? AND `hash` = ?',
				[$artist->getUserId(), $artist->getHash()]
		);
	}
}
