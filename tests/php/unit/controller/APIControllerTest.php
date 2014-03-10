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

namespace OCA\Music\Controller;

use \OCA\Music\AppFramework\Utility\ControllerTestUtility;
use \OCA\Music\AppFramework\Core\API;
use \OCA\Music\AppFramework\Http\Request;
use \OCA\Music\AppFramework\Http\JSONResponse;

use OCA\Music\DB\Artist;
use OCA\Music\DB\Album;
use OCA\Music\DB\Track;

/* FIXME: dirty hack to mock object */
class TestView {
	public function getPath($fileId) {
		return $fileId;
	}
}

class APIControllerTest extends ControllerTestUtility {

	private $api;
	private $mapper;
	private $trackBusinessLayer;
	private $artistBusinessLayer;
	private $albumBusinessLayer;
	private $request;
	private $controller;
	private $user = 'john';

	protected function getController($urlParams){
		return new ApiController($this->api, new Request(array('urlParams' => $urlParams)),
			$this->trackBusinessLayer,
			$this->artistBusinessLayer,
			$this->albumBusinessLayer);
	}

	protected function setUp(){
		$this->api = $this->getAPIMock();

		/* FIXME: dirty hack to mock object */
		$this->api->expects($this->any())
			->method('getView')
			->will($this->returnValue(new TestView()));
		$this->trackBusinessLayer = $this->getMockBuilder('\OCA\Music\BusinessLayer\TrackBusinessLayer')
			->disableOriginalConstructor()
			->getMock();
		$this->artistBusinessLayer = $this->getMockBuilder('\OCA\Music\BusinessLayer\ArtistBusinessLayer')
			->disableOriginalConstructor()
			->getMock();
		$this->albumBusinessLayer = $this->getMockBuilder('\OCA\Music\BusinessLayer\AlbumBusinessLayer')
			->disableOriginalConstructor()
			->getMock();
		$this->request = new Request();
		$this->controller = new ApiController($this->api, $this->request,
			$this->trackBusinessLayer,
			$this->artistBusinessLayer,
			$this->albumBusinessLayer);
	}

	private function assertAPIControllerAnnotations($methodName){
		$annotations = array('CSRFExemption', 'IsAdminExemption', 'IsSubAdminExemption', 'Ajax', 'API');
		$this->assertAnnotations($this->controller, $methodName, $annotations);
	}

	public function testArtistsAnnotations(){
		$this->assertAPIControllerAnnotations('artists');
	}

	public function testArtistAnnotations(){
		$this->assertAPIControllerAnnotations('artist');
	}

	public function testAlbumsAnnotations(){
		$this->assertAPIControllerAnnotations('albums');
	}

	public function testAlbumAnnotations(){
		$this->assertAPIControllerAnnotations('album');
	}

	public function testTracksAnnotations(){
		$this->assertAPIControllerAnnotations('tracks');
	}

	public function testTrackAnnotations(){
		$this->assertAPIControllerAnnotations('track');
	}

	private function getLinkToRouteFunction(){
		return function($routeName, $arguments) {
			switch($routeName){
				case 'music_artists':
					return '/api/artists';
				case 'music_albums':
					return '/api/albums';
				case 'music_tracks':
					return '/api/tracks';
				case 'music_artist':
					return '/api/artist/' . $arguments['artistIdOrSlug'];
				case 'music_album':
					return '/api/album/' . $arguments['albumIdOrSlug'];
				case 'music_track':
					return '/api/track/' . $arguments['trackIdOrSlug'];
				default:
					return $arguments['file'];
			}
		};
	}

	public function testArtists(){
		$artist1 = new Artist();
		$artist1->setId(3);
		$artist1->setName('The artist name');
		$artist1->setImage('The image url');
		$artist2 = new Artist();
		$artist2->setId(4);
		$artist2->setName('The other artist name');
		$artist2->setImage('The image url number 2');

		$this->api->expects($this->exactly(2))
			->method('linkToRoute')
			->will($this->returnCallback($this->getLinkToRouteFunction()));

		$this->api->expects($this->once())
			->method('getUserId')
			->will($this->returnValue($this->user));
		$this->artistBusinessLayer->expects($this->once())
			->method('findAll')
			->with($this->equalTo($this->user))
			->will($this->returnValue(array($artist1, $artist2)));

		$result = array(
			array(
				'name' => 'The artist name',
				'image' => 'The image url',
				'uri' => '/api/artist/3',
				'slug' => '3-the-artist-name',
				'id' => 3
			),
			array(
				'name' => 'The other artist name',
				'image' => 'The image url number 2',
				'uri' => '/api/artist/4',
				'slug' => '4-the-other-artist-name',
				'id' => 4
			)
		);

		$response = $this->controller->artists();

		$this->assertEquals($result, $response->getData());
		$this->assertTrue($response instanceof JSONResponse);
	}

