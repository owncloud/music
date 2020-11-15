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

namespace OCA\Music\Db;

class AlbumTest extends \PHPUnit\Framework\TestCase {
	private $urlGenerator;

	protected function setUp() {
		$this->urlGenerator = $this->getMockBuilder('\OCP\IURLGenerator')
			->disableOriginalConstructor()
			->getMock();
	}

	public function testToAPI() {
		$album = new Album();
		$album->setId(3);
		$album->setName('The name');
		$album->setYears([1999, 2000, 2013]);
		$album->setCoverFileId(5);
		$album->setArtistIds([1,2]);
		$album->setAlbumArtistId(3);

		$l10n = $this->getMockBuilder('\OCP\IL10N')->getMock();

		$this->assertEquals([
			'id' => 3,
			'name' => 'The name',
			'year' => 2013,
			'cover' => null,
			'slug' => '3-the-name',
			'artists' => [
				['id' => 1, 'uri' => null],
				['id' => 2, 'uri' => null]
			],
			'uri' => null,
			'albumArtistId' => 3,
			], $album->toAPI($this->urlGenerator, $l10n));
	}

	public function testNameLocalisation() {
		$album = new Album();
		$album->setName(null);

		$l10n = $this->getMockBuilder('\OCP\IL10N')->getMock();
		$l10nString = $this->getMockBuilder('\OC_L10N_String')
			->disableOriginalConstructor()
			->setMethods(['__toString'])
			->getMock();
		$l10nString->expects($this->any())
			->method('__toString')
			->will($this->returnValue('Unknown album'));
		$l10n->expects($this->any())
			->method('t')
			->will($this->returnValue($l10nString));

		$this->assertEquals('Unknown album', $album->getNameString($l10n));
		$album->setName('Album name');
		$this->assertEquals('Album name', $album->getNameString($l10n));
	}
}
