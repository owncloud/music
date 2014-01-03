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

use \OCA\Music\Db\Track;


class TrackBusinessLayerTest extends \OCA\Music\AppFramework\Utility\TestUtility {

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
		$this->trackBusinessLayer = new TrackBusinessLayer($this->mapper, $this->api);
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

	public function testAddTrackIfNotExistAdd(){
		$title = 'test';
		$fileId = 2;

		$track = new Track();
		$track->setTitle($title);
		$track->setId(1);

		$this->mapper->expects($this->once())
			->method('findByFileId')
			->with($this->equalTo($fileId),
				$this->equalTo($this->userId))
			->will($this->throwException(new DoesNotExistException('bla')));

		$this->mapper->expects($this->once())
			->method('insert')
			->will($this->returnValue($track));

		$result = $this->trackBusinessLayer->addTrackIfNotExist(null, null, null, null, $fileId, null, $this->userId);
		$this->assertEquals($track, $result);
	}

	public function testAddTrackIfNotExistNoAdd(){
		$title = 'test';
		$fileId = 2;

		$track = new Track();
		$track->setTitle($title);
		$track->setId(1);

		$this->mapper->expects($this->once())
			->method('findByFileId')
			->with($this->equalTo($fileId),
				$this->equalTo($this->userId))
			->will($this->returnValue($track));

		$this->mapper->expects($this->never())
			->method('insert');

		$this->mapper->expects($this->once())
			->method('update')
			->will($this->returnValue($track));

		$result = $this->trackBusinessLayer->addTrackIfNotExist(null, null, null, null, $fileId, null, $this->userId);
		$this->assertEquals($track, $result);
	}

	public function testAddTrackIfNotExistException(){
		$title = 'test';
		$fileId = 2;

		$this->mapper->expects($this->once())
			->method('findByFileId')
			->with($this->equalTo($fileId),
				$this->equalTo($this->userId))
			->will($this->throwException(new MultipleObjectsReturnedException('bla')));

		$this->mapper->expects($this->never())
			->method('insert');

		$this->setExpectedException('\OCA\Music\BusinessLayer\BusinessLayerException');
		$this->trackBusinessLayer->addTrackIfNotExist(null, null, null, null, $fileId, null, $this->userId);
	}

	public function testDeleteTrackEmpty(){
		$fileId = 2;

		$this->mapper->expects($this->once())
			->method('findAllByFileId')
			->with($this->equalTo($fileId))
			->will($this->returnValue(array()));

		$this->mapper->expects($this->never())
			->method('delete');

		$this->mapper->expects($this->never())
			->method('countByArtist');

		$this->mapper->expects($this->never())
			->method('countByAlbum');

		$result = $this->trackBusinessLayer->deleteTrack($fileId, $this->userId);
		$this->assertEquals(array('albumIds'=>array(), 'artistIds' => array()), $result);
	}

	public function testDeleteTrackDeleteArtist(){
		$fileId = 2;

		$track = new Track();
		$track->setArtistId(2);
		$track->setAlbumId(3);
		$track->setId(1);

		$this->mapper->expects($this->once())
			->method('findAllByFileId')
			->with($this->equalTo($fileId))
			->will($this->returnValue(array($track)));

		$this->mapper->expects($this->once())
			->method('delete')
			->with($this->equalTo($track));

		$this->mapper->expects($this->once())
			->method('countByArtist')
			->with($this->equalTo(2),
				$this->equalTo($this->userId))
			->will($this->returnValue('0'));

		$this->mapper->expects($this->once())
			->method('countByAlbum')
			->with($this->equalTo(3),
				$this->equalTo($this->userId))
			->will($this->returnValue('1'));

		$result = $this->trackBusinessLayer->deleteTrack($fileId, $this->userId);
		$this->assertEquals(array('albumIds'=>array(), 'artistIds' => array(2)), $result);
	}

	public function testDeleteTrackDeleteAlbum(){
		$fileId = 2;

		$track = new Track();
		$track->setArtistId(2);
		$track->setAlbumId(3);
		$track->setId(1);

		$this->mapper->expects($this->once())
			->method('findAllByFileId')
			->with($this->equalTo($fileId))
			->will($this->returnValue(array($track)));

		$this->mapper->expects($this->once())
			->method('delete')
			->with($this->equalTo($track));

		$this->mapper->expects($this->once())
			->method('countByArtist')
			->with($this->equalTo(2),
				$this->equalTo($this->userId))
			->will($this->returnValue('1'));

		$this->mapper->expects($this->once())
			->method('countByAlbum')
			->with($this->equalTo(3),
				$this->equalTo($this->userId))
			->will($this->returnValue('0'));

		$result = $this->trackBusinessLayer->deleteTrack($fileId, $this->userId);
		$this->assertEquals(array('albumIds'=>array(3), 'artistIds' => array()), $result);
	}
}


