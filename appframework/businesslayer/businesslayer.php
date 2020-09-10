<?php
/**
 * ownCloud
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Alessandro Cosentino <cosenal@gmail.com>
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Alessandro Cosentino 2012
 * @copyright Bernhard Posselt 2012, 2014
 * @copyright Pauli Järvinen 2017 - 2020
 */

namespace OCA\Music\AppFramework\BusinessLayer;

use \OCA\Music\Db\BaseMapper;
use \OCA\Music\Db\SortBy;

use \OCP\AppFramework\Db\DoesNotExistException;
use \OCP\AppFramework\Db\MultipleObjectsReturnedException;

abstract class BusinessLayer {
	protected $mapper;

	public function __construct(BaseMapper $mapper) {
		$this->mapper = $mapper;
	}

	/**
	 * Update an entity in the database
	 * @param Entity $entity
	 */
	public function update($entity) {
		$this->mapper->update($entity);
	}

	/**
	 * Delete an entity
	 * @param int $id the id of the entity
	 * @param string $userId the name of the user for security reasons
	 * @throws BusinessLayerException if the entity does not exist or more than one entity exists
	 */
	public function delete($id, $userId) {
		$entity = $this->find($id, $userId);
		$this->mapper->delete($entity);
	}

	/**
	 * Deletes entities without specifying the owning user.
	 * This should never be called directly from the HTML API, but only in case
	 * we can actually trust the passed IDs (e.g. file deleted hook).
	 * @param array $ids the ids of the entities which should be deleted
	 */
	public function deleteById($ids) {
		if (\count($ids) > 0) {
			$this->mapper->deleteById($ids);
		}
	}

	/**
	 * Finds an entity by id
	 * @param int $id the id of the entity
	 * @param string $userId the name of the user for security reasons
	 * @throws BusinessLayerException if the entity does not exist or more than one entity exists
	 * @return Entity the entity
	 */
	public function find($id, $userId) {
		try {
			return $this->mapper->find($id, $userId);
		} catch (DoesNotExistException $ex) {
			throw new BusinessLayerException($ex->getMessage());
		} catch (MultipleObjectsReturnedException $ex) {
			throw new BusinessLayerException($ex->getMessage());
		}
	}

	/**
	 * Finds an entity by id, or returns an empty entity instance if the requested one is not found
	 * @param int $id the id of the entity
	 * @param string $userId the name of the user for security reasons
	 * @return Entity the entity
	 */
	public function findOrDefault($id, $userId) {
		try {
			return $this->find($id, $userId);
		} catch (BusinessLayerException $ex) {
			return $this->mapper->createEntity();
		}
	}

	/**
	 * Find all entities matching the given IDs.
	 * Specifying the user is optional; if omitted, the caller should make sure that
	 * user's data is not leaked to unauthorized users.
	 * @param integer[] $ids  IDs of the entities to be found
	 * @param string|null $userId
	 * @return Entity[]
	 */
	public function findById($ids, $userId=null) {
		if (\count($ids) > 0) {
			return $this->mapper->findById($ids, $userId);
		} else {
			return [];
		}
	}

	/**
	 * Finds all entities
	 * @param string $userId the name of the user
	 * @param integer $sortBy sort order of the result set
	 * @param integer|null $limit
	 * @param integer|null $offset
	 * @return Entity[]
	 */
	public function findAll($userId, $sortBy=SortBy::None, $limit=null, $offset=null) {
		return $this->mapper->findAll($userId, $sortBy, $limit, $offset);
	}

	/**
	 * Return all entities with name matching the search criteria
	 * @param string $name
	 * @param string $userId
	 * @param bool $fuzzy
	 * @param integer|null $limit
	 * @param integer|null $offset
	 * @return Entity[]
	 */
	public function findAllByName($name, $userId, $fuzzy = false, $limit=null, $offset=null) {
		return $this->mapper->findAllByName($name, $userId, $fuzzy, $limit, $offset);
	}

	/**
	 * Find all starred entities
	 * @param string $userId
	 * @param integer|null $limit
	 * @param integer|null $offset
	 * @return Entity[]
	 */
	public function findAllStarred($userId, $limit=null, $offset=null) {
		return $this->mapper->findAllStarred($userId, $limit, $offset);
	}

	/**
	 * Set the given entities as "starred" on this date
	 * @param integer[] $ids
	 * @param string $userId
	 * @return int number of modified entities
	 */
	public function setStarred($ids, $userId) {
		if (\count($ids) > 0) {
			return $this->mapper->setStarredDate(new \DateTime(), $ids, $userId);
		} else {
			return 0;
		}
	}

	/**
	 * Remove the "starred" status of the given entities
	 * @param integer[] $ids
	 * @param string $userId
	 * @return int number of modified entities
	 */
	public function unsetStarred($ids, $userId) {
		if (\count($ids) > 0) {
			return $this->mapper->setStarredDate(NULL, $ids, $userId);
		} else {
			return 0;
		}
	}

	/**
	 * Tests if entity with given ID and user ID exists in the database
	 * @param int $id
	 * @param string $userId
	 * @return bool
	 */
	public function exists($id, $userId) {
		return $this->mapper->exists($id, $userId);
	}

	/**
	 * Get the number of entities
	 * @param string $userId
	 */
	public function count($userId) {
		return $this->mapper->count($userId);
	}

}
