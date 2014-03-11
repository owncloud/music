<?php

/**
 * ownCloud - Music app
 *
 * @author Morris Jobke
 * @copyright 2013 Morris Jobke <morris.jobke@gmail.com>
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

namespace OCA\Music\Db;

class ArtistMapperTest extends \OCA\Music\AppFramework\Utility\MapperTestUtility {

	private $mapper;
	private $artists;

	private $userId = 'john';
	private $id = 5;
	private $rows;

	public function setUp()
	{
		$this->beforeEach();

		$this->mapper = new ArtistMapper($this->api);

		// create mock items
		$artist1 = new Artist();
		$artist1->setName('Test name');
		$artist1->setImage('http://example.org');
		$artist1->resetUpdatedFields();
		$artist2 = new Artist();
		$artist2->setName('Test name2');
		$artist2->setImage('http://example.org/1');
		$artist2->resetUpdatedFields();
		$artistNull = new Artist();
		$artistNull->setName(null);
		$artistNull->resetUpdatedFields();

		$this->artists = array(
			$artist1,
			$artist2,
			$artistNull
		);

		$this->rows = array(
			array('id' => $this->artists[0]->getId(), 'name' => 'Test name', 'image' => 'http://example.org'),
			array('id' => $this->artists[1]->getId(), 'name' => 'Test name2', 'image' => 'http://example.org/1'),
			array('id' => $this->artists[2]->getId(), 'name' => null),
		);

	}


	private function makeSelectQuery($condition=null){
		return 'SELECT `artist`.`name`, `artist`.`image`, `artist`.`id` '.
			'FROM `*PREFIX*music_artists` `artist` '.
			'WHERE `artist`.`user_id` = ? ' . $condition;
	}

	public function testFind(){
		$sql = $this->makeSelectQuery('AND `artist`.`id` = ?');
		$this->setMapperResult($sql, array($this->userId, $this->id), array($this->rows[0]));
		$result = $this->mapper->find($this->id, $this->userId);
		$this->assertEquals($this->artists[0], $result);
	}

	public function testFindAll(){
		$sql = $this->makeSelectQuery();
		$this->setMapperResult($sql, array($this->userId), $this->rows);
		$result = $this->mapper->findAll($this->userId);
		$this->assertEquals($this->artists, $result);
	}

	public function testFindMultipleById(){
		$artistIds = array(1,3,5);
		$sql = $this->makeSelectQuery('AND `artist`.`id` IN (?,?,?)');
		$this->setMapperResult($sql, array($this->userId, 1, 3, 5), $this->rows);
		$result = $this->mapper->findMultipleById($artistIds, $this->userId);
		$this->assertEquals($this->artists, $result);
	}

	public function testFindByName(){
		$artistName = 'test';
		$sql = $this->makeSelectQuery('AND `artist`.`name` = ?');
		$this->setMapperResult($sql, array($this->userId, $artistName), array($this->rows[0]));
		$result = $this->mapper->findByName($artistName, $this->userId);
		$this->assertEquals($this->artists[0], $result);
	}

	public function testFindByNameIsNull(){
		$artistName = null;
		$sql = $this->makeSelectQuery('AND `artist`.`name` IS NULL');
		$this->setMapperResult($sql, array($this->userId), array($this->rows[2]));
		$result = $this->mapper->findByName($artistName, $this->userId);
		$this->assertEquals($this->artists[2], $result);
	}

	public function testDeleteByIdNone(){
		$artistIds = array();

		$this->api->expects($this->never())
			->method('prepareQuery');

		$this->mapper->deleteById($artistIds);
	}

	public function testDeleteById(){
		$artistIds = array(1, 2);

		$sql = 'DELETE FROM `*PREFIX*music_artists` WHERE `id` IN (?,?)';
		$this->setMapperResult($sql, $artistIds, array());

		$this->mapper->deleteById($artistIds);
	}

	public function testCount(){
		$sql = 'SELECT COUNT(*) FROM `*PREFIX*music_artists` WHERE `user_id` = ?';
		$this->setMapperResult($sql, array($this->userId), array(array('COUNT(*)' => 4)));
		$result = $this->mapper->count($this->userId);
		$this->assertEquals(4, $result);
	}

	public function testFindAllByName(){
		$sql = $this->makeSelectQuery('AND `artist`.`name` = ?');
		$this->setMapperResult($sql, array($this->userId, 123), array($this->rows[0]));
		$result = $this->mapper->findAllByName(123, $this->userId);
		$this->assertEquals(array($this->artists[0]), $result);
	}

	public function testFindAllByNameFuzzy(){
		$sql = $this->makeSelectQuery('AND LOWER(`artist`.`name`) LIKE LOWER(?)');
		$this->setMapperResult($sql, array($this->userId, '%test123test%'), array($this->rows[0]));
		$result = $this->mapper->findAllByName('test123test', $this->userId, true);
		$this->assertEquals(array($this->artists[0]), $result);
	}
}
