<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2016 - 2020
 */

namespace OCA\Music\Db;

use OCP\AppFramework\Db\Mapper;
use OCP\IDBConnection;

use \Doctrine\DBAL\Exception\UniqueConstraintViolationException;

/**
 * Common base class for data access classes of the Music app
 */
abstract class BaseMapper extends Mapper {

	const SQL_DATE_FORMAT = 'Y-m-d H:i:s';

	protected $nameColumn;

	/**
	 * @param IDBConnection $db
	 * @param string $tableName
	 * @param string $entityClass
	 */
	public function __construct(IDBConnection $db, $tableName, $entityClass, $nameColumn) {
		parent::__construct($db, $tableName, $entityClass);
		$this->nameColumn = $nameColumn;
	}

	/**
	 * Find a single entity by id and user_id
	 * @param integer $id
	 * @param string $userId
	 * @throws DoesNotExistException if the entity does not exist
	 * @throws MultipleObjectsReturnedException if more than one entity exists
	 * @return Entity
	 */
	public function find($id, $userId) {
		$sql = $this->selectUserEntities("`{$this->getTableName()}`.`id` = ?");
		return $this->findEntity($sql, [$userId, $id]);
	}

	/**
	 * Find all entities matching the given IDs. Specifying the owning user is optional.
	 * @param integer[] $ids  IDs of the entities to be found
	 * @param string|null $userId
	 * @return Entity[]
	 */
	public function findById($ids, $userId=null) {
		$count = \count($ids);
		$condition = "`{$this->getTableName()}`.`id` IN ". $this->questionMarks($count);

		if (empty($userId)) {
			$sql = $this->selectEntities($condition);
		} else {
			$sql = $this->selectUserEntities($condition);
			$ids = \array_merge([$userId], $ids);
		}

		return $this->findEntities($sql, $ids);
	}

	/**
	 * Find all user's entities
	 * 
	 * @param string $userId
	 * @param integer $sortBy sort order of the result set
	 * @param integer|null $limit
	 * @param integer|null $offset
	 * @return Entity[]
	 */
	public function findAll($userId, $sortBy=SortBy::None, $limit=null, $offset=null) {
		if ($sortBy == SortBy::Name) {
			$sorting = "ORDER BY LOWER(`{$this->getTableName()}`.`{$this->nameColumn}`)";
		} elseif ($sortBy == SortBy::Newest) {
			$sorting = "ORDER BY `{$this->getTableName()}`.`id` DESC"; // abuse the fact that IDs are ever-incrementing values
		} else {
			$sorting = null;
		}
		$sql = $this->selectUserEntities('', $sorting);
		$params = [$userId];
		return $this->findEntities($sql, $params, $limit, $offset);
	}

	/**
	 * Find all user's entities matching the given name
	 * 
	 * @param string|null $name
	 * @param string $userId
	 * @param bool $fuzzy
	 * @param integer|null $limit
	 * @param integer|null $offset
	 * @return Artist[]
	 */
	public function findAllByName($name, $userId, $fuzzy = false, $limit=null, $offset=null) {
		$nameCol = "`{$this->getTableName()}`.`{$this->nameColumn}`";
		if ($name === null) {
			$condition = "$nameCol IS NULL";
			$params = [$userId];
		} elseif ($fuzzy) {
			$condition = "LOWER($nameCol) LIKE LOWER(?)";
			$params = [$userId, "%$name%"];
		} else {
			$condition = "$nameCol = ?";
			$params = [$userId, $name];
		}
		$sql = $this->selectUserEntities($condition, "ORDER BY LOWER($nameCol)");

		return $this->findEntities($sql, $params, $limit, $offset);
	}

	/**
	 * Find all user's starred entities
	 * 
	 * @param string $userId
	 * @param integer|null $limit
	 * @param integer|null $offset
	 * @return Entity[]
	 */
	public function findAllStarred($userId, $limit=null, $offset=null) {
		$sql = $this->selectUserEntities(
				"`{$this->getTableName()}`.`starred` IS NOT NULL",
				"ORDER BY LOWER(`{$this->getTableName()}`.`{$this->nameColumn}`)");
		return $this->findEntities($sql, [$userId], $limit, $offset);
	}

