<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2013, 2014
 * @copyright Pauli Järvinen 2016 - 2021
 */

namespace OCA\Music\BusinessLayer;

use \OCA\Music\Db\Album;

class AlbumBusinessLayerTest extends \PHPUnit\Framework\TestCase {
	private $mapper;
	private $logger;
	private $albumBusinessLayer;
	private $userId;
	private $albums;
	private $artistIds;

	protected function setUp() : void {
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
		$this->albums = [$album1, $album2, $album3];
		$this->albumsByArtist3 = [$album1, $album2];
		$this->artistIds = [
			1 => [3, 5, 7],
			2 => [3, 7, 9],
			3 => [9, 13]
		];

		$album1->setArtistIds($this->artistIds[1]);
		$album2->setArtistIds($this->artistIds[1]);
		$album3->setArtistIds($this->artistIds[1]);
		$this->response = [$album1, $album2, $album3];
		$this->responseByArtist3 = [$album1, $album2];
	}

	public function testFindAll() {
		$this->mapper->expects($this->once())
			->method('findAll')
			->with($this->equalTo($this->userId))
			->will($this->returnValue($this->albums));
		$this->mapper->expects($this->exactly(1))
			->method('getPerformingArtistsByAlbumId')
			->with($this->equalTo(null),
					$this->equalTo($this->userId))
			->will($this->returnValue($this->artistIds));
		$this->mapper->expects($this->exactly(1))
			->method('getYearsByAlbumId')
			->with($this->equalTo(null),
					$this->equalTo($this->userId))
			->will($this->returnValue([]));
		$this->mapper->expects($this->exactly(1))
			->method('getDiscCountByAlbumId')
			->with($this->equalTo(null),
					$this->equalTo($this->userId))
			->will($this->returnValue([1 => 1, 2 => 1, 3 => 1]));
		$this->mapper->expects($this->exactly(1))
			->method('getGenresByAlbumId')
			->with($this->equalTo(null),
					$this->equalTo($this->userId))
			->will($this->returnValue([]));

		$result = $this->albumBusinessLayer->findAll($this->userId);
		$this->assertEquals($this->response, $result);
	}

	public function testFindAllWithoutResult() {
		$this->mapper->expects($this->once())
			->method('findAll')
			->with($this->equalTo($this->userId))
			->will($this->returnValue([]));

		$result = $this->albumBusinessLayer->findAll($this->userId);
		$this->assertEquals([], $result);
	}

	public function testFind() {
		$albumId = 2;

		$this->mapper->expects($this->once())
			->method('find')
			->with($this->equalTo($albumId), $this->equalTo($this->userId))
			->will($this->returnValue($this->albums[$albumId-1]));
		$this->mapper->expects($this->exactly(1))
			->method('getPerformingArtistsByAlbumId')
			->with($this->equalTo([$albumId]),
					$this->equalTo($this->userId))
			->will($this->returnValue([$albumId => $this->artistIds[$albumId-1]]));
		$this->mapper->expects($this->exactly(1))
			->method('getYearsByAlbumId')
			->with($this->equalTo([$albumId]),
					$this->equalTo($this->userId))
			->will($this->returnValue([]));
		$this->mapper->expects($this->exactly(1))
			->method('getDiscCountByAlbumId')
			->with($this->equalTo([$albumId]),
					$this->equalTo($this->userId))
			->will($this->returnValue([$albumId => 1]));
		$this->mapper->expects($this->exactly(1))
			->method('getGenresByAlbumId')
			->with($this->equalTo([$albumId]),
					$this->equalTo($this->userId))
		->will($this->returnValue([]));

		$result = $this->albumBusinessLayer->find($albumId, $this->userId);
		$this->assertEquals($this->response[$albumId-1], $result);
	}

	public function testFindAllByArtist() {
		$artistId = 3;

		$this->mapper->expects($this->once())
			->method('findAllByArtist')
			->with($this->equalTo($artistId))
			->will($this->returnValue($this->albumsByArtist3));
		$this->mapper->expects($this->exactly(1))
			->method('getPerformingArtistsByAlbumId')
			->with($this->equalTo([1, 2]),
					$this->equalTo($this->userId))
			->will($this->returnValue([
				1 => $this->artistIds[1],
				2 => $this->artistIds[2]
			]));
		$this->mapper->expects($this->exactly(1))
			->method('getYearsByAlbumId')
			->with($this->equalTo([1, 2]),
					$this->equalTo($this->userId))
			->will($this->returnValue([]));
		$this->mapper->expects($this->exactly(1))
			->method('getDiscCountByAlbumId')
			->with($this->equalTo([1, 2]),
					$this->equalTo($this->userId))
			->will($this->returnValue([1 => 1, 2 => 1]));
		$this->mapper->expects($this->exactly(1))
			->method('getGenresByAlbumId')
			->with($this->equalTo([1, 2]),
					$this->equalTo($this->userId))
			->will($this->returnValue([]));

		$result = $this->albumBusinessLayer->findAllByArtist($artistId, $this->userId);
		$this->assertEquals($this->responseByArtist3, $result);
	}

	public function testDeleteById() {
		$albumIds = [1, 2, 3];

		$this->mapper->expects($this->once())
			->method('deleteById')
			->with($this->equalTo($albumIds));

		$this->albumBusinessLayer->deleteById($albumIds);
	}

	public function testUpdateFolderCover() {
		$coverFileId = 1;
		$parentFolderId = 2;

		$this->mapper->expects($this->once())
			->method('updateFolderCover')
			->with($this->equalTo($coverFileId), $this->equalTo($parentFolderId));

		$this->albumBusinessLayer->updateFolderCover($coverFileId, $parentFolderId);
	}

	public function testAddOrUpdateAlbum() {
		$name = 'test';
		$artistId = 1;
		$disc = 1;

		$this->mapper->expects($this->once())
			->method('insertOrUpdate')
			->will($this->returnValue($this->albums[0]));

		$album = $this->albumBusinessLayer->addOrUpdateAlbum($name, $disc, $artistId, $this->userId);
		$this->assertEquals($this->albums[0], $album);
	}

	public function testRemoveAndFindCovers() {
		$fileId = 1;

		$this->mapper->expects($this->once())
			->method('removeCovers')
			->with($this->equalTo([$fileId]));

		$this->albumBusinessLayer->removeCovers([$fileId]);
	}
}
