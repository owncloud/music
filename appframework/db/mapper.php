<?php

/**
 * ownCloud - App Framework
 *
 * @author Bernhard Posselt
 * @author Morris Jobke
 * @copyright 2012 Bernhard Posselt dev@bernhard-posselt.com
 * @copyright 2013 Morris Jobke morris.jobke@gmail.com
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */


namespace OCA\Music\AppFramework\Db;

use \OCA\Music\Core\API;


/**
 * Simple parent class for inheriting your data access layer from. This class
 * may be subject to change in the future
 */
abstract class Mapper {

	private $tableName;

	/**
	 * @param API $api Instance of the API abstraction layer
	 * @param string $tableName the name of the table. set this to allow entity
	 * queries without using sql
	 */
	public function __construct(API $api, $tableName){
		$this->api = $api;
		$this->tableName = '*PREFIX*' . $tableName;
	}


	/**
	 * @return string the table name
	 */
	public function getTableName(){
		return $this->tableName;
	}


	/**
	 * Deletes an entity from the table
	 * @param Entity $entity the entity that should be deleted
	 */
	public function delete(Entity $entity){
		$sql = 'DELETE FROM `' . $this->tableName . '` WHERE `id` = ?';
		$this->execute($sql, array($entity->getId()));
	}


	/**
	 * Creates a new entry in the db from an entity
	 * @param Entity $enttiy the entity that should be created
	 * @return the saved entity with the set id
	 */
	public function insert(Entity $entity){
		// get updated fields to save, fields have to be set using a setter to
		// be saved
		$properties = $entity->getUpdatedFields();
		$values = '';
		$columns = '';
		$params = array();

		// build the fields
		$i = 0;
		foreach($properties as $property => $updated) {
			$column = $entity->propertyToColumn($property);
			$getter = 'get' . ucfirst($property);

			$columns .= '`' . $column . '`';
			$values .= '?';

			// only append colon if there are more entries
			if($i < count($properties)-1){
				$columns .= ',';
				$values .= ',';
			}

			array_push($params, $entity->$getter());
			$i++;

		}

		$sql = 'INSERT INTO `' . $this->tableName . '`(' .
				$columns . ') VALUES(' . $values . ')';

		$this->execute($sql, $params);

		$entity->setId((int) $this->api->getInsertId($this->tableName));
		return $entity;
	}



	/**
	 * Updates an entry in the db from an entity
	 * @throws \InvalidArgumentException if entity has no id
	 * @param Entity $enttiy the entity that should be created
	 */
	public function update(Entity $entity){
		// entity needs an id
		$id = $entity->getId();
		if($id === null){
			throw new \InvalidArgumentException(
				'Entity which should be updated has no id');
		}

		// get updated fields to save, fields have to be set using a setter to
		// be saved
		$properties = $entity->getUpdatedFields();
		// dont update the id field
		unset($properties['id']);

		$columns = '';
		$params = array();

		// build the fields
		$i = 0;
		foreach($properties as $property => $updated) {

			$column = $entity->propertyToColumn($property);
			$getter = 'get' . ucfirst($property);

			$columns .= '`' . $column . '` = ?';

			// only append colon if there are more entries
			if($i < count($properties)-1){
				$columns .= ',';
			}

			array_push($params, $entity->$getter());
			$i++;
		}

		$sql = 'UPDATE `' . $this->tableName . '` SET ' .
				$columns . ' WHERE `id` = ?';
		array_push($params, $id);

		$this->execute($sql, $params);
	}


	/**
	 * Returns an db result and throws exceptions when there are more or less
	 * results
	 * @see findEntity
	 * @param string $sql the sql query
	 * @param array $params the parameters of the sql query
	 * @throws DoesNotExistException if the item does not exist
	 * @throws MultipleObjectsReturnedException if more than one item exist
	 * @return array the result as row
	 */
	protected function findOneQuery($sql, $params){
		$result = $this->execute($sql, $params);
		$row = $result->fetchRow();

		if($row === false || $row === null){
			throw new DoesNotExistException('No matching entry found');
		}
		$row2 = $result->fetchRow();
		//MDB2 returns null, PDO and doctrine false when no row is available
		if( ! ($row2 === false || $row2 === null )) {
			throw new MultipleObjectsReturnedException('More than one result');
		} else {
			return $row;
		}
	}


	/**
	 * Runs an sql query
	 * @param string $sql the prepare string
	 * @param array $params the params which should replace the ? in the sql query
	 * @param int $limit the maximum number of rows
	 * @param int $offset from which row we want to start
	 * @return \PDOStatement the database query result
	 */
	protected function execute($sql, array $params=array(), $limit=null, $offset=null){
		$query = $this->api->prepareQuery($sql, $limit, $offset);
		return $query->execute($params);
	}

	/**
	 * Creates an entity from a row. Automatically determines the entity class
	 * from the current mapper name (MyEntityMapper -> MyEntity)
	 * @param array $row the row which should be converted to an entity
	 * @return Entity the entity
	 */
	protected function mapRowToEntity($row) {
		$class = get_class($this);
		$entityName = str_replace('Mapper', '', $class);
		$entity = new $entityName();
		return $entity->fromRow($row);
	}

	/**
	 * Runs a sql query and returns an array of entities
	 * @param string $sql the prepare string
	 * @param array $params the params which should replace the ? in the sql query
	 * @param int $limit the maximum number of rows
	 * @param int $offset from which row we want to start
	 * @return array all fetched entities
	 */
	protected function findEntities($sql, array $params=array(), $limit=null, $offset=null) {
		$result = $this->execute($sql, $params, $limit, $offset);

		$entities = array();
		while($row = $result->fetchRow()){
			$entity = $this->mapRowToEntity($row);
			array_push($entities, $entity);
		}
		return $entities;
	}

	/**
	 * Returns an db result and throws exceptions when there are more or less
	 * results
	 * @param string $sql the sql query
	 * @param array $params the parameters of the sql query
	 * @throws DoesNotExistException if the item does not exist
	 * @throws MultipleObjectsReturnedException if more than one item exist
	 * @return array the result as row
	 */
	protected function findEntity($sql, $params){
		$result = $this->execute($sql, $params);
		$row = $result->fetchRow();

		if($row === false || $row === null){
			throw new DoesNotExistException('No matching entry found');
		}
		$row2 = $result->fetchRow();
		//MDB2 returns null, PDO and doctrine false when no row is available
		if( ! ($row2 === false || $row2 === null )) {
			throw new MultipleObjectsReturnedException('More than one result');
		} else {
			return $this->mapRowToEntity($row);
		}
	}
}
