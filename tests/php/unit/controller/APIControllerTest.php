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

namespace OCA\Music\Controller;

use \OCA\Music\AppFramework\Utility\ControllerTestUtility;
use \OCP\AppFramework\Http\Request;
use \OCP\AppFramework\Http\JSONResponse;

use OCA\Music\DB\Artist;
use OCA\Music\DB\Album;
use OCA\Music\DB\Track;

class APIControllerTest extends ControllerTestUtility {

	private $mapper;
	private $trackBusinessLayer;
	private $artistBusinessLayer;
	private $albumBusinessLayer;
	private $request;
	private $controller;
	private $userId = 'john';
	private $appname = 'music';
	private $urlGenerator;
	private $l10n;

	protected function getController($urlParams){
		return new ApiController(
			$this->appname,
			$this->getRequest(array('urlParams' => $urlParams)),
			$this->urlGenerator,
			$this->trackBusinessLayer,
			$this->artistBusinessLayer,
			$this->albumBusinessLayer,
			$this->scanner,
			$this->userId,
			$this->l10n,
			$this->userFolder);
	}

	protected function setUp(){
		$this->request = $this->getMockBuilder(
			'\OCP\IRequest')
			->disableOriginalConstructor()
			->getMock();
		$this->urlGenerator = $this->getMockBuilder('\OCP\IURLGenerator')
			->disableOriginalConstructor()
			->getMock();
		$this->l10n = $this->getMockBuilder('\OCP\IL10N')
			->disableOriginalConstructor()
			->getMock();
		$this->userFolder = $this->getMockBuilder('\OCP\Files\Folder')
			->disableOriginalConstructor()
			->getMock();

		$this->trackBusinessLayer = $this->getMockBuilder('\OCA\Music\BusinessLayer\TrackBusinessLayer')
			->disableOriginalConstructor()
			->getMock();
		$this->artistBusinessLayer = $this->getMockBuilder('\OCA\Music\BusinessLayer\ArtistBusinessLayer')
			->disableOriginalConstructor()
			->getMock();
		$this->albumBusinessLayer = $this->getMockBuilder('\OCA\Music\BusinessLayer\AlbumBusinessLayer')
			->disableOriginalConstructor()
			->getMock();
		$this->scanner = $this->getMockBuilder('\OCA\Music\Utility\Scanner')
			->disableOriginalConstructor()
			->getMock();
		$this->controller = new ApiController(
			$this->appname,
			$this->request,
			$this->urlGenerator,
			$this->trackBusinessLayer,
			$this->artistBusinessLayer,
			$this->albumBusinessLayer,
			$this->scanner,
			$this->userId,
			$this->l10n,
			$this->userFolder);
	}

	/**
	 * @param string $methodName
	 */
	private function assertAPIControllerAnnotations($methodName){
		$annotations = array('NoAdminRequired', 'NoCSRFRequired');
		$this->assertAnnotations($this->controller, $methodName, $annotations);
	}

