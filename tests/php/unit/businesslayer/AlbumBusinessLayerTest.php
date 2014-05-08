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

use \OCA\Music\AppFramework\Db\DoesNotExistException;
use \OCA\Music\AppFramework\Db\MultipleObjectsReturnedException;

use \OCA\Music\Db\Album;


class AlbumBusinessLayerTest extends \PHPUnit_Framework_TestCase {

	private $mapper;
	private $logger;
	private $albumBusinessLayer;
	private $userId;
	private $albums;
	private $artistIds;


	protected function setUp(){
		$this->mapper = $this->getMockBuilder('\OCA\Music\Db\AlbumMapper')
			->disableOriginalConstructor()
			->getMock();
		$this->logger = $this->getMockBuilder('\OCA\Music\AppFramework\Core\Logger')
			->disableOriginalConstructor()
			->getMock();
		$this->albumBusinessLayer = new AlbumBusinessLayer($this->mapper, $this->logger);
		$this->userId = 'jack';
		$album1 = new Album();
		$album2 = new Album();
		$album3 = new Album();
		$album1->setId(1);
		$album2->setId(2);
		$album3->setId(3);
		$this->albums = array($album1, $album2, $album3);
		$this->albumsByArtist3 = array($album1, $album2);
		$this->artistIds = array(
			1 => array(3, 5, 7),
			2 => array(3, 7, 9),
			3 => array(9, 13)
		);

		$album1->setArtistIds($this->artistIds[1]);
		$album2->setArtistIds($this->artistIds[1]);
		$album3->setArtistIds($this->artistIds[1]);
		$this->response = array($album1, $album2, $album3);
		$this->responseByArtist3 = array($album1, $album2);
	}

	public function testFindAll(){
		$this->mapper->expects($this->once())
			->method('findAll')
			->with($this->equalTo($this->userId))
			->will($this->returnValue($this->albums));
		$this->mapper->expects($this->exactly(1))
			->method('getAlbumArtistsByAlbumId')
			->with($this->equalTo(array(1,2,3)))
			->will($this->returnValue($this->artistIds));

		$result = $this->albumBusinessLayer->findAll($this->userId);
		$this->assertEquals($this->response, $result);
	}

	public function testFindAllWithoutResult(){
		$this->mapper->expects($this->once())
			->method('findAll')
			->with($this->equalTo($this->userId))
			->will($this->returnValue(array()));

		$result = $this->albumBusinessLayer->findAll($this->userId);
		$this->assertEquals(array(), $result);
	}

	public function testFind(){
		$albumId = 2;

		$this->mapper->expects($this->once())
			->method('find')
			->with($this->equalTo($albumId), $this->equalTo($this->userId))
			->will($this->returnValue($this->albums[$albumId-1]));
		$this->mapper->expects($this->exactly(1))
			->method('getAlbumArtistsByAlbumId')
			->with($this->equalTo(array($albumId)))
			->will($this->returnValue(array($albumId => $this->artistIds[$albumId-1])));

		$result = $this->albumBusinessLayer->find($albumId, $this->userId);
		$this->assertEquals($this->response[$albumId-1], $result);
	}

	public function testFindAllByArtist(){
		$artistId = 3;

		$this->mapper->expects($this->once())
			->method('findAllByArtist')
			->with($this->equalTo($artistId))
			->will($this->returnValue($this->albumsByArtist3));
		$this->mapper->expects($this->exactly(1))
			->method('getAlbumArtistsByAlbumId')
			->with($this->equalTo(array(1, 2)))
			->will($this->returnValue(array(
				1 => $this->artistIds[1],
				2 => $this->artistIds[2]
			)));

		$result = $this->albumBusinessLayer->findAllByArtist($artistId, $this->userId);
		$this->assertEquals($this->responseByArtist3, $result);
	}

	public function testDeleteById(){
		$albumIds = array(1, 2, 3);

		$this->mapper->expects($this->once())
			->method('deleteById')
			->with($this->equalTo($albumIds));

		$this->albumBusinessLayer->deleteById($albumIds);
	}

	public function testUpdateCover(){
		$coverFileId = 1;
		$parentFolderId = 2;

		$this->mapper->expects($this->once())
			->method('updateCover')
			->with($this->equalTo($coverFileId), $this->equalTo($parentFolderId));

		$this->albumBusinessLayer->updateCover($coverFileId, $parentFolderId);
	}

	public function testAddAlbumIfNotExistAdd(){
		$name = 'test';
		$year = 2002;
		$artistId = 1;

		$this->mapper->expects($this->once())
			->method('findAlbum')
			->with($this->equalTo($name),
				$this->equalTo($year),
				$this->equalTo($artistId),
				$this->equalTo($this->userId))
			->will($this->throwException(new DoesNotExistException('bla')));

		$this->mapper->expects($this->once())
			->method('insert')
			->will($this->returnValue($this->albums[0]));

		$album = $this->albumBusinessLayer->addAlbumIfNotExist($name, $year, $artistId, $this->userId);
		$this->assertEquals($this->albums[0], $album);
	}

	public function testAddAlbumIfNotExistNoAdd(){
		$name = 'test';
		$year = 2002;
		$artistId = 1;

		$this->mapper->expects($this->once())
			->method('findAlbum')
			->with($this->equalTo($name),
				$this->equalTo($year),
				$this->equalTo($artistId),
				$this->equalTo($this->userId))
			->will($this->returnValue($this->albums[0]));

		$this->mapper->expects($this->never())
			->method('insert');

		$album = $this->albumBusinessLayer->addAlbumIfNotExist($name, $year, $artistId, $this->userId);
		$this->assertEquals($this->albums[0], $album);
	}

	public function testAddAlbumIfNotExistException(){
		$name = 'test';
		$year = 2002;
		$artistId = 1;

		$this->mapper->expects($this->once())
			->method('findAlbum')
			->with($this->equalTo($name),
				$this->equalTo($year),
				$this->equalTo($artistId),
				$this->equalTo($this->userId))
			->will($this->throwException(new MultipleObjectsReturnedException('bla')));

		$this->mapper->expects($this->never())
			->method('insert');

		$this->setExpectedException('\OCA\Music\AppFramework\BusinessLayer\BusinessLayerException');
		$this->albumBusinessLayer->addAlbumIfNotExist($name, $year, $artistId, $this->userId);
	}

	public function testRemoveAndFindCovers(){
		$fileId = 1;

		$this->mapper->expects($this->once())
			->method('removeCover')
			->with($this->equalTo($fileId));

		$this->mapper->expects($this->once())
			->method('getAlbumsWithoutCover')
			->will($this->returnValue(array(array('albumId' => 2, 'parentFolderId' => 3))));

		$this->mapper->expects($this->once())
			->method('findAlbumCover')
			->with($this->equalTo(2), $this->equalTo(3));

		$this->albumBusinessLayer->removeCover($fileId);

	}
}
