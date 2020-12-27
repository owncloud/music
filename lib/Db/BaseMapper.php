<?php declare(strict_types=1);

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

use \OCP\AppFramework\Db\DoesNotExistException;
use \OCP\AppFramework\Db\Entity;
use \OCP\AppFramework\Db\Mapper;
use \OCP\AppFramework\Db\MultipleObjectsReturnedException;
use \OCP\IDBConnection;

use \Doctrine\DBAL\Exception\UniqueConstraintViolationException;

/**
 * Common base class for data access classes of the Music app
 */
abstract class BaseMapper extends Mapper {
	const SQL_DATE_FORMAT = 'Y-m-d H:i:s.v';

	protected $nameColumn;

	public function __construct(IDBConnection $db, string $tableName, string $entityClass, string $nameColumn) {
		parent::__construct($db, $tableName, $entityClass);
		$this->nameColumn = $nameColumn;
	}

	/**
	 * Create an empty object of the entity class bound to this mapper
	 */
	public function createEntity() : Entity {
		return new $this->entityClass();
	}

	/**
	 * Find a single entity by id and user_id
	 * @throws DoesNotExistException if the entity does not exist
	 * @throws MultipleObjectsReturnedException if more than one entity exists
	 */
	public function find(int $id, string $userId) : Entity {
		$sql = $this->selectUserEntities("`{$this->getTableName()}`.`id` = ?");
		return $this->findEntity($sql, [$userId, $id]);
	}

	/**
	 * Find all entities matching the given IDs. Specifying the owning user is optional.
	 * @param integer[] $ids  IDs of the entities to be found
	 * @param string|null $userId
	 * @return Entity[]
	 */
	public function findById(array $ids, string $userId=null) : array {
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
	 * @return Entity[]
	 */
	public function findAll(string $userId, int $sortBy=SortBy::None, int $limit=null, int $offset=null) : array {
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
	 * @return Entity[]
	 */
	public function findAllByName(
			?string $name, string $userId, bool $fuzzy = false, int $limit=null, int $offset=null) : array {
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
	 * @return Entity[]
	 */
	public function findAllStarred(string $userId, int $limit=null, int $offset=null) : array {
		$sql = $this->selectUserEntities(
				"`{$this->getTableName()}`.`starred` IS NOT NULL",
				"ORDER BY LOWER(`{$this->getTableName()}`.`{$this->nameColumn}`)");
		return $this->findEntities($sql, [$userId], $limit, $offset);
	}

	/**
	 * Delete all entities with given IDs without specifying the user
	 * @param integer[] $ids  IDs of the entities to be deleted
	 */
	public function deleteById(array $ids) : void {
		$count = \count($ids);
		if ($count === 0) {
			return;
		}
		$sql = "DELETE FROM `{$this->getTableName()}` WHERE `id` IN ". $this->questionMarks($count);
		$this->execute($sql, $ids);
	}

	/**
	 * Delete all entities of the given user
	 */
	public function deleteAll(string $userId) : void {
		$sql = "DELETE FROM `{$this->getTableName()}` WHERE `user_id` = ?";
		$this->execute($sql, [$userId]);
	}

	/**
	 * Tests if entity with given ID and user ID exists in the database
	 */
	public function exists(int $id, string $userId) : bool {
		$sql = "SELECT 1 FROM `{$this->getTableName()}` WHERE `id` = ? AND `user_id` = ?";
		$result = $this->execute($sql, [$id, $userId]);
		return $result->rowCount() > 0;
	}

	/**
	 * Count all entities of a user
	 */
	public function count(string $userId) : int {
		$sql = "SELECT COUNT(*) AS count FROM `{$this->getTableName()}` WHERE `user_id` = ?";
		$result = $this->execute($sql, [$userId]);
		$row = $result->fetch();
		return \intval($row['count']);
	}

	/**
	 * Insert an entity, or if an entity with the same identity already exists,
	 * update the existing entity.
	 * @return Entity The inserted or updated entity, containing also the id field
	 */
	public function insertOrUpdate(Entity $entity) : Entity {
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
	 * @param \DateTime|null $date
	 * @param integer[] $ids
	 * @param string $userId
	 * @return int number of modified entities
	 */
	public function setStarredDate(?\DateTime $date, array $ids, string $userId) : int {
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
	 */
	protected function questionMarks(int $count) : string {
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
	protected function selectUserEntities(string $condition=null, string $extension=null) : string {
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
	protected function selectEntities(string $condition, string $extension=null) : string {
		return "SELECT * FROM `{$this->getTableName()}` WHERE $condition $extension ";
	}

	/**
	 * Find an entity which has the same identity as the supplied entity.
	 * How the identity of the entity is defined, depends on the derived concrete class.
	 */
	abstract protected function findUniqueEntity(Entity $entity) : Entity;
}