	public function testArtistsFulltree(){
		$artist1 = new Artist();
		$artist1->setId(3);
		$artist1->setName('The artist name');
		$artist1->setImage('The image url');
		$artist2 = new Artist();
		$artist2->setId(4);
		$artist2->setName('The other artist name');
		$artist2->setImage('The image url number 2');
		$album = new Album();
		$album->setId(4);
		$album->setName('The name');
		$album->setYear(2013);
		$album->setCoverFileId(5);
		$album->setArtistIds(array(3));
		$track = new Track();
		$track->setId(1);
		$track->setTitle('The title');
		$track->setArtistId(3);
		$track->setAlbumId(4);
		$track->setNumber(4);
		$track->setLength(123);
		$track->setFileId(3);
		$track->setMimetype('audio/mp3');
		$track->setBitrate(123);

		$albumId = 4;

		$this->api->expects($this->exactly(16))
			->method('linkToRoute')
			->will($this->returnCallback($this->getLinkToRouteFunction()));

		$this->api->expects($this->once())
			->method('getUserId')
			->will($this->returnValue($this->user));
		$this->artistBusinessLayer->expects($this->once())
			->method('findAll')
			->with($this->equalTo($this->user))
			->will($this->returnValue(array($artist1, $artist2)));
		$this->albumBusinessLayer->expects($this->at(0))
			->method('findAllByArtist')
			->with($this->equalTo(3), $this->equalTo($this->user))
			->will($this->returnValue(array($album)));
		$this->albumBusinessLayer->expects($this->at(1))
			->method('findAllByArtist')
			->with($this->equalTo(4), $this->equalTo($this->user))
			->will($this->returnValue(array($album)));
		$this->trackBusinessLayer->expects($this->exactly(2))
			->method('findAllByAlbum')
			->with($this->equalTo($albumId))
			->will($this->returnValue(array($track)));

		$result = array(
			array(
				'name' => 'The artist name',
				'image' => 'The image url',
				'uri' => '/api/artist/3',
				'slug' => '3-the-artist-name',
				'id' => 3,
				'albums' => array(
					array(
						'name' => 'The name',
						'cover' => 5,
						'uri' => '/api/album/4',
						'slug' => '4-the-name',
						'id' => 4,
						'year' => 2013,
						'artists' => array(
							array('id' => 3, 'uri' => '/api/artist/3')
						),
						'tracks' => array(
							array(
								'title' => 'The title',
								'uri' => '/api/track/1',
								'slug' => '1-the-title',
								'id' => 1,
								'number' => 4,
								'bitrate' => 123,
								'length' => 123,
								'artist' => array('id' => 3, 'uri' => '/api/artist/3'),
								'album' => array('id' => 4, 'uri' => '/api/album/4'),
								'files' => array(
									'audio/mp3' => 3
								)
							)
						)
					)
				)
			),
			array(
				'name' => 'The other artist name',
				'image' => 'The image url number 2',
				'uri' => '/api/artist/4',
				'slug' => '4-the-other-artist-name',
				'id' => 4,
				'albums' => array(
					array(
						'name' => 'The name',
						'cover' => 5,
						'uri' => '/api/album/4',
						'slug' => '4-the-name',
						'id' => 4,
						'year' => 2013,
						'artists' => array(
							array('id' => 3, 'uri' => '/api/artist/3')
						),
						'tracks' => array(
							array(
								'title' => 'The title',
								'uri' => '/api/track/1',
								'slug' => '1-the-title',
								'id' => 1,
								'number' => 4,
								'bitrate' => 123,
								'length' => 123,
								'artist' => array('id' => 3, 'uri' => '/api/artist/3'),
								'album' => array('id' => 4, 'uri' => '/api/album/4'),
								'files' => array(
									'audio/mp3' => 3
								)
							)
						)
					)
				)
			)
		);

		$urlParams = array('fulltree' => true);
		$this->controller = $this->getController($urlParams);

		$response = $this->controller->artists();

		$this->assertEquals($result, $response->getData());
		$this->assertTrue($response instanceof JSONResponse);
	}

