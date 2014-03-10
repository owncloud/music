<?php

/**
 * ownCloud - App Framework
 *
 * @author Bernhard Posselt
 * @copyright 2012 Bernhard Posselt dev@bernhard-posselt.com
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


namespace OCA\Music\AppFramework\Utility;

use OCA\Music\AppFramework\Core\Api;


/**
 * Simple utility class for testing mappers
 */
abstract class MapperTestUtility extends TestUtility {


	protected $api;


	/**
	 * Run this function before the actual test to either set or initialize the
	 * api. After this the api can be accessed by using $this->api
	 * @param \OCA\Music\AppFramework\Core\API $api the api mock
	 */
	protected function beforeEach(){
		$this->api = $this->getMock('\OCA\Music\Core\API',
			array('prepareQuery', 'getInsertId'),
			array('a'));
	}


	/**
	 * Create mocks and set expected results for database queries
	 * @param string $sql the sql query that you expect to receive
	 * @param array $arguments the expected arguments for the prepare query
	 * method
	 * @param array $returnRows the rows that should be returned for the result
	 * of the database query. If not provided, it wont be assumed that fetchRow
	 * will be called on the result
	 */
	protected function setMapperResult($sql, $arguments=array(), $returnRows=array(),
		$limit=null, $offset=null){

		$pdoResult = $this->getMock('Result',
			array('fetchRow'));

		$iterator = new ArgumentIterator($returnRows);
		$pdoResult->expects($this->any())
			->method('fetchRow')
			->will($this->returnCallback(
				function() use ($iterator){
					return $iterator->next();
			  	}
			));

		$query = $this->getMock('Query',
			array('execute'));
		$query->expects($this->once())
			->method('execute')
			->with($this->equalTo($arguments))
			->will($this->returnValue($pdoResult));

		if($limit === null && $offset === null) {
			$this->api->expects($this->once())
				->method('prepareQuery')
				->with($this->equalTo($sql))
				->will(($this->returnValue($query)));
		} elseif($limit !== null && $offset === null) {
			$this->api->expects($this->once())
				->method('prepareQuery')
				->with($this->equalTo($sql), $this->equalTo($limit))
				->will(($this->returnValue($query)));
		} elseif($limit === null && $offset !== null) {
			$this->api->expects($this->once())
				->method('prepareQuery')
				->with($this->equalTo($sql),
					$this->equalTo(null),
					$this->equalTo($offset))
				->will(($this->returnValue($query)));
		} else  {
			$this->api->expects($this->once())
				->method('prepareQuery')
				->with($this->equalTo($sql),
					$this->equalTo($limit),
					$this->equalTo($offset))
				->will(($this->returnValue($query)));
		}

	}

}


class ArgumentIterator {

	private $arguments;

	public function __construct($arguments){
		$this->arguments = $arguments;
	}

	public function next(){
		$result = array_shift($this->arguments);
		if($result === null){
			return false;
		} else {
			return $result;
		}
	}
}

