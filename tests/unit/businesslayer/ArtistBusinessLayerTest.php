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

require_once(__DIR__ . "/../../classloader.php");

use \OCA\AppFramework\Db\DoesNotExistException;

use \OCA\Music\Db\Artist;


class ArtistBusinessLayerTest extends \OCA\AppFramework\Utility\TestUtility {

	private $api;
	private $mapper;
	private $artistBusinessLayer;


	protected function setUp(){
		$this->api = $this->getAPIMock();
		$this->mapper = $this->getMockBuilder('\OCA\Music\Db\ArtistMapper')
			->disableOriginalConstructor()
			->getMock();
		$this->artistBusinessLayer = new ArtistBusinessLayer($this->mapper);
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
}