	public function testArtistsAlbumsOnlyFulltree(){
		$artist1 = new Artist();
		$artist1->setId(3);
		$artist1->setName('The artist name');
		$artist1->setImage('The image url');
		$artist2 = new Artist();
		$artist2->setId(4);
		$artist2->setName('The other artist name');
		$artist2->setImage('The image url number 2');
		$album = new Album();
		$album->setId(4);
		$album->setName('The name');
		$album->setYear(2013);
		$album->setCoverFileId(5);
		$album->setArtistIds(array(3));

		$albumId = 4;

		$this->api->expects($this->exactly(8))
			->method('linkToRoute')
			->will($this->returnCallback($this->getLinkToRouteFunction()));

		$this->api->expects($this->once())
			->method('getUserId')
			->will($this->returnValue($this->user));
		$this->artistBusinessLayer->expects($this->once())
			->method('findAll')
			->with($this->equalTo($this->user))
			->will($this->returnValue(array($artist1, $artist2)));
		$this->albumBusinessLayer->expects($this->at(0))
			->method('findAllByArtist')
			->with($this->equalTo(3), $this->equalTo($this->user))
			->will($this->returnValue(array($album)));
		$this->albumBusinessLayer->expects($this->at(1))
			->method('findAllByArtist')
			->with($this->equalTo(4), $this->equalTo($this->user))
			->will($this->returnValue(array($album)));
		$this->trackBusinessLayer->expects($this->never())
			->method('findAllByAlbum');

		$result = array(
			array(
				'name' => 'The artist name',
				'image' => 'The image url',
				'uri' => '/api/artist/3',
				'slug' => '3-the-artist-name',
				'id' => 3,
				'albums' => array(
					array(
						'name' => 'The name',
						'cover' => 5,
						'uri' => '/api/album/4',
						'slug' => '4-the-name',
						'id' => 4,
						'year' => 2013,
						'artists' => array(
							array('id' => 3, 'uri' => '/api/artist/3')
						)
					)
				)
			),
			array(
				'name' => 'The other artist name',
				'image' => 'The image url number 2',
				'uri' => '/api/artist/4',
				'slug' => '4-the-other-artist-name',
				'id' => 4,
				'albums' => array(
					array(
						'name' => 'The name',
						'cover' => 5,
						'uri' => '/api/album/4',
						'slug' => '4-the-name',
						'id' => 4,
						'year' => 2013,
						'artists' => array(
							array('id' => 3, 'uri' => '/api/artist/3')
						)
					)
				)
			)
		);

		$urlParams = array('albums' => true);
		$this->controller = $this->getController($urlParams);

		$response = $this->controller->artists();

		$this->assertEquals($result, $response->getData());
		$this->assertTrue($response instanceof JSONResponse);
	}

	public function testArtist(){
		$artist = new Artist();
		$artist->setId(3);
		$artist->setName('The artist name');
		$artist->setImage('The image url');

		$artistId = 3;

		$this->api->expects($this->exactly(1))
			->method('linkToRoute')
			->will($this->returnCallback($this->getLinkToRouteFunction()));

		$this->api->expects($this->once())
			->method('getUserId')
			->will($this->returnValue($this->user));
		$this->artistBusinessLayer->expects($this->once())
			->method('find')
			->with($this->equalTo($artistId), $this->equalTo($this->user))
			->will($this->returnValue($artist));

		$result = array(
			'name' => 'The artist name',
			'image' => 'The image url',
			'uri' => '/api/artist/3',
			'slug' => '3-the-artist-name',
			'id' => 3
		);

		$urlParams = array('artistIdOrSlug' => $artistId);
		$this->controller = $this->getController($urlParams);

		$response = $this->controller->artist();

		$this->assertEquals($result, $response->getData());
		$this->assertTrue($response instanceof JSONResponse);
	}

	public function testArtistFulltree(){
		$artist = new Artist();
		$artist->setId(3);
		$artist->setName('The artist name');
		$artist->setImage('The image url');
		$album = new Album();
		$album->setId(3);
		$album->setName('The name');
		$album->setYear(2013);
		$album->setCoverFileId(5);
		$album->setArtistIds(array(3));
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

		$artistId = 3;
		$albumId = 3;

		$this->api->expects($this->exactly(8))
			->method('linkToRoute')
			->will($this->returnCallback($this->getLinkToRouteFunction()));

		$this->api->expects($this->once())
			->method('getUserId')
			->will($this->returnValue($this->user));
		$this->artistBusinessLayer->expects($this->once())
			->method('find')
			->with($this->equalTo($artistId), $this->equalTo($this->user))
			->will($this->returnValue($artist));
		$this->albumBusinessLayer->expects($this->once())
			->method('findAllByArtist')
			->with($this->equalTo($artistId), $this->equalTo($this->user))
			->will($this->returnValue(array($album)));
		$this->trackBusinessLayer->expects($this->once())
			->method('findAllByAlbum')
			->with($this->equalTo($albumId))
			->will($this->returnValue(array($track)));

		$result = array(
			'name' => 'The artist name',
			'image' => 'The image url',
			'uri' => '/api/artist/3',
			'slug' => '3-the-artist-name',
			'id' => 3,
			'albums' => array(
				array(
					'name' => 'The name',
					'cover' => 5,
					'uri' => '/api/album/3',
					'slug' => '3-the-name',
					'id' => 3,
					'year' => 2013,
					'artists' => array(
						array('id' => 3, 'uri' => '/api/artist/3')
					),
					'tracks' => array(
						array(
							'title' => 'The title',
							'uri' => '/api/track/1',
							'slug' => '1-the-title',
							'id' => 1,
							'number' => 4,
							'bitrate' => 123,
							'length' => 123,
							'artist' => array('id' => 3, 'uri' => '/api/artist/3'),
							'album' => array('id' => 1, 'uri' => '/api/album/1'),
							'files' => array(
								'audio/mp3' => 3
							)
						)
					)
				)
			)
		);

		$urlParams = array('artistIdOrSlug' => $artistId, 'fulltree' => true);
		$this->controller = $this->getController($urlParams);

		$response = $this->controller->artist();

		$this->assertEquals($result, $response->getData());
		$this->assertTrue($response instanceof JSONResponse);
	}