	/**
	 * Delete all entities with given IDs without specifying the user
	 * @param integer[] $ids  IDs of the entities to be deleted
	 */
	public function deleteById($ids) {
		$count = \count($ids);
		if ($count === 0) {
			return;
		}
		$sql = "DELETE FROM `{$this->getTableName()}` WHERE `id` IN ". $this->questionMarks($count);
		$this->execute($sql, $ids);
	}

	/**
	 * Count all entities of a user
	 * @param string $userId
	 */
	public function count($userId) {
		$sql = "SELECT COUNT(*) AS count FROM `{$this->getTableName()}` WHERE `user_id` = ?";
		$result = $this->execute($sql, [$userId]);
		$row = $result->fetch();
		return \intval($row['count']);
	}

	/**
	 * Insert an entity, or if an entity with the same identity already exists,
	 * update the existing entity.
	 * @param Entity $entity
	 * @return Entity The inserted or updated entity, containing also the id field
	 */
	public function insertOrUpdate($entity) {
		try {
			return $this->insert($entity);
		} catch (UniqueConstraintViolationException $ex) {
			$existingEntity = $this->findUniqueEntity($entity);
			$entity->setId($existingEntity->getId());
			return $this->update($entity);
		}
	}

	/**
	 * Set the "starred" column of the given entities
	 * @param DateTime|null $date
	 * @param integer[] $ids
	 * @param string $userId
	 * @return int number of modified entities
	 */
	public function setStarredDate($date, $ids, $userId) {
		$count = \count($ids);
		if (!empty($date)) {
			$date = $date->format(self::SQL_DATE_FORMAT);
		}

		$sql = "UPDATE `{$this->getTableName()}` SET `starred` = ?
				WHERE `id` IN {$this->questionMarks($count)} AND `user_id` = ?";
		$params = \array_merge([$date], $ids, [$userId]);
		return $this->execute($sql, $params)->rowCount();
	}

	/**
	 * helper creating a string like '(?,?,?)' with the specified number of elements
	 * @param int $count
	 */
	protected function questionMarks($count) {
		$questionMarks = [];
		for ($i = 0; $i < $count; $i++) {
			$questionMarks[] = '?';
		}
		return '(' . \implode(',', $questionMarks) . ')';
	}

	/**
	 * Build a SQL SELECT statement which selects all entities of the given user,
	 * and optionally applies other conditions, too.
	 * This is built upon `selectEntities` which may be overridden by the derived class.
	 * @param string|null $condition Optional extra condition. This will get automatically
	 *                               prefixed with ' AND ', so don't include that.
	 * @param string|null $extension Any extension (e.g. ORDER BY, LIMIT) to be added after
	 *                               the conditions in the SQL statement
	 */
	protected function selectUserEntities($condition=null, $extension=null) {
		$allConditions = "`{$this->getTableName()}`.`user_id` = ?";

		if (!empty($condition)) {
			$allConditions .= " AND $condition";
		}

		return $this->selectEntities($allConditions, $extension);
	}

	/**
	 * Build a SQL SELECT statement which selects all entities matching the given condition.
	 * The derived class may override this if necessary.
	 * @param string $condition This will get automatically prefixed with ' WHERE '
	 * @param string|null $extension Any extension (e.g. ORDER BY, LIMIT) to be added after
	 *                               the conditions in the SQL statement
	 */
	protected function selectEntities($condition, $extension=null) {
		return "SELECT * FROM `{$this->getTableName()}` WHERE $condition $extension ";
	}

	/**
	 * Find an entity which has the same identity as the supplied entity.
	 * How the identity of the entity is defined, depends on the derived concrete class.
	 * @param Entity $entity
	 * @return Entity
	 */
	abstract protected function findUniqueEntity($entity);
}
