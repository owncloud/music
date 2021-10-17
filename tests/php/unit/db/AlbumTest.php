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
 * @copyright Pauli Järvinen 2017 - 2021
 */

namespace OCA\Music\Db;

class AlbumTest extends \PHPUnit\Framework\TestCase {
	private $urlGenerator;

	protected function setUp() : void {
		$this->urlGenerator = $this->getMockBuilder('\OCP\IURLGenerator')
			->disableOriginalConstructor()
			->getMock();
		$this->urlGenerator
			->method('linkToRoute')
			->will($this->returnCallback([$this, 'linkToRouteMock']));
	}

	public static function linkToRouteMock(string $route, array $args) : string {
		switch ($route) {
			case 'music.api.artist':		return "/link/to/artist/{$args['artistId']}";
			case 'music.api.album':			return "/link/to/album/{$args['albumId']}";
			case 'music.api.albumCover':	return "/link/to/album/cover/{$args['albumId']}";
			default:						return "(mock missing for route $route)";
		}
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
			'cover' => '/link/to/album/cover/3',
			'slug' => 'the-name',
			'artists' => [
				['id' => 1, 'uri' => '/link/to/artist/1'],
				['id' => 2, 'uri' => '/link/to/artist/2']
			],
			'uri' => '/link/to/album/3',
			'albumArtistId' => 3,
			], $album->toAPI($this->urlGenerator, $l10n));
	}

	public function testNameLocalisation() {
		$album = new Album();
		$album->setName(null);

		$l10n = $this->getMockBuilder('\OCP\IL10N')->getMock();
		$l10n->expects($this->any())
			->method('t')
			->will($this->returnValue('Unknown album'));

		$this->assertEquals('Unknown album', $album->getNameString($l10n));
		$album->setName('Album name');
		$this->assertEquals('Album name', $album->getNameString($l10n));
	}
}
