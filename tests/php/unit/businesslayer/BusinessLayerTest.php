<?php

/**
 * ownCloud - Music app
 *
 * @author Alessandro Cosentino
 * @author Bernhard Posselt
 * @author Morris Jobke
 * @copyright 2012 Alessandro Cosentino <cosenal@gmail.com>
 * @copyright 2012 Bernhard Posselt <nukeawhale@gmail.com>
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

class TestBusinessLayer extends BusinessLayer {
	public function __construct($mapper, $api){
		parent::__construct($mapper, $api);
	}
}


class BusinessLayerTest extends \OCA\Music\AppFramework\Utility\TestUtility {

	protected $api;
	protected $mapper;
	protected $musicBusinessLayer;

	protected function setUp(){
		$this->api = $this->getAPIMock();
		$this->mapper = $this->getMockBuilder('\OCA\Music\Db\TrackMapper')
			->disableOriginalConstructor()
			->getMock();
		$this->musicBusinessLayer = new TestBusinessLayer($this->mapper, $this->api);
	}

	public function testFind(){
		$id = 3;
		$user = 'ken';

		$this->mapper->expects($this->once())
			->method('find')
			->with($this->equalTo($id), $this->equalTo($user));

		$this->musicBusinessLayer->find($id, $user);
	}


	public function testFindDoesNotExist(){
		$ex = new DoesNotExistException('hi');

		$this->mapper->expects($this->once())
			->method('find')
			->will($this->throwException($ex));

		$this->setExpectedException('\OCA\Music\BusinessLayer\BusinessLayerException');
		$this->musicBusinessLayer->find(1, '');
	}


	public function testFindMultiple(){
		$ex = new MultipleObjectsReturnedException('hi');

		$this->mapper->expects($this->once())
			->method('find')
			->will($this->throwException($ex));

		$this->setExpectedException('\OCA\Music\BusinessLayer\BusinessLayerException');
		$this->musicBusinessLayer->find(1, '');
	}


	public function testFindAll(){
		$user = 'ken';
		$response = '';

		$this->mapper->expects($this->once())
			->method('findAll')
			->with($this->equalTo($user))
			->will($this->returnValue($response));

		$result = $this->musicBusinessLayer->findAll($user);
		$this->assertEquals($response, $result);
	}

}
