<?php

/**
 * ownCloud - Music app
 *
 * @author Morris Jobke
 * @copyright 2013 Morris Jobke <morris.jobke@gmail.com>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */


namespace OCA\Music\Db;

/* FIXME: dirty hack to mock object */
class TrackTestView {
	public function getPath($fileId) {
		return $fileId;
	}
}

class TrackTest extends \PHPUnit_Framework_TestCase {

	private $api;

	protected function setUp() {
		$this->api = $this->getMockBuilder(
			'\OCA\Music\Core\API')
			->disableOriginalConstructor()
			->getMock();

		/* FIXME: dirty hack to mock object */
		$this->api->expects($this->any())
			->method('getView')
			->will($this->returnValue(new TrackTestView()));
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
			), $track->toAPI($this->api));
	}

}