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
 * @copyright Pauli Järvinen 2017, 2018
 */

namespace OCA\Music\AppFramework\BusinessLayer;

use \OCA\Music\Db\BaseMapper;
use \OCA\Music\Db\SortBy;

use \OCP\AppFramework\Db\DoesNotExistException;
use \OCP\AppFramework\Db\MultipleObjectsReturnedException;


abstract class BusinessLayer {

	protected $mapper;

	public function __construct(BaseMapper $mapper){
		$this->mapper = $mapper;
	}

	/**
	 * Delete an entity
	 * @param int $id the id of the entity
	 * @param string $userId the name of the user for security reasons
	 * @throws DoesNotExistException if the entity does not exist
	 * @throws MultipleObjectsReturnedException if more than one entity exists
	 */
	public function delete($id, $userId){
		$entity = $this->find($id, $userId);
		$this->mapper->delete($entity);
	}

	/**
	 * Deletes entities without specifying the owning user.
	 * This should never be called directly from the HTML API, but only in case
	 * we can actually trust the passed IDs (e.g. file deleted hook).
	 * @param array $ids the ids of the entities which should be deleted
	 */
	public function deleteById($ids){
		$this->mapper->deleteById($ids);
	}

	/**
	 * Finds an entity by id
	 * @param int $id the id of the entity
	 * @param string $userId the name of the user for security reasons
	 * @throws BusinessLayerException if the entity does not exist or more than one entity exists
	 * @return Entity the entity
	 */
	public function find($id, $userId){
		try {
			return $this->mapper->find($id, $userId);
		} catch(DoesNotExistException $ex){
			throw new BusinessLayerException($ex->getMessage());
		} catch(MultipleObjectsReturnedException $ex){
			throw new BusinessLayerException($ex->getMessage());
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
	public function findById($ids, $userId=null){
		return $this->mapper->findById($ids, $userId);
	}

	/**
	 * Finds all entities
	 * @param string $userId the name of the user
	 * @param SortBy $sortBy sort order of the result set
	 * @param integer $limit
	 * @param integer $offset
	 * @return Entity[]
	 */
	public function findAll($userId, $sortBy=SortBy::None, $limit=null, $offset=null){
		return $this->mapper->findAll($userId, $sortBy, $limit, $offset);
	}

	/**
	 * Return all entities with name matching the search criteria
	 * @param string $name
	 * @param string $userId
	 * @param bool $fuzzy
	 * @return Entity[]
	 */
	public function findAllByName($name, $userId, $fuzzy = false){
		return $this->mapper->findAllByName($name, $userId, $fuzzy);
	}

	/**
	 * Get the number of entities
	 * @param string $userId
	 */
	public function count($userId){
		return $this->mapper->count($userId);
	}
}