	public function testAlbums(){
		$album1 = new Album();
		$album1->setId(3);
		$album1->setName('The name');
		$album1->setYear(2013);
		$album1->setCoverFileId(5);
		$album1->setArtistIds(array(1));
		$album2 = new Album();
		$album2->setId(4);
		$album2->setName('The album name');
		$album2->setYear(2003);
		$album2->setCoverFileId(7);
		$album2->setArtistIds(array(3,5));

		$this->api->expects($this->exactly(7))
			->method('linkToRoute')
			->will($this->returnCallback($this->getLinkToRouteFunction()));

		$this->api->expects($this->once())
			->method('getUserId')
			->will($this->returnValue($this->user));
		$this->albumBusinessLayer->expects($this->once())
			->method('findAll')
			->with($this->equalTo($this->user))
			->will($this->returnValue(array($album1, $album2)));

		$result = array(
			array(
				'name' => 'The name',
				'cover' => 5,
				'uri' => '/api/album/3',
				'slug' => '3-the-name',
				'id' => 3,
				'year' => 2013,
				'artists' => array(
					array('id' => 1, 'uri' => '/api/artist/1')
				)
			),
			array(
				'name' => 'The album name',
				'cover' => 7,
				'uri' => '/api/album/4',
				'slug' => '4-the-album-name',
				'id' => 4,
				'year' => 2003,
				'artists' => array(
					array('id' => 3, 'uri' => '/api/artist/3'),
					array('id' => 5, 'uri' => '/api/artist/5')
				)
			)
		);

		$response = $this->controller->albums();

		$this->assertEquals($result, $response->getData());
		$this->assertTrue($response instanceof JSONResponse);
	}

