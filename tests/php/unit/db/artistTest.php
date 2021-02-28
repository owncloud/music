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
 * @copyright Pauli Järvinen 2018 - 2021
 */

namespace OCA\Music\Db;

class ArtistTest extends \PHPUnit\Framework\TestCase {
	private $urlGenerator;

	protected function setUp() : void {
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
		$l10n->expects($this->any())
			->method('t')
			->will($this->returnValue('Unknown artist'));

		$this->assertEquals('Unknown artist', $artist->getNameString($l10n));
		$artist->setName('Artist name');
		$this->assertEquals('Artist name', $artist->getNameString($l10n));
	}
}
