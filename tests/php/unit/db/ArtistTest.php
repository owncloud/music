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
 * @copyright Pauli Järvinen 2018 - 2025
 */

namespace OCA\Music\Db;

class ArtistTest extends \PHPUnit\Framework\TestCase {
	private $urlGenerator;
	private $l10n;

	protected function setUp() : void {
		$this->urlGenerator = $this->getMockBuilder('\OCP\IURLGenerator')
			->disableOriginalConstructor()
			->getMock();
		$this->urlGenerator->expects($this->any())
			->method('linkToRoute')
			->will($this->returnValue('https://some.url'));

		$this->l10n = $this->getMockBuilder('\OCP\IL10N')->getMock();
		$this->l10n->expects($this->any())
			->method('t')
			->will($this->returnValue('Unknown artist'));
		}

	public function testToShivaApi() {
		$artist = new Artist();
		$artist->setId(3);
		$artist->setName('The name');
		$artist->setCoverFileId(100);

		$this->assertEquals([
			'id' => 3,
			'name' => 'The name',
			'image' => 'https://some.url',
			'slug' => 'the-name',
			'uri' => 'https://some.url'
		], $artist->toShivaApi($this->urlGenerator, $this->l10n));
	}

	public function testNullNameLocalisation() {
		$artist = new Artist();
		$artist->setName(null);

		$this->assertEquals('Unknown artist', $artist->getNameString($this->l10n));
		$artist->setName('Artist name');
		$this->assertEquals('Artist name', $artist->getNameString($this->l10n));
	}
}