	public function testAlbumsFulltree(){
		$album1 = new Album();
		$album1->setId(3);
		$album1->setName('The name');
		$album1->setYear(2013);
		$album1->setCoverFileId(5);
		$album1->setArtistIds(array(1));
		$album2 = new Album();
		$album2->setId(4);
		$album2->setName('The album name');
		$album2->setYear(2003);
		$album2->setCoverFileId(7);
		$album2->setArtistIds(array(3,5));
		$artist1 = new Artist();
		$artist1->setId(1);
		$artist1->setName('The artist name');
		$artist1->setImage('The image url');
		$artist2 = new Artist();
		$artist2->setId(3);
		$artist2->setName('The artist name3');
		$artist2->setImage('The image url3');
		$artist3 = new Artist();
		$artist3->setId(5);
		$artist3->setName('The artist name5');
		$artist3->setImage('The image url5');
		$track = new Track();
		$track->setId(1);
		$track->setTitle('The title');
		$track->setArtistId(3);
		$track->setAlbumId(4);
		$track->setNumber(4);
		$track->setLength(123);
		$track->setFileId(3);
		$track->setMimetype('audio/mp3');
		$track->setBitrate(123);

		$this->api->expects($this->exactly(18)) // artists uris will be fetched twice
			->method('linkToRoute')
			->will($this->returnCallback($this->getLinkToRouteFunction()));

		$this->api->expects($this->once())
			->method('getUserId')
			->will($this->returnValue($this->user));
		$this->albumBusinessLayer->expects($this->once())
			->method('findAll')
			->with($this->equalTo($this->user))
			->will($this->returnValue(array($album1, $album2)));
		$this->artistBusinessLayer->expects($this->at(0))
			->method('findMultipleById')
			->with($this->equalTo(array(1)), $this->equalTo($this->user))
			->will($this->returnValue(array($artist1)));
		$this->artistBusinessLayer->expects($this->at(1))
			->method('findMultipleById')
			->with($this->equalTo(array(3,5)), $this->equalTo($this->user))
			->will($this->returnValue(array($artist2, $artist3)));
		$this->trackBusinessLayer->expects($this->at(0))
			->method('findAllByAlbum')
			->with($this->equalTo(3))
			->will($this->returnValue(array($track)));
		$this->trackBusinessLayer->expects($this->at(1))
			->method('findAllByAlbum')
			->with($this->equalTo(4))
			->will($this->returnValue(array($track)));

		$result = array(
			array(
				'name' => 'The name',
				'cover' => 5,
				'uri' => '/api/album/3',
				'slug' => '3-the-name',
				'id' => 3,
				'year' => 2013,
				'artists' => array(
					array(
						'name' => 'The artist name',
						'image' => 'The image url',
						'uri' => '/api/artist/1',
						'slug' => '1-the-artist-name',
						'id' => 1
					)
				),
				'tracks' => array(
					array(
						'title' => 'The title',
						'uri' => '/api/track/1',
						'slug' => '1-the-title',
						'id' => 1,
						'number' => 4,
						'bitrate' => 123,
						'length' => 123,
						'artist' => array('id' => 3, 'uri' => '/api/artist/3'),
						'album' => array('id' => 4, 'uri' => '/api/album/4'),
						'files' => array(
							'audio/mp3' => 3
						)
					)
				)
			),
			array(
				'name' => 'The album name',
				'cover' => 7,
				'uri' => '/api/album/4',
				'slug' => '4-the-album-name',
				'id' => 4,
				'year' => 2003,
				'artists' => array(
					array(
						'name' => 'The artist name3',
						'image' => 'The image url3',
						'uri' => '/api/artist/3',
						'slug' => '3-the-artist-name3',
						'id' => 3
					),
					array(
						'name' => 'The artist name5',
						'image' => 'The image url5',
						'uri' => '/api/artist/5',
						'slug' => '5-the-artist-name5',
						'id' => 5
					)
				),
				'tracks' => array(
					array(
						'title' => 'The title',
						'uri' => '/api/track/1',
						'slug' => '1-the-title',
						'id' => 1,
						'number' => 4,
						'bitrate' => 123,
						'length' => 123,
						'artist' => array('id' => 3, 'uri' => '/api/artist/3'),
						'album' => array('id' => 4, 'uri' => '/api/album/4'),
						'files' => array(
							'audio/mp3' => 3
						)
					)
				)
			)
		);

		$urlParams = array('fulltree' => true);
		$this->controller = $this->getController($urlParams);

		$response = $this->controller->albums();

		$this->assertEquals($result, $response->getData());
		$this->assertTrue($response instanceof JSONResponse);
	}

	public function testAlbum(){
		$album = new Album();
		$album->setId(3);
		$album->setName('The name');
		$album->setYear(2013);
		$album->setCoverFileId(5);
		$album->setArtistIds(array(1));
		$artist = new Artist();
		$artist->setId(1);
		$artist->setName('The artist name');
		$artist->setImage('The image url');
		$track = new Track();
		$track->setId(1);
		$track->setTitle('The title');
		$track->setArtistId(3);
		$track->setAlbumId(4);
		$track->setNumber(4);
		$track->setLength(123);
		$track->setFileId(3);
		$track->setMimetype('audio/mp3');
		$track->setBitrate(123);

		$albumId = 3;

		$this->api->expects($this->exactly(8)) // artists uris will be fetched twice
			->method('linkToRoute')
			->will($this->returnCallback($this->getLinkToRouteFunction()));

		$this->api->expects($this->once())
			->method('getUserId')
			->will($this->returnValue($this->user));
		$this->albumBusinessLayer->expects($this->once())
			->method('find')
			->with($this->equalTo($albumId), $this->equalTo($this->user))
			->will($this->returnValue($album));
		$this->artistBusinessLayer->expects($this->once())
			->method('findMultipleById')
			->with($this->equalTo(array(1)), $this->equalTo($this->user))
			->will($this->returnValue(array($artist)));
		$this->trackBusinessLayer->expects($this->once())
			->method('findAllByAlbum')
			->with($this->equalTo($albumId))
			->will($this->returnValue(array($track)));

		$result = array(
			'name' => 'The name',
			'cover' => 5,
			'uri' => '/api/album/3',
			'slug' => '3-the-name',
			'id' => 3,
			'year' => 2013,
			'artists' => array(
				array(
					'name' => 'The artist name',
					'image' => 'The image url',
					'uri' => '/api/artist/1',
					'slug' => '1-the-artist-name',
					'id' => 1
				)
			),
			'tracks' => array(
				array(
					'title' => 'The title',
					'uri' => '/api/track/1',
					'slug' => '1-the-title',
					'id' => 1,
					'number' => 4,
					'bitrate' => 123,
					'length' => 123,
					'artist' => array('id' => 3, 'uri' => '/api/artist/3'),
					'album' => array('id' => 4, 'uri' => '/api/album/4'),
					'files' => array(
						'audio/mp3' => 3
					)
				)
			)
		);

		$urlParams = array('albumIdOrSlug' => $albumId, 'fulltree' => true);
		$this->controller = $this->getController($urlParams);

		$response = $this->controller->album();

		$this->assertEquals($result, $response->getData());
		$this->assertTrue($response instanceof JSONResponse);
	}

