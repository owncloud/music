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

class ArtistTest extends \PHPUnit_Framework_TestCase {

	private $api;

	protected function setUp() {
		$this->api = $this->getMockBuilder(
			'\OCA\Music\Core\API')
			->disableOriginalConstructor()
			->getMock();
	}

	public function testToAPI() {
		$artist = new Artist();
		$artist->setId(3);
		$artist->setName('The name');
		$artist->setImage('The image url');

		$this->assertEquals(array(
			'id' => 3,
			'name' => 'The name',
			'image' => 'The image url',
			'slug' => $artist->getId() . '-the-name',
			'uri' => ''
			), $artist->toAPI($this->api));
	}

}