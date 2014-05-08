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


class TrackTest extends \PHPUnit_Framework_TestCase {

	private $urlGenerator;

	protected function setUp() {
		$this->urlGenerator = $this->getMockBuilder('\OCP\IURLGenerator')
			->disableOriginalConstructor()
			->getMock();
	}

	public function testToAPI() {
		$track = new Track();
		$track->setId(1);
		$track->setTitle('The title');
		$track->setArtistId(3);
		$track->setAlbumId(1);
		$track->setNumber(4);
		$track->setLength(123);
		$track->setFileId(3);
		$track->setMimetype('audio/mp3');
		$track->setBitrate(123);

		$this->assertEquals(array(
			'id' => 1,
			'title' => 'The title',
			'number' => 4,
			'artist' => array('id' => 3, 'uri' => null),
			'album' => array('id' => 1, 'uri' => null),
			'length' => 123,
			'files' => array('audio/mp3' => null),
			'bitrate' => 123,
			'slug' => '1-the-title',
			'uri' => null
			), $track->toAPI($this->urlGenerator));
	}

}
