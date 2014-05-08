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
		$artist->setImage('The image url');

		$l10n = $this->getMockBuilder('\OCP\IL10N')
			->disableOriginalConstructor()
			->getMock();

		$this->assertEquals(array(
			'id' => 3,
			'name' => 'The name',
			'image' => 'The image url',
			'slug' => $artist->getId() . '-the-name',
			'uri' => ''
			), $artist->toAPI($this->urlGenerator, $l10n));
	}

	public function testNullNameLocalisation() {
		$artist = new Artist();
		$artist->setName(null);

		$l10n = $this->getMock('\OCP\IL10N', array('t', 'n', 'l'));
		$l10nString = $this->getMock('\OC\L10N\String', array('__toString'));
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