	public function testAlbumFulltree(){
		$album = new Album();
		$album->setId(3);
		$album->setName('The name');
		$album->setYear(2013);
		$album->setCoverFileId(5);
		$album->setArtistIds(array(1));

		$albumId = 3;

		$this->api->expects($this->exactly(3))
			->method('linkToRoute')
			->will($this->returnCallback($this->getLinkToRouteFunction()));

		$this->api->expects($this->once())
			->method('getUserId')
			->will($this->returnValue($this->user));
		$this->albumBusinessLayer->expects($this->once())
			->method('find')
			->with($this->equalTo($albumId), $this->equalTo($this->user))
			->will($this->returnValue($album));

		$result = array(
			'name' => 'The name',
			'cover' => 5,
			'uri' => '/api/album/3',
			'slug' => '3-the-name',
			'id' => 3,
			'year' => 2013,
			'artists' => array(
				array('id' => 1, 'uri' => '/api/artist/1')
			)
		);

		$urlParams = array('albumIdOrSlug' => $albumId);
		$this->controller = $this->getController($urlParams);

		$response = $this->controller->album();

		$this->assertEquals($result, $response->getData());
		$this->assertTrue($response instanceof JSONResponse);
	}

	public function testTracks(){
		$track1 = new Track();
		$track1->setId(1);
		$track1->setTitle('The title');
		$track1->setArtistId(3);
		$track1->setAlbumId(1);
		$track1->setNumber(4);
		$track1->setLength(123);
		$track1->setFileId(3);
		$track1->setMimetype('audio/mp3');
		$track1->setBitrate(123);
		$track2 = new Track();
		$track2->setId(2);
		$track2->setTitle('The second title');
		$track2->setArtistId(2);
		$track2->setAlbumId(3);
		$track2->setNumber(5);
		$track2->setLength(103);
		$track2->setFileId(3);
		$track2->setMimetype('audio/mp3');
		$track2->setBitrate(123);

		$this->api->expects($this->exactly(8))
			->method('linkToRoute')
			->will($this->returnCallback($this->getLinkToRouteFunction()));

		$this->api->expects($this->once())
			->method('getUserId')
			->will($this->returnValue($this->user));
		$this->trackBusinessLayer->expects($this->once())
			->method('findAll')
			->with($this->equalTo($this->user))
			->will($this->returnValue(array($track1, $track2)));

		$result = array(
			array(
				'title' => 'The title',
				'uri' => '/api/track/1',
				'slug' => '1-the-title',
				'id' => 1,
				'number' => 4,
				'bitrate' => 123,
				'length' => 123,
				'artist' => array('id' => 3, 'uri' => '/api/artist/3'),
				'album' => array('id' => 1, 'uri' => '/api/album/1'),
				'files' => array(
					'audio/mp3' => 3
				)
			),
			array(
				'title' => 'The second title',
				'uri' => '/api/track/2',
				'slug' => '2-the-second-title',
				'id' => 2,
				'number' => 5,
				'bitrate' => 123,
				'length' => 103,
				'artist' => array('id' => 2, 'uri' => '/api/artist/2'),
				'album' => array('id' => 3, 'uri' => '/api/album/3'),
				'files' => array(
					'audio/mp3' => 3
				)
			)
		);

		$response = $this->controller->tracks();

		$this->assertEquals($result, $response->getData());
		$this->assertTrue($response instanceof JSONResponse);
	}

