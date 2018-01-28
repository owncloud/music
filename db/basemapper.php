<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2016 - 2018
 */

namespace OCA\Music\Db;

use OCP\AppFramework\Db\Mapper;
use OCP\IDBConnection;

use \Doctrine\DBAL\Exception\UniqueConstraintViolationException;

/**
 * Common base class for data access classes of the Music app
 */
class BaseMapper extends Mapper {

	/**
	 * @param IDBConnection $db
	 * @param string $tableName
	 * @param string $entityClass
	 */
	public function __construct(IDBConnection $db, $tableName, $entityClass=null){
		parent::__construct($db, $tableName, $entityClass);
	}

	/**
	 * Find a single entity by id and user_id
	 * @param integer $id
	 * @param string $userId
	 * @throws DoesNotExistException if the entity does not exist
	 * @throws MultipleObjectsReturnedException if more than one entity exists
	 * @return Entity
	 */
	public function find($id, $userId){
		$sql = 'SELECT * FROM `' . $this->getTableName() . '` WHERE `id` = ? AND `user_id` = ?';
		return $this->findEntity($sql, [$id, $userId]);
	}

	/**
	 * Find all entities matching the given IDs. Specifying the owning user is optional.
	 * @param integer[] $ids  IDs of the entities to be found
	 * @param string|null $userId
	 * @return Entity[]
	 */
	public function findById($ids, $userId=null){
		$count = count($ids);
		$sql = 'SELECT * FROM `' . $this->getTableName() . '` WHERE `id` IN '. $this->questionMarks($count);
		if (!empty($userId)) {
			$sql .= ' AND `user_id` = ?';
			$ids[] = $userId;
		}
		return $this->findEntities($sql, $ids);
	}

	/**
	 * Delete all entities with given IDs without specifying the user
	 * @param integer[] $ids  IDs of the entities to be deleted
	 */
	public function deleteById($ids){
		$count = count($ids);
		if($count === 0) {
			return;
		}
		$sql = 'DELETE FROM `' . $this->getTableName() . '` WHERE `id` IN '. $this->questionMarks($count);
		$this->execute($sql, $ids);
	}

	/**
	 * Count all entities of a user
	 * @param string $userId
	 */
	public function count($userId){
		$sql = 'SELECT COUNT(*) AS count FROM `' . $this->getTableName() . '` '.
			'WHERE `user_id` = ?';
		$result = $this->execute($sql, [$userId]);
		$row = $result->fetch();
		return intval($row['count']);
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
			$existingEntity = $this->findUniqueEntity($entity); // this should be implemented by the derived class
			$entity->setId($existingEntity->getId());
			return $this->update($entity);
		}
	}

	/**
	 * helper creating a string like '(?,?,?)' with the specified number of elements
	 * @param int $count
	 */
	protected function questionMarks($count) {
		$questionMarks = array();
		for($i = 0; $i < $count; $i++){
			$questionMarks[] = '?';
		}
		return '(' . implode(',', $questionMarks) . ')';
	}

}