	public function testAnnotations(){
		$this->assertAPIControllerAnnotations('artists');
		$this->assertAPIControllerAnnotations('artist');
		$this->assertAPIControllerAnnotations('albums');
		$this->assertAPIControllerAnnotations('album');
		$this->assertAPIControllerAnnotations('tracks');
		$this->assertAPIControllerAnnotations('track');
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

		$this->artistBusinessLayer->expects($this->once())
			->method('findAll')
			->with($this->equalTo($this->userId))
			->will($this->returnValue(array($artist1, $artist2)));

		$result = array(
			array(
				'name' => 'The artist name',
				'image' => 'The image url',
				'uri' => null,
				'slug' => '3-the-artist-name',
				'id' => 3
			),
			array(
				'name' => 'The other artist name',
				'image' => 'The image url number 2',
				'uri' => null,
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

		$this->artistBusinessLayer->expects($this->once())
			->method('findAll')
			->with($this->equalTo($this->userId))
			->will($this->returnValue(array($artist1, $artist2)));
		$this->albumBusinessLayer->expects($this->at(0))
			->method('findAllByArtist')
			->with($this->equalTo(3), $this->equalTo($this->userId))
			->will($this->returnValue(array($album)));
		$this->albumBusinessLayer->expects($this->at(1))
			->method('findAllByArtist')
			->with($this->equalTo(4), $this->equalTo($this->userId))
			->will($this->returnValue(array($album)));
		$this->trackBusinessLayer->expects($this->exactly(2))
			->method('findAllByAlbum')
			->with($this->equalTo($albumId))
			->will($this->returnValue(array($track)));

		$result = array(
			array(
				'name' => 'The artist name',
				'image' => 'The image url',
				'uri' => null,
				'slug' => '3-the-artist-name',
				'id' => 3,
				'albums' => array(
					array(
						'name' => 'The name',
						'cover' => null,
						'uri' => null,
						'slug' => '4-the-name',
						'id' => 4,
						'year' => 2013,
						'artists' => array(
							array('id' => 3, 'uri' => null)
						),
						'tracks' => array(
							array(
								'title' => 'The title',
								'uri' => null,
								'slug' => '1-the-title',
								'id' => 1,
								'number' => 4,
								'bitrate' => 123,
								'length' => 123,
								'artist' => array('id' => 3, 'uri' => null),
								'album' => array('id' => 4, 'uri' => null),
								'files' => array(
									'audio/mp3' => null
								)
							)
						)
					)
				)
			),
			array(
				'name' => 'The other artist name',
				'image' => 'The image url number 2',
				'uri' => null,
				'slug' => '4-the-other-artist-name',
				'id' => 4,
				'albums' => array(
					array(
						'name' => 'The name',
						'cover' => null,
						'uri' => null,
						'slug' => '4-the-name',
						'id' => 4,
						'year' => 2013,
						'artists' => array(
							array('id' => 3, 'uri' => null)
						),
						'tracks' => array(
							array(
								'title' => 'The title',
								'uri' => null,
								'slug' => '1-the-title',
								'id' => 1,
								'number' => 4,
								'bitrate' => 123,
								'length' => 123,
								'artist' => array('id' => 3, 'uri' => null),
								'album' => array('id' => 4, 'uri' => null),
								'files' => array(
									'audio/mp3' => null
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

		$this->artistBusinessLayer->expects($this->once())
			->method('findAll')
			->with($this->equalTo($this->userId))
			->will($this->returnValue(array($artist1, $artist2)));
		$this->albumBusinessLayer->expects($this->at(0))
			->method('findAllByArtist')
			->with($this->equalTo(3), $this->equalTo($this->userId))
			->will($this->returnValue(array($album)));
		$this->albumBusinessLayer->expects($this->at(1))
			->method('findAllByArtist')
			->with($this->equalTo(4), $this->equalTo($this->userId))
			->will($this->returnValue(array($album)));
		$this->trackBusinessLayer->expects($this->never())
			->method('findAllByAlbum');

		$result = array(
			array(
				'name' => 'The artist name',
				'image' => 'The image url',
				'uri' => null,
				'slug' => '3-the-artist-name',
				'id' => 3,
				'albums' => array(
					array(
						'name' => 'The name',
						'cover' => null,
						'uri' => null,
						'slug' => '4-the-name',
						'id' => 4,
						'year' => 2013,
						'artists' => array(
							array('id' => 3, 'uri' => null)
						)
					)
				)
			),
			array(
				'name' => 'The other artist name',
				'image' => 'The image url number 2',
				'uri' => null,
				'slug' => '4-the-other-artist-name',
				'id' => 4,
				'albums' => array(
					array(
						'name' => 'The name',
						'cover' => null,
						'uri' => null,
						'slug' => '4-the-name',
						'id' => 4,
						'year' => 2013,
						'artists' => array(
							array('id' => 3, 'uri' => null)
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

		$this->artistBusinessLayer->expects($this->once())
			->method('find')
			->with($this->equalTo($artistId), $this->equalTo($this->userId))
			->will($this->returnValue($artist));

		$result = array(
			'name' => 'The artist name',
			'image' => 'The image url',
			'uri' => null,
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

		$this->artistBusinessLayer->expects($this->once())
			->method('find')
			->with($this->equalTo($artistId), $this->equalTo($this->userId))
			->will($this->returnValue($artist));
		$this->albumBusinessLayer->expects($this->once())
			->method('findAllByArtist')
			->with($this->equalTo($artistId), $this->equalTo($this->userId))
			->will($this->returnValue(array($album)));
		$this->trackBusinessLayer->expects($this->once())
			->method('findAllByAlbum')
			->with($this->equalTo($albumId))
			->will($this->returnValue(array($track)));

		$result = array(
			'name' => 'The artist name',
			'image' => 'The image url',
			'uri' => null,
			'slug' => '3-the-artist-name',
			'id' => 3,
			'albums' => array(
				array(
					'name' => 'The name',
					'cover' => null,
					'uri' => null,
					'slug' => '3-the-name',
					'id' => 3,
					'year' => 2013,
					'artists' => array(
						array('id' => 3, 'uri' => null)
					),
					'tracks' => array(
						array(
							'title' => 'The title',
							'uri' => null,
							'slug' => '1-the-title',
							'id' => 1,
							'number' => 4,
							'bitrate' => 123,
							'length' => 123,
							'artist' => array('id' => 3, 'uri' => null),
							'album' => array('id' => 1, 'uri' => null),
							'files' => array(
								'audio/mp3' => null
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

		$this->albumBusinessLayer->expects($this->once())
			->method('findAll')
			->with($this->equalTo($this->userId))
			->will($this->returnValue(array($album1, $album2)));

		$result = array(
			array(
				'name' => 'The name',
				'cover' => null,
				'uri' => null,
				'slug' => '3-the-name',
				'id' => 3,
				'year' => 2013,
				'artists' => array(
					array('id' => 1, 'uri' => null)
				)
			),
			array(
				'name' => 'The album name',
				'cover' => null,
				'uri' => null,
				'slug' => '4-the-album-name',
				'id' => 4,
				'year' => 2003,
				'artists' => array(
					array('id' => 3, 'uri' => null),
					array('id' => 5, 'uri' => null)
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

		$this->albumBusinessLayer->expects($this->once())
			->method('findAll')
			->with($this->equalTo($this->userId))
			->will($this->returnValue(array($album1, $album2)));
		$this->artistBusinessLayer->expects($this->at(0))
			->method('findMultipleById')
			->with($this->equalTo(array(1)), $this->equalTo($this->userId))
			->will($this->returnValue(array($artist1)));
		$this->artistBusinessLayer->expects($this->at(1))
			->method('findMultipleById')
			->with($this->equalTo(array(3,5)), $this->equalTo($this->userId))
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
				'cover' => null,
				'uri' => null,
				'slug' => '3-the-name',
				'id' => 3,
				'year' => 2013,
				'artists' => array(
					array(
						'name' => 'The artist name',
						'image' => 'The image url',
						'uri' => null,
						'slug' => '1-the-artist-name',
						'id' => 1
					)
				),
				'tracks' => array(
					array(
						'title' => 'The title',
						'uri' => null,
						'slug' => '1-the-title',
						'id' => 1,
						'number' => 4,
						'bitrate' => 123,
						'length' => 123,
						'artist' => array('id' => 3, 'uri' => null),
						'album' => array('id' => 4, 'uri' => null),
						'files' => array(
							'audio/mp3' => null
						)
					)
				)
			),
			array(
				'name' => 'The album name',
				'cover' => null,
				'uri' => null,
				'slug' => '4-the-album-name',
				'id' => 4,
				'year' => 2003,
				'artists' => array(
					array(
						'name' => 'The artist name3',
						'image' => 'The image url3',
						'uri' => null,
						'slug' => '3-the-artist-name3',
						'id' => 3
					),
					array(
						'name' => 'The artist name5',
						'image' => 'The image url5',
						'uri' => null,
						'slug' => '5-the-artist-name5',
						'id' => 5
					)
				),
				'tracks' => array(
					array(
						'title' => 'The title',
						'uri' => null,
						'slug' => '1-the-title',
						'id' => 1,
						'number' => 4,
						'bitrate' => 123,
						'length' => 123,
						'artist' => array('id' => 3, 'uri' => null),
						'album' => array('id' => 4, 'uri' => null),
						'files' => array(
							'audio/mp3' => null
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

		$this->albumBusinessLayer->expects($this->once())
			->method('find')
			->with($this->equalTo($albumId), $this->equalTo($this->userId))
			->will($this->returnValue($album));
		$this->artistBusinessLayer->expects($this->once())
			->method('findMultipleById')
			->with($this->equalTo(array(1)), $this->equalTo($this->userId))
			->will($this->returnValue(array($artist)));
		$this->trackBusinessLayer->expects($this->once())
			->method('findAllByAlbum')
			->with($this->equalTo($albumId))
			->will($this->returnValue(array($track)));

		$result = array(
			'name' => 'The name',
			'cover' => null,
			'uri' => null,
			'slug' => '3-the-name',
			'id' => 3,
			'year' => 2013,
			'artists' => array(
				array(
					'name' => 'The artist name',
					'image' => 'The image url',
					'uri' => null,
					'slug' => '1-the-artist-name',
					'id' => 1
				)
			),
			'tracks' => array(
				array(
					'title' => 'The title',
					'uri' => null,
					'slug' => '1-the-title',
					'id' => 1,
					'number' => 4,
					'bitrate' => 123,
					'length' => 123,
					'artist' => array('id' => 3, 'uri' => null),
					'album' => array('id' => 4, 'uri' => null),
					'files' => array(
						'audio/mp3' => null
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

		$this->albumBusinessLayer->expects($this->once())
			->method('find')
			->with($this->equalTo($albumId), $this->equalTo($this->userId))
			->will($this->returnValue($album));

		$result = array(
			'name' => 'The name',
			'cover' => null,
			'uri' => null,
			'slug' => '3-the-name',
			'id' => 3,
			'year' => 2013,
			'artists' => array(
				array('id' => 1, 'uri' => null)
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

		$this->trackBusinessLayer->expects($this->once())
			->method('findAll')
			->with($this->equalTo($this->userId))
			->will($this->returnValue(array($track1, $track2)));

		$result = array(
			array(
				'title' => 'The title',
				'uri' => null,
				'slug' => '1-the-title',
				'id' => 1,
				'number' => 4,
				'bitrate' => 123,
				'length' => 123,
				'artist' => array('id' => 3, 'uri' => null),
				'album' => array('id' => 1, 'uri' => null),
				'files' => array(
					'audio/mp3' => null
				)
			),
			array(
				'title' => 'The second title',
				'uri' => null,
				'slug' => '2-the-second-title',
				'id' => 2,
				'number' => 5,
				'bitrate' => 123,
				'length' => 103,
				'artist' => array('id' => 2, 'uri' => null),
				'album' => array('id' => 3, 'uri' => null),
				'files' => array(
					'audio/mp3' => null
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

		$this->trackBusinessLayer->expects($this->once())
			->method('findAll')
			->with($this->equalTo($this->userId))
			->will($this->returnValue(array($track1)));
		$this->artistBusinessLayer->expects($this->once())
			->method('find')
			->with($this->equalTo(3), $this->equalTo($this->userId))
			->will($this->returnValue($artist));
		$this->albumBusinessLayer->expects($this->once())
			->method('find')
			->with($this->equalTo(1), $this->equalTo($this->userId))
			->will($this->returnValue($album ));

		$result = array(
			array(
				'title' => 'The title',
				'uri' => null,
				'slug' => '1-the-title',
				'id' => 1,
				'number' => 4,
				'bitrate' => 123,
				'length' => 123,
				'artist' => array(
					'name' => 'The artist name',
					'image' => 'The image url',
					'uri' => null,
					'slug' => '1-the-artist-name',
					'id' => 1
				),
				'album' => array(
					'name' => 'The name',
					'cover' => null,
					'uri' => null,
					'slug' => '3-the-name',
					'id' => 3,
					'year' => 2013,
					'artists' => array(
						array('id' => 1, 'uri' => null)
					)
				),
				'files' => array(
					'audio/mp3' => null
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

		$this->trackBusinessLayer->expects($this->once())
			->method('find')
			->with($this->equalTo($trackId), $this->equalTo($this->userId))
			->will($this->returnValue($track));

		$result = array(
			'title' => 'The title',
			'uri' => null,
			'slug' => '1-the-title',
			'id' => 1,
			'number' => 4,
			'bitrate' => 123,
			'length' => 123,
			'artist' => array('id' => 3, 'uri' => null),
			'album' => array('id' => 1, 'uri' => null),
			'files' => array(
				'audio/mp3' => null
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

		$this->trackBusinessLayer->expects($this->once())
			->method('findByFileId')
			->with($this->equalTo($fileId), $this->equalTo($this->userId))
			->will($this->returnValue($track));

		$result = array(
			'title' => 'The title',
			'id' => 1,
			'number' => 4,
			'artistId' => 3,
			'albumId' => 1,
			'files' => array(
				'audio/mp3' => 'relative/funny/path'
			)
		);

		$urlParams = array('fileId' => $fileId);
		$this->controller = $this->getController($urlParams);


		$node = $this->getMockBuilder('\OCP\Files\Node')
			->disableOriginalConstructor()
			->getMock();
		$node->expects($this->once())
			->method('getPath')
			->will($this->returnValue('funny/path'));
		$this->userFolder->expects($this->once())
			->method('getById')
			->with($this->equalTo($fileId))
			->will($this->returnValue(array($node)));
		$this->userFolder->expects($this->once())
			->method('getRelativePath')
			->with($this->equalTo('funny/path'))
			->will($this->returnValue('/relative/funny/path'));
		$this->urlGenerator->expects($this->once())
			->method('getAbsoluteURL')
			->with($this->equalTo('remote.php/webdav/relative/funny/path'))
			->will($this->returnValue('relative/funny/path'));

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

		$this->trackBusinessLayer->expects($this->once())
			->method('findAllByArtist')
			->with($this->equalTo($artistId), $this->equalTo($this->userId))
			->will($this->returnValue(array($track1, $track2)));

		$result = array(
			array(
				'title' => 'The title',
				'uri' => null,
				'slug' => '1-the-title',
				'id' => 1,
				'number' => 4,
				'bitrate' => 123,
				'length' => 123,
				'artist' => array('id' => 3, 'uri' => null),
				'album' => array('id' => 1, 'uri' => null),
				'files' => array(
					'audio/mp3' => null
				)
			),
			array(
				'title' => 'The second title',
				'uri' => null,
				'slug' => '2-the-second-title',
				'id' => 2,
				'number' => 5,
				'bitrate' => 123,
				'length' => 103,
				'artist' => array('id' => 3, 'uri' => null),
				'album' => array('id' => 3, 'uri' => null),
				'files' => array(
					'audio/mp3' => null
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

		$this->trackBusinessLayer->expects($this->once())
			->method('findAllByAlbum')
			->with($this->equalTo($albumId), $this->equalTo($this->userId))
			->will($this->returnValue(array($track1, $track2)));

		$result = array(
			array(
				'title' => 'The title',
				'uri' => null,
				'slug' => '1-the-title',
				'id' => 1,
				'number' => 4,
				'bitrate' => 123,
				'length' => 123,
				'artist' => array('id' => 3, 'uri' => null),
				'album' => array('id' => 1, 'uri' => null),
				'files' => array(
					'audio/mp3' => null
				)
			),
			array(
				'title' => 'The second title',
				'uri' => null,
				'slug' => '2-the-second-title',
				'id' => 2,
				'number' => 5,
				'bitrate' => 123,
				'length' => 103,
				'artist' => array('id' => 2, 'uri' => null),
				'album' => array('id' => 1, 'uri' => null),
				'files' => array(
					'audio/mp3' => null
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