	public function testTracksFulltree(){
		$track1 = new Track();
		$track1->setId(1);
		$track1->setTitle('The title');
		$track1->setArtistId(3);
		$track1->setAlbumId(1);
		$track1->setNumber(4);
		$track1->setLength(123);
		$track1->setFileId(3);
		$track1->setMimetype('audio/mp3');
		$track1->setBitrate(123);
		$album = new Album();
		$album->setId(3);
		$album->setName('The name');
		$album->setYear(2013);
		$album->setCoverFileId(5);
		$album->setArtistIds(array(1));
		$artist = new Artist();
		$artist->setId(1);
		$artist->setName('The artist name');
		$artist->setImage('The image url');

		$this->api->expects($this->exactly(8))
			->method('linkToRoute')
			->will($this->returnCallback($this->getLinkToRouteFunction()));

		$this->api->expects($this->once())
			->method('getUserId')
			->will($this->returnValue($this->user));
		$this->trackBusinessLayer->expects($this->once())
			->method('findAll')
			->with($this->equalTo($this->user))
			->will($this->returnValue(array($track1)));
		$this->artistBusinessLayer->expects($this->once())
			->method('find')
			->with($this->equalTo(3), $this->equalTo($this->user))
			->will($this->returnValue($artist));
		$this->albumBusinessLayer->expects($this->once())
			->method('find')
			->with($this->equalTo(1), $this->equalTo($this->user))
			->will($this->returnValue($album ));

		$result = array(
			array(
				'title' => 'The title',
				'uri' => '/api/track/1',
				'slug' => '1-the-title',
				'id' => 1,
				'number' => 4,
				'bitrate' => 123,
				'length' => 123,
				'artist' => array(
					'name' => 'The artist name',
					'image' => 'The image url',
					'uri' => '/api/artist/1',
					'slug' => '1-the-artist-name',
					'id' => 1
				),
				'album' => array(
					'name' => 'The name',
					'cover' => 5,
					'uri' => '/api/album/3',
					'slug' => '3-the-name',
					'id' => 3,
					'year' => 2013,
					'artists' => array(
						array('id' => 1, 'uri' => '/api/artist/1')
					)
				),
				'files' => array(
					'audio/mp3' => 3
				)
			)
		);

		$urlParams = array('fulltree' => true);
		$this->controller = $this->getController($urlParams);

		$response = $this->controller->tracks();

		$this->assertEquals($result, $response->getData());
		$this->assertTrue($response instanceof JSONResponse);
	}

	public function testTrack(){
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

		$trackId = 1;

		$this->api->expects($this->exactly(4))
			->method('linkToRoute')
			->will($this->returnCallback($this->getLinkToRouteFunction()));

		$this->api->expects($this->once())
			->method('getUserId')
			->will($this->returnValue($this->user));
		$this->trackBusinessLayer->expects($this->once())
			->method('find')
			->with($this->equalTo($trackId), $this->equalTo($this->user))
			->will($this->returnValue($track));

		$result = array(
			'title' => 'The title',
			'uri' => '/api/track/1',
			'slug' => '1-the-title',
			'id' => 1,
			'number' => 4,
			'bitrate' => 123,
			'length' => 123,
			'artist' => array('id' => 3, 'uri' => '/api/artist/3'),
			'album' => array('id' => 1, 'uri' => '/api/album/1'),
			'files' => array(
				'audio/mp3' => 3
			)
		);

		$urlParams = array('trackIdOrSlug' => $trackId);
		$this->controller = $this->getController($urlParams);

		$response = $this->controller->track();

		$this->assertEquals($result, $response->getData());
		$this->assertTrue($response instanceof JSONResponse);
	}

	public function testTrackById(){
		$trackId = 1;
		$fileId = 3;

		$track = new Track();
		$track->setId($trackId);
		$track->setTitle('The title');
		$track->setArtistId(3);
		$track->setAlbumId(1);
		$track->setNumber(4);
		$track->setLength(123);
		$track->setFileId($fileId);
		$track->setMimetype('audio/mp3');
		$track->setBitrate(123);

		$this->api->expects($this->exactly(4))
			->method('linkToRoute')
			->will($this->returnCallback($this->getLinkToRouteFunction()));

		$this->api->expects($this->once())
			->method('getUserId')
			->will($this->returnValue($this->user));
		$this->trackBusinessLayer->expects($this->once())
			->method('findByFileId')
			->with($this->equalTo($fileId), $this->equalTo($this->user))
			->will($this->returnValue($track));

		$result = array(
			'title' => 'The title',
			'uri' => '/api/track/1',
			'slug' => '1-the-title',
			'id' => 1,
			'number' => 4,
			'bitrate' => 123,
			'length' => 123,
			'artist' => array('id' => 3, 'uri' => '/api/artist/3'),
			'album' => array('id' => 1, 'uri' => '/api/album/1'),
			'files' => array(
				'audio/mp3' => 3
			)
		);

		$urlParams = array('fileId' => $fileId);
		$this->controller = $this->getController($urlParams);

		$response = $this->controller->trackByFileId();

		$this->assertEquals($result, $response->getData());
		$this->assertTrue($response instanceof JSONResponse);
	}

	public function testTrackFulltree(){
		$this->markTestSkipped();
	}

