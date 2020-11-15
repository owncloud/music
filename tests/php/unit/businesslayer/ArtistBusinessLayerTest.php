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

use \OCA\Music\Db\Artist;

class ArtistBusinessLayerTest extends \PHPUnit\Framework\TestCase {
	private $mapper;
	private $logger;
	private $artistBusinessLayer;

	protected function setUp() {
		$this->mapper = $this->getMockBuilder('\OCA\Music\Db\ArtistMapper')
			->disableOriginalConstructor()
			->getMock();
		$this->logger = $this->getMockBuilder('\OCA\Music\AppFramework\Core\Logger')
			->disableOriginalConstructor()
			->getMock();
		$this->artistBusinessLayer = new ArtistBusinessLayer($this->mapper, $this->logger);
		$this->userId = 'john';
	}

	public function testFindById() {
		$artistIds = [1,2,3];
		$response = [];
		$this->mapper->expects($this->once())
			->method('findById')
			->with($this->equalTo($artistIds),
					$this->equalTo($this->userId))
			->will($this->returnValue($response));

		$result = $this->artistBusinessLayer->findById(
			$artistIds,
			$this->userId);
		$this->assertEquals($response, $result);
	}

	public function testDeleteById() {
		$artistIds = [1, 2, 3];

		$this->mapper->expects($this->once())
			->method('deleteById')
			->with($this->equalTo($artistIds));

		$this->artistBusinessLayer->deleteById($artistIds);
	}

	public function testAddOrUpdateArtist() {
		$name = 'test';

		$artist = new Artist();
		$artist->setName($name);
		$artist->setId(1);

		$this->mapper->expects($this->once())
			->method('insertOrUpdate')
			->will($this->returnValue($artist));

		$result = $this->artistBusinessLayer->addOrUpdateArtist($name, $this->userId);
		$this->assertEquals($artist, $result);
	}
}
