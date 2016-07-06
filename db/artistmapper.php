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

use OCP\AppFramework\Db\Mapper;
use OCP\IDb;

class ArtistMapper extends Mapper {

	public function __construct(IDb $db){
		parent::__construct($db, 'music_artists', '\OCA\Music\Db\Artist');
	}

	/**
	 * @param string $condition
	 * @return string
	 */
	private function makeSelectQuery($condition=null){
		return 'SELECT `artist`.`name`, `artist`.`image`, `artist`.`id` '.
			'FROM `*PREFIX*music_artists` `artist` '.
			'WHERE `artist`.`user_id` = ? ' . $condition;
	}

	/**
	 * @param null $condition
	 * @param null $having
	 * @param null $order
	 * @return string
	 */
	private function makeJoinedQuery($condition=null, $having=null, $order=null){
		return 'SELECT `artist`.`name`, `artist`.`image`, `artist`.`id` '.
		'FROM `*PREFIX*music_artists` `artist` '.
		'JOIN `*PREFIX*music_tracks` `tracks` ON `artist`.`id`=`tracks`.`artist_id` '.
		'JOIN `*PREFIX*filecache` `files` ON `tracks`.`file_id`=`files`.`fileid` '.
		'WHERE `artist`.`user_id` = ? ' . $condition . ' GROUP BY `artist`.`id` ' . $having . ' ORDER BY ' . $order;
	}

	/**
	 * @param string $userId
	 * @param int $limit
	 * @param int $offset
	 * @param string $add
	 * @param string $update
	 * @return Artist[]
	 */
	public function findAll($userId, $limit=null, $offset=null, $add=null, $update=null){
		if(is_null($add) && is_null($update)) {
			$sql = $this->makeSelectQuery('ORDER BY `artist`.`name`');
			$params = array($userId);
		} else {
			$added_start = \DateTime::createFromFormat('Y-m-d', explode("/", $add)[0]);
			$added_end = count(explode("/", $add)) > 1 ? \DateTime::createFromFormat('Y-m-d', explode("/", $add)[1]) : false;
			$updated_start = \DateTime::createFromFormat('Y-m-d', explode("/", $update)[0]);
			$updated_end = count(explode("/", $update)) > 1 ? \DateTime::createFromFormat('Y-m-d', explode("/", $update)[1]) : false;
			$condition = "";
			$having = "";
			$params = array($userId);

			if($updated_start) {
				$condition .= 'AND `files`.`mtime` >= ? ';
				array_push($params, $updated_start->format('U'));
			}
			if($updated_end) {
				$condition .= 'AND `files`.`mtime` <= ? ';
				array_push($params, $updated_end->format('U'));
			}

			if($updated_start || $updated_end) {
					$having .= 'HAVING MIN(`files`.`mtime`) != MAX(`files`.`mtime`) ';
				}
				if($added_start) {
					$having .= (strlen($having)==0 ? 'HAVING' : 'AND') . ' MIN(`files`.`mtime`) >= ? ';
					array_push($params, $added_start->format('U'));
				}
				if($added_end) {
					$having .= (strlen($having)==0 ? 'HAVING' : 'AND') . ' MIN(`files`.`mtime`) <= ? ';
					array_push($params, $added_end->format('U'));
				}

			$order = '`artist`.`name`';
			$sql = $this->makeJoinedQuery($condition, $having, $order);
		}

		return $this->findEntities($sql, $params, ($limit==0 ? null : $limit), $offset);
	}

	/**
	 * @param integer[] $artistIds
	 * @param string $userId
	 * @return Artist[]
	 */
	public function findMultipleById($artistIds, $userId){
		$questionMarks = array();
		for($i = 0; $i < count($artistIds); $i++){
			$questionMarks[] = '?';
		}
		$sql = $this->makeSelectQuery('AND `artist`.`id` IN (' .
			implode(',', $questionMarks) .') ORDER BY `artist`.`name`');
		$params = $artistIds;
		array_unshift($params, $userId);
		return $this->findEntities($sql, $params);
	}


	/**
	 * @param integer $artistId
	 * @param string $userId
	 * @return Artist
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
	 * @param null $add
	 * @param null $update
	 * @return array
	 */
	protected function makeFindByNameSqlAndParams($artistName, $userId, $fuzzy = false, $add=null, $update = null) {
		if ($artistName === null) {
			$condition = 'AND `artist`.`name` IS NULL ';
			$params = array($userId);
		} else if($fuzzy) {
			$condition = 'AND LOWER(`artist`.`name`) LIKE LOWER(?) ';
			$params = array($userId, '%' . $artistName . '%');
		} else {
			$condition = 'AND `artist`.`name` = ? ';
			$params = array($userId, $artistName);
		}
		if(is_null($add) && is_null($update)) {
			$sql = $this->makeSelectQuery($condition.'ORDER BY `artist`.`name`');
		} else {
			$added_start = \DateTime::createFromFormat('Y-m-d', explode("/", $add)[0]);
			$added_end = count(explode("/", $add)) > 1 ? \DateTime::createFromFormat('Y-m-d', explode("/", $add)[1]) : false;
			$updated_start = \DateTime::createFromFormat('Y-m-d', explode("/", $update)[0]);
			$updated_end = count(explode("/", $update)) > 1 ? \DateTime::createFromFormat('Y-m-d', explode("/", $update)[1]) : false;
			$having = "";

			if($updated_start) {
				$condition .= 'AND `files`.`mtime` >= ? ';
				array_push($params, $updated_start->format('U'));
			}
			if($updated_end) {
				$condition .= 'AND `files`.`mtime` <= ? ';
				array_push($params, $updated_end->format('U'));
			}

			if($updated_start || $updated_end) {
				$having .= 'HAVING MIN(`files`.`mtime`) != MAX(`files`.`mtime`) ';
			}
			if($added_start) {
				$having .= (strlen($having)==0 ? 'HAVING' : 'AND') . ' MIN(`files`.`mtime`) >= ? ';
				array_push($params, $added_start->format('U'));
			}
			if($added_end) {
				$having .= (strlen($having)==0 ? 'HAVING' : 'AND') . ' MIN(`files`.`mtime`) <= ? ';
				array_push($params, $added_end->format('U'));
			}


			$order = '`artist`.`name`';
			$sql = $this->makeJoinedQuery($condition, $having, $order);
		}

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
	 * @param int $limit
	 * @param int $offset
	 * @param string $add
	 * @param string $update
	 * @return Artist[]
	 */
	public function findAllByName($artistName, $userId, $fuzzy = false, $limit=null, $offset=null, $add=null, $update=null){
		$sqlAndParams = $this->makeFindByNameSqlAndParams($artistName, $userId, $fuzzy, $add, $update);
		return $this->findEntities($sqlAndParams['sql'], $sqlAndParams['params'], ($limit==0 ? null : $limit), $offset);
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
	 * @return integer
	 */
	public function count($userId){
		$sql = 'SELECT COUNT(*) AS count FROM `*PREFIX*music_artists` '.
			'WHERE `user_id` = ?';
		$params = array($userId);
		$result = $this->execute($sql, $params);
		$row = $result->fetch();
		return $row['count'];
	}

}