	public function testTracksByArtist(){
		$track1 = new Track();
		$track1->setId(1);
		$track1->setTitle('The title');
		$track1->setArtistId(3);
		$track1->setAlbumId(1);
		$track1->setNumber(4);
		$track1->setLength(123);
		$track1->setFileId(3);
		$track1->setMimetype('audio/mp3');
		$track1->setBitrate(123);
		$track2 = new Track();
		$track2->setId(2);
		$track2->setTitle('The second title');
		$track2->setArtistId(3);
		$track2->setAlbumId(3);
		$track2->setNumber(5);
		$track2->setLength(103);
		$track2->setFileId(3);
		$track2->setMimetype('audio/mp3');
		$track2->setBitrate(123);

		$artistId = 3;

		$this->api->expects($this->exactly(8))
			->method('linkToRoute')
			->will($this->returnCallback($this->getLinkToRouteFunction()));

		$this->api->expects($this->once())
			->method('getUserId')
			->will($this->returnValue($this->user));
		$this->trackBusinessLayer->expects($this->once())
			->method('findAllByArtist')
			->with($this->equalTo($artistId), $this->equalTo($this->user))
			->will($this->returnValue(array($track1, $track2)));

		$result = array(
			array(
				'title' => 'The title',
				'uri' => '/api/track/1',
				'slug' => '1-the-title',
				'id' => 1,
				'number' => 4,
				'bitrate' => 123,
				'length' => 123,
				'artist' => array('id' => 3, 'uri' => '/api/artist/3'),
				'album' => array('id' => 1, 'uri' => '/api/album/1'),
				'files' => array(
					'audio/mp3' => 3
				)
			),
			array(
				'title' => 'The second title',
				'uri' => '/api/track/2',
				'slug' => '2-the-second-title',
				'id' => 2,
				'number' => 5,
				'bitrate' => 123,
				'length' => 103,
				'artist' => array('id' => 3, 'uri' => '/api/artist/3'),
				'album' => array('id' => 3, 'uri' => '/api/album/3'),
				'files' => array(
					'audio/mp3' => 3
				)
			)
		);

		$urlParams = array('artist' => $artistId);
		$this->controller = $this->getController($urlParams);

		$response = $this->controller->tracks();

		$this->assertEquals($result, $response->getData());
		$this->assertTrue($response instanceof JSONResponse);
	}

	public function testTracksByAlbum(){
		$track1 = new Track();
		$track1->setId(1);
		$track1->setTitle('The title');
		$track1->setArtistId(3);
		$track1->setAlbumId(1);
		$track1->setNumber(4);
		$track1->setLength(123);
		$track1->setFileId(3);
		$track1->setMimetype('audio/mp3');
		$track1->setBitrate(123);
		$track2 = new Track();
		$track2->setId(2);
		$track2->setTitle('The second title');
		$track2->setArtistId(2);
		$track2->setAlbumId(1);
		$track2->setNumber(5);
		$track2->setLength(103);
		$track2->setFileId(3);
		$track2->setMimetype('audio/mp3');
		$track2->setBitrate(123);

		$albumId = 1;

		$this->api->expects($this->exactly(8))
			->method('linkToRoute')
			->will($this->returnCallback($this->getLinkToRouteFunction()));

		$this->api->expects($this->once())
			->method('getUserId')
			->will($this->returnValue($this->user));
		$this->trackBusinessLayer->expects($this->once())
			->method('findAllByAlbum')
			->with($this->equalTo($albumId), $this->equalTo($this->user))
			->will($this->returnValue(array($track1, $track2)));

		$result = array(
			array(
				'title' => 'The title',
				'uri' => '/api/track/1',
				'slug' => '1-the-title',
				'id' => 1,
				'number' => 4,
				'bitrate' => 123,
				'length' => 123,
				'artist' => array('id' => 3, 'uri' => '/api/artist/3'),
				'album' => array('id' => 1, 'uri' => '/api/album/1'),
				'files' => array(
					'audio/mp3' => 3
				)
			),
			array(
				'title' => 'The second title',
				'uri' => '/api/track/2',
				'slug' => '2-the-second-title',
				'id' => 2,
				'number' => 5,
				'bitrate' => 123,
				'length' => 103,
				'artist' => array('id' => 2, 'uri' => '/api/artist/2'),
				'album' => array('id' => 1, 'uri' => '/api/album/1'),
				'files' => array(
					'audio/mp3' => 3
				)
			)
		);

		$urlParams = array('album' => $albumId);
		$this->controller = $this->getController($urlParams);

		$response = $this->controller->tracks();

		$this->assertEquals($result, $response->getData());
		$this->assertTrue($response instanceof JSONResponse);
	}
}
