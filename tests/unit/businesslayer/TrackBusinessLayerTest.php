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

use \OCA\Music\Db\Track;


class TrackBusinessLayerTest extends \OCA\AppFramework\Utility\TestUtility {

	private $api;
	private $mapper;
	private $trackBusinessLayer;
	private $userId;
	private $artistId;
	private $albumId;


	protected function setUp(){
		$this->api = $this->getAPIMock();
		$this->mapper = $this->getMockBuilder('\OCA\Music\Db\TrackMapper')
			->disableOriginalConstructor()
			->getMock();
		$this->trackBusinessLayer = new TrackBusinessLayer($this->mapper);
		$this->userId = 'jack';
		$this->artistId = 3;
		$this->albumId = 3;
	}

	public function testFindAllByArtist(){
		$response = '';
		$this->mapper->expects($this->once())
			->method('findAllByArtist')
			->with($this->equalTo($this->artistId),
					$this->equalTo($this->userId))
			->will($this->returnValue($response));

		$result = $this->trackBusinessLayer->findAllByArtist(
			$this->artistId,
			$this->userId);
		$this->assertEquals($response, $result);
	}

	public function testFindAllByAlbum(){
		$response = '';
		$this->mapper->expects($this->once())
			->method('findAllByAlbum')
			->with($this->equalTo($this->albumId),
					$this->equalTo($this->userId))
			->will($this->returnValue($response));

		$result = $this->trackBusinessLayer->findAllByAlbum(
			$this->albumId,
			$this->userId);
		$this->assertEquals($response, $result);
	}
}


