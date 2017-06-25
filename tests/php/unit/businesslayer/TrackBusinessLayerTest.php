<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @copyright Morris Jobke 2013, 2014
 */

namespace OCA\Music\BusinessLayer;

use \OCP\AppFramework\Db\DoesNotExistException;
use \OCP\AppFramework\Db\MultipleObjectsReturnedException;

use \OCA\Music\Db\Track;


class TrackBusinessLayerTest extends \PHPUnit_Framework_TestCase {

	private $mapper;
	private $logger;
	private $trackBusinessLayer;
	private $userId;
	private $artistId;
	private $albumId;


	protected function setUp(){
		$this->mapper = $this->getMockBuilder('\OCA\Music\Db\TrackMapper')
			->disableOriginalConstructor()
			->getMock();
		$this->logger = $this->getMockBuilder('\OCA\Music\AppFramework\Core\Logger')
			->disableOriginalConstructor()
			->getMock();
		$this->trackBusinessLayer = new TrackBusinessLayer($this->mapper, $this->logger);
		$this->userId = 'jack';
		$this->artistId = 3;
		$this->albumId = 3;
		$this->fileId = 2;
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

	public function testFindByFileId(){
		$response = '';
		$this->mapper->expects($this->once())
			->method('findByFileId')
			->with($this->equalTo($this->fileId),
					$this->equalTo($this->userId))
			->will($this->returnValue($response));

		$result = $this->trackBusinessLayer->findByFileId(
			$this->fileId,
			$this->userId);
		$this->assertEquals($response, $result);
	}

	public function testAddOrUpdateTrack(){
		$title = 'test';
		$fileId = 2;

		$track = new Track();
		$track->setTitle($title);
		$track->setId(1);

		$this->mapper->expects($this->once())
			->method('insertOrUpdate')
			->will($this->returnValue($track));

		$result = $this->trackBusinessLayer->addOrUpdateTrack(null, null, null, null, $fileId, null, $this->userId);
		$this->assertEquals($track, $result);
	}

	public function testDeleteTracksEmpty(){
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

		$result = $this->trackBusinessLayer->deleteTracks($fileId, $this->userId);
		$this->assertEquals(false, $result);
	}

	public function testDeleteTracksDeleteArtist(){
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
			->with($this->equalTo(2))
			->will($this->returnValue('0'));

		$this->mapper->expects($this->once())
			->method('countByAlbum')
			->with($this->equalTo(3))
			->will($this->returnValue('1'));

		$result = $this->trackBusinessLayer->deleteTracks($fileId, $this->userId);
		$this->assertEquals([],              $result['obsoleteAlbums']);
		$this->assertEquals([2],             $result['obsoleteArtists']);
		$this->assertEquals([3],             $result['remainingAlbums']);
		$this->assertEquals([],              $result['remainingArtists']);
		$this->assertEquals([$this->userId], $result['affectedUsers']);
	}

	public function testDeleteTracksDeleteAlbum(){
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
			->with($this->equalTo(2))
			->will($this->returnValue('1'));

		$this->mapper->expects($this->once())
			->method('countByAlbum')
			->with($this->equalTo(3))
			->will($this->returnValue('0'));

		$result = $this->trackBusinessLayer->deleteTracks($fileId, $this->userId);
		$this->assertEquals([3],             $result['obsoleteAlbums']);
		$this->assertEquals([],              $result['obsoleteArtists']);
		$this->assertEquals([],              $result['remainingAlbums']);
		$this->assertEquals([2],             $result['remainingArtists']);
		$this->assertEquals([$this->userId], $result['affectedUsers']);
	}
}


