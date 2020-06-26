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

class ArtistTest extends \PHPUnit_Framework_TestCase {
	private $urlGenerator;

	protected function setUp() {
		$this->urlGenerator = $this->getMockBuilder('\OCP\IURLGenerator')
			->disableOriginalConstructor()
			->getMock();
	}

	public function testToAPI() {
		$artist = new Artist();
		$artist->setId(3);
		$artist->setName('The name');
		$artist->setCoverFileId(100);

		$l10n = $this->getMockBuilder('\OCP\IL10N')->getMock();

		$this->assertEquals([
			'id' => 3,
			'name' => 'The name',
			'image' => null,
			'slug' => $artist->getId() . '-the-name',
			'uri' => null
			], $artist->toAPI($this->urlGenerator, $l10n));
	}

	public function testNullNameLocalisation() {
		$artist = new Artist();
		$artist->setName(null);

		$l10n = $this->getMockBuilder('\OCP\IL10N')->getMock();
		$l10nString = $this->getMockBuilder('\OC_L10N_String')
			->disableOriginalConstructor()
			->setMethods(['__toString'])
			->getMock();
		$l10nString->expects($this->any())
			->method('__toString')
			->will($this->returnValue('Unknown artist'));
		$l10n->expects($this->any())
			->method('t')
			->will($this->returnValue($l10nString));

		$this->assertEquals('Unknown artist', $artist->getNameString($l10n));
		$artist->setName('Artist name');
		$this->assertEquals('Artist name', $artist->getNameString($l10n));
	}
}
