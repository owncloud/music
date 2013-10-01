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

namespace OCA\Music\BusinessLayer;

use \OCA\Music\AppFramework\Db\DoesNotExistException;
use \OCA\Music\AppFramework\Db\MultipleObjectsReturnedException;

use \OCA\Music\Db\Artist;


class ArtistBusinessLayerTest extends \OCA\Music\AppFramework\Utility\TestUtility {

	private $api;
	private $mapper;
	private $artistBusinessLayer;


	protected function setUp(){
		$this->api = $this->getAPIMock();
		$this->mapper = $this->getMockBuilder('\OCA\Music\Db\ArtistMapper')
			->disableOriginalConstructor()
			->getMock();
		$this->artistBusinessLayer = new ArtistBusinessLayer($this->mapper, $this->api);
		$this->userId = 'john';
	}

	public function testFindMultipleById(){
		$artistIds = array(1,2,3);
		$response = '';
		$this->mapper->expects($this->once())
			->method('findMultipleById')
			->with($this->equalTo($artistIds),
					$this->equalTo($this->userId))
			->will($this->returnValue($response));

		$result = $this->artistBusinessLayer->findMultipleById(
			$artistIds,
			$this->userId);
		$this->assertEquals($response, $result);
	}

	public function testDeleteById(){
		$artistIds = array(1, 2, 3);

		$this->mapper->expects($this->once())
			->method('deleteById')
			->with($this->equalTo($artistIds));

		$this->artistBusinessLayer->deleteById($artistIds);
	}

	public function testAddArtistIfNotExistAdd(){
		$name = 'test';

		$artist = new Artist();
		$artist->setName($name);
		$artist->setId(1);

		$this->mapper->expects($this->once())
			->method('findByName')
			->with($this->equalTo($name),
				$this->equalTo($this->userId))
			->will($this->throwException(new DoesNotExistException('bla')));

		$this->mapper->expects($this->once())
			->method('insert')
			->will($this->returnValue($artist));

		$result = $this->artistBusinessLayer->addArtistIfNotExist($name, $this->userId);
		$this->assertEquals($artist, $result);
	}

	public function testAddArtistIfNotExistNoAdd(){
		$name = 'test';

		$artist = new Artist();
		$artist->setName($name);
		$artist->setId(1);

		$this->mapper->expects($this->once())
			->method('findByName')
			->with($this->equalTo($name),
				$this->equalTo($this->userId))
			->will($this->returnValue($artist));

		$this->mapper->expects($this->never())
			->method('insert');

		$result = $this->artistBusinessLayer->addArtistIfNotExist($name, $this->userId);
		$this->assertEquals($artist, $result);
	}

	public function testAddArtistIfNotExistException(){
		$name = 'test';

		$this->mapper->expects($this->once())
			->method('findByName')
			->with($this->equalTo($name),
				$this->equalTo($this->userId))
			->will($this->throwException(new MultipleObjectsReturnedException('bla')));

		$this->mapper->expects($this->never())
			->method('insert');

		$this->setExpectedException('\OCA\Music\BusinessLayer\BusinessLayerException');
		$this->artistBusinessLayer->addArtistIfNotExist($name, $this->userId);
	}
}


