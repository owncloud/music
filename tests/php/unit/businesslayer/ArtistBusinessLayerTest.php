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

use \OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use \OCA\Music\AppFramework\Db\DoesNotExistException;
use \OCA\Music\AppFramework\Db\MultipleObjectsReturnedException;

use \OCA\Music\Db\Artist;


class ArtistBusinessLayerTest extends \PHPUnit_Framework_TestCase {

	private $mapper;
	private $logger;
	private $artistBusinessLayer;


	protected function setUp(){
		$this->mapper = $this->getMockBuilder('\OCA\Music\Db\ArtistMapper')
			->disableOriginalConstructor()
			->getMock();
		$this->logger = $this->getMockBuilder('\OCA\Music\AppFramework\Core\Logger')
			->disableOriginalConstructor()
			->getMock();
		$this->artistBusinessLayer = new ArtistBusinessLayer($this->mapper, $this->logger);
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

		$this->setExpectedException('\OCA\Music\AppFramework\BusinessLayer\BusinessLayerException');
		$this->artistBusinessLayer->addArtistIfNotExist($name, $this->userId);
	}
}


