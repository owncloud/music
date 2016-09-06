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


class AlbumTest extends \PHPUnit_Framework_TestCase {

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
		$album->setYear(2013);
		$album->setCoverFileId(5);
		$album->setDisk(1);
		$album->setArtistIds(array(1,2));
		$album->setAlbumArtistId(3);

		$l10n = $this->getMockBuilder('\OCP\IL10N')
			->setMethods(array('t', 'n', 'l', 'getLanguageCode'))
			->getMock();

		$this->assertEquals(array(
			'id' => 3,
			'name' => 'The name',
			'year' => 2013,
			'disk' => 1,
			'cover' => null,
			'slug' => '3-the-name',
			'artists' => array(
				array('id' => 1, 'uri' => null),
				array('id' => 2, 'uri' => null)
			),
			'uri' => null,
			'albumArtistId' => 3,
			), $album->toAPI($this->urlGenerator, $l10n));
	}

	public function testNameLocalisation() {
		$album = new Album();
		$album->setName(null);

		$l10n = $this->getMockBuilder('\OCP\IL10N')
			->setMethods(array('t', 'n', 'l', 'getLanguageCode'))
			->getMock();
		$l10nString = $this->getMockBuilder('\OC_L10N_String')
			->disableOriginalConstructor()
			->setMethods(array('__toString'))
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
