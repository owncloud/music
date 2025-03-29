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
 * @copyright Pauli Järvinen 2016 - 2025
 */

namespace OCA\Music\Controller;

use OCA\Music\Tests\Utility\ControllerTestUtility;
use OCP\AppFramework\Http\JSONResponse;

use OCA\Music\Db\Artist;
use OCA\Music\Db\Album;
use OCA\Music\Db\Track;

class ShivaApiControllerTest extends ControllerTestUtility {
	private $trackBusinessLayer;
	private $artistBusinessLayer;
	private $albumBusinessLayer;
	private $request;
	private $controller;
	private $userId = 'john';
	private $appname = 'music';
	private $urlGenerator;
	private $l10n;
	private $logger;

	protected function setUp() : void {
		$this->request = $this->getMockBuilder('\OCP\IRequest')
			->disableOriginalConstructor()
			->getMock();
		$this->urlGenerator = $this->getMockBuilder('\OCP\IURLGenerator')
			->disableOriginalConstructor()
			->getMock();
		$this->urlGenerator
			->method('linkToRoute')
			->will($this->returnCallback([$this, 'linkToRouteMock']));
		$this->l10n = $this->getMockBuilder('\OCP\IL10N')
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
		$this->logger = $this->getMockBuilder('\OCA\Music\AppFramework\Core\Logger')
			->disableOriginalConstructor()
			->getMock();
		$this->controller = new ShivaApiController(
			$this->appname,
			$this->request,
			$this->urlGenerator,
			$this->trackBusinessLayer,
			$this->artistBusinessLayer,
			$this->albumBusinessLayer,
			$this->userId,
			$this->l10n,
			$this->logger);
	}

	public static function linkToRouteMock(string $route, array $args) : string {
		switch ($route) {
			case 'music.shivaApi.artist':		return "/link/to/artist/{$args['id']}";
			case 'music.shivaApi.album':		return "/link/to/album/{$args['id']}";
			case 'music.shivaApi.track':		return "/link/to/track/{$args['id']}";
			case 'music.musicApi.download':		return "/link/to/file/{$args['fileId']}";
			case 'music.coverApi.artistCover':	return "/link/to/artist/cover/{$args['artistId']}";
			case 'music.coverApi.albumCover':	return "/link/to/album/cover/{$args['albumId']}";
			default:							return "(mock missing for route $route)";
		}
	}

	/**
	 * @param string $methodName
	 */
	private function assertAPIControllerAnnotations($methodName) {
		$annotations = ['NoAdminRequired', 'NoCSRFRequired'];
		$this->assertAnnotations($this->controller, $methodName, $annotations);
	}

	public function testAnnotations() {
		$this->assertAPIControllerAnnotations('artists');
		$this->assertAPIControllerAnnotations('artist');
		$this->assertAPIControllerAnnotations('albums');
		$this->assertAPIControllerAnnotations('album');
		$this->assertAPIControllerAnnotations('tracks');
		$this->assertAPIControllerAnnotations('track');
	}

	public function testArtists() {
		$artist1 = new Artist();
		$artist1->setId(3);
		$artist1->setName('The artist name');
		$artist1->setCoverFileId(10);
		$artist2 = new Artist();
		$artist2->setId(4);
		$artist2->setName('The other artist name');
		$artist2->setCoverFileId(11);

		$this->artistBusinessLayer->expects($this->once())
			->method('findAll')
			->with($this->equalTo($this->userId))
			->will($this->returnValue([$artist1, $artist2]));

		$result = [
			[
				'name' => 'The artist name',
				'image' => '/link/to/artist/cover/3',
				'uri' => '/link/to/artist/3',
				'slug' => 'the-artist-name',
				'id' => 3
			],
			[
				'name' => 'The other artist name',
				'image' => '/link/to/artist/cover/4',
				'uri' => '/link/to/artist/4',
				'slug' => 'the-other-artist-name',
				'id' => 4
			]
		];

		$response = $this->controller->artists(false /*fulltree*/, false /*albums*/);

		$this->assertEquals($result, $response->getData());
		$this->assertTrue($response instanceof JSONResponse);
	}

	public function testArtistsFulltree() {
		$artist1 = new Artist();
		$artist1->setId(3);
		$artist1->setName('The artist name');
		$artist1->setCoverFileId(10);
		$artist2 = new Artist();
		$artist2->setId(4);
		$artist2->setName('The other artist name');
		$artist2->setCoverFileId(11);
		$artist3 = new Artist();
		$artist3->setId(5);
		$artist3->setName('The other new artist name');
		$artist3->setCoverFileId(12);
		$album = new Album();
		$album->setId(4);
		$album->setName('The name');
		$album->setYears([2011, 2013]);
		$album->setCoverFileId(5);
		$album->setArtistIds([3]);
		$album->setAlbumArtistId(5);
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
			->will($this->returnValue([$artist1, $artist2]));
		$this->albumBusinessLayer->expects($this->at(0))
			->method('findAllByArtist')
			->with($this->equalTo(3), $this->equalTo($this->userId))
			->will($this->returnValue([$album]));
		$this->albumBusinessLayer->expects($this->at(1))
			->method('findAllByArtist')
			->with($this->equalTo(4), $this->equalTo($this->userId))
			->will($this->returnValue([$album]));
		$this->trackBusinessLayer->expects($this->exactly(2))
			->method('findAllByAlbum')
			->with($this->equalTo($albumId))
			->will($this->returnValue([$track]));

		$result = [
			[
				'name' => 'The artist name',
				'image' => '/link/to/artist/cover/3',
				'uri' => '/link/to/artist/3',
				'slug' => 'the-artist-name',
				'id' => 3,
				'albums' => [
					[
						'name' => 'The name',
						'cover' => '/link/to/album/cover/4',
						'uri' => '/link/to/album/4',
						'slug' => 'the-name',
						'id' => 4,
						'year' => 2013,
						'artists' => [
							['id' => 3, 'uri' => '/link/to/artist/3']
						],
						'albumArtistId' => 5,
						'tracks' => [
							[
								'title' => 'The title',
								'uri' => '/link/to/track/1',
								'slug' => 'the-title',
								'id' => 1,
								'ordinal' => 4,
								'bitrate' => 123,
								'length' => 123,
								'artist' => ['id' => 3, 'uri' => '/link/to/artist/3'],
								'album' => ['id' => 4, 'uri' => '/link/to/album/4'],
								'files' => [
									'audio/mp3' => '/link/to/file/3'
								]
							]
						]
					]
				]
			],
			[
				'name' => 'The other artist name',
				'image' => '/link/to/artist/cover/4',
				'uri' => '/link/to/artist/4',
				'slug' => 'the-other-artist-name',
				'id' => 4,
				'albums' => [
					[
						'name' => 'The name',
						'cover' => '/link/to/album/cover/4',
						'uri' => '/link/to/album/4',
						'slug' => 'the-name',
						'id' => 4,
						'year' => 2013,
						'artists' => [
							['id' => 3, 'uri' => '/link/to/artist/3']
						],
						'albumArtistId' => 5,
						'tracks' => [
							[
								'title' => 'The title',
								'uri' => '/link/to/track/1',
								'slug' => 'the-title',
								'id' => 1,
								'ordinal' => 4,
								'bitrate' => 123,
								'length' => 123,
								'artist' => ['id' => 3, 'uri' => '/link/to/artist/3'],
								'album' => ['id' => 4, 'uri' => '/link/to/album/4'],
								'files' => [
									'audio/mp3' => '/link/to/file/3'
								]
							]
						]
					]
				]
			]
		];

		$response = $this->controller->artists(true /*fulltree*/, false /*albums*/);

		$this->assertEquals($result, $response->getData());
		$this->assertTrue($response instanceof JSONResponse);
	}

	public function testArtistsAlbumsOnlyFulltree() {
		$artist1 = new Artist();
		$artist1->setId(3);
		$artist1->setName('The artist name');
		$artist1->setCoverFileId(100);
		$artist2 = new Artist();
		$artist2->setId(4);
		$artist2->setName('The other artist name');
		$artist2->setCoverFileId(200);
		$album = new Album();
		$album->setId(4);
		$album->setName('The name');
		$album->setYears([2013]);
		$album->setCoverFileId(5);
		$album->setArtistIds([3]);
		$album->setAlbumArtistId(3);

		$this->artistBusinessLayer->expects($this->once())
			->method('findAll')
			->with($this->equalTo($this->userId))
			->will($this->returnValue([$artist1, $artist2]));
		$this->albumBusinessLayer->expects($this->at(0))
			->method('findAllByArtist')
			->with($this->equalTo(3), $this->equalTo($this->userId))
			->will($this->returnValue([$album]));
		$this->albumBusinessLayer->expects($this->at(1))
			->method('findAllByArtist')
			->with($this->equalTo(4), $this->equalTo($this->userId))
			->will($this->returnValue([$album]));
		$this->trackBusinessLayer->expects($this->never())
			->method('findAllByAlbum');

		$result = [
			[
				'name' => 'The artist name',
				'image' => '/link/to/artist/cover/3',
				'uri' => '/link/to/artist/3',
				'slug' => 'the-artist-name',
				'id' => 3,
				'albums' => [
					[
						'name' => 'The name',
						'cover' => '/link/to/album/cover/4',
						'uri' => '/link/to/album/4',
						'slug' => 'the-name',
						'id' => 4,
						'year' => 2013,
						'artists' => [
							['id' => 3, 'uri' => '/link/to/artist/3']
						],
						'albumArtistId' => 3
					],
				]
			],
			[
				'name' => 'The other artist name',
				'image' => '/link/to/artist/cover/4',
				'uri' => '/link/to/artist/4',
				'slug' => 'the-other-artist-name',
				'id' => 4,
				'albums' => [
					[
						'name' => 'The name',
						'cover' => '/link/to/album/cover/4',
						'uri' => '/link/to/album/4',
						'slug' => 'the-name',
						'id' => 4,
						'year' => 2013,
						'artists' => [
							['id' => 3, 'uri' => '/link/to/artist/3']
						],
						'albumArtistId' => 3
					]
				]
			]
		];

		$response = $this->controller->artists(false /*fultree*/, true /*albums*/);

		$this->assertEquals($result, $response->getData());
		$this->assertTrue($response instanceof JSONResponse);
	}

	public function testArtist() {
		$artist = new Artist();
		$artist->setId(3);
		$artist->setName('The artist name');
		$artist->setCoverFileId(null);

		$artistId = 3;

		$this->artistBusinessLayer->expects($this->once())
			->method('find')
			->with($this->equalTo($artistId), $this->equalTo($this->userId))
			->will($this->returnValue($artist));

		$result = [
			'name' => 'The artist name',
			'image' => null,
			'uri' => '/link/to/artist/3',
			'slug' => 'the-artist-name',
			'id' => 3
		];

		$response = $this->controller->artist($artistId, false /*fulltree*/);

		$this->assertEquals($result, $response->getData());
		$this->assertTrue($response instanceof JSONResponse);
	}

	public function testArtistFulltree() {
		$artist = new Artist();
		$artist->setId(3);
		$artist->setName('The artist name');
		$artist->setCoverFileId(null);
		$album = new Album();
		$album->setId(3);
		$album->setName('The name');
		$album->setYears([1999, 2000, 2013]);
		$album->setCoverFileId(5);
		$album->setArtistIds([3]);
		$album->setAlbumArtistId(3);
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
			->will($this->returnValue([$album]));
		$this->trackBusinessLayer->expects($this->once())
			->method('findAllByAlbum')
			->with($this->equalTo($albumId))
			->will($this->returnValue([$track]));

		$result = [
			'name' => 'The artist name',
			'image' => null,
			'uri' => '/link/to/artist/3',
			'slug' => 'the-artist-name',
			'id' => 3,
			'albums' => [
				[
					'name' => 'The name',
					'cover' => '/link/to/album/cover/3',
					'uri' => '/link/to/album/3',
					'slug' => 'the-name',
					'id' => 3,
					'year' => 2013,
					'artists' => [
						['id' => 3, 'uri' => '/link/to/artist/3']
					],
					'albumArtistId' => 3,
					'tracks' => [
						[
							'title' => 'The title',
							'uri' => '/link/to/track/1',
							'slug' => 'the-title',
							'id' => 1,
							'ordinal' => 4,
							'bitrate' => 123,
							'length' => 123,
							'artist' => ['id' => 3, 'uri' => '/link/to/artist/3'],
							'album' => ['id' => 1, 'uri' => '/link/to/album/1'],
							'files' => [
								'audio/mp3' => '/link/to/file/3'
							]
						]
					]
				]
			]
		];

		$response = $this->controller->artist($artistId, true /*fulltree*/);

		$this->assertEquals($result, $response->getData());
		$this->assertTrue($response instanceof JSONResponse);
	}

	public function testAlbums() {
		$album1 = new Album();
		$album1->setId(3);
		$album1->setName('The name');
		$album1->setYears([2013]);
		$album1->setCoverFileId(5);
		$album1->setArtistIds([1]);
		$album1->setAlbumArtistId(1);
		$album2 = new Album();
		$album2->setId(4);
		$album2->setName('The album name');
		$album2->setYears([]);
		$album2->setCoverFileId(7);
		$album2->setArtistIds([3,5]);
		$album2->setAlbumArtistId(2);

		$this->albumBusinessLayer->expects($this->once())
			->method('findAll')
			->with($this->equalTo($this->userId))
			->will($this->returnValue([$album1, $album2]));

		$result = [
			[
				'name' => 'The name',
				'cover' => '/link/to/album/cover/3',
				'uri' => '/link/to/album/3',
				'slug' => 'the-name',
				'id' => 3,
				'year' => 2013,
				'artists' => [
					['id' => 1, 'uri' => '/link/to/artist/1']
				],
				'albumArtistId' => 1
			],
			[
				'name' => 'The album name',
				'cover' => '/link/to/album/cover/4',
				'uri' => '/link/to/album/4',
				'slug' => 'the-album-name',
				'id' => 4,
				'year' => null,
				'artists' => [
					['id' => 3, 'uri' => '/link/to/artist/3'],
					['id' => 5, 'uri' => '/link/to/artist/5']
				],
				'albumArtistId' => 2
			]
		];

		$response = $this->controller->albums(null /*artist*/, false /*fulltree*/);

		$this->assertEquals($result, $response->getData());
		$this->assertTrue($response instanceof JSONResponse);
	}

	public function testAlbumsFulltree() {
		$album1 = new Album();
		$album1->setId(3);
		$album1->setName('The name');
		$album1->setYears([2013]);
		$album1->setCoverFileId(5);
		$album1->setArtistIds([1]);
		$album1->setAlbumArtistId(5);
		$album2 = new Album();
		$album2->setId(4);
		$album2->setName('The album name');
		$album2->setYears([2003]);
		$album2->setCoverFileId(7);
		$album2->setArtistIds([3,5]);
		$album2->setAlbumArtistId(1);
		$artist1 = new Artist();
		$artist1->setId(1);
		$artist1->setName('The artist name');
		$artist1->setCoverFileId(null);
		$artist2 = new Artist();
		$artist2->setId(3);
		$artist2->setName('The artist name3');
		$artist2->setCoverFileId(null);
		$artist3 = new Artist();
		$artist3->setId(5);
		$artist3->setName('The artist name5');
		$artist3->setCoverFileId(100000);
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
			->will($this->returnValue([$album1, $album2]));
		$this->artistBusinessLayer->expects($this->at(0))
			->method('findById')
			->with($this->equalTo([1]), $this->equalTo($this->userId))
			->will($this->returnValue([$artist1]));
		$this->artistBusinessLayer->expects($this->at(1))
			->method('findById')
			->with($this->equalTo([3,5]), $this->equalTo($this->userId))
			->will($this->returnValue([$artist2, $artist3]));
		$this->trackBusinessLayer->expects($this->at(0))
			->method('findAllByAlbum')
			->with($this->equalTo(3))
			->will($this->returnValue([$track]));
		$this->trackBusinessLayer->expects($this->at(1))
			->method('findAllByAlbum')
			->with($this->equalTo(4))
			->will($this->returnValue([$track]));

		$result = [
			[
				'name' => 'The name',
				'cover' => '/link/to/album/cover/3',
				'uri' => '/link/to/album/3',
				'slug' => 'the-name',
				'id' => 3,
				'year' => 2013,
				'artists' => [
					[
						'name' => 'The artist name',
						'image' => null,
						'uri' => '/link/to/artist/1',
						'slug' => 'the-artist-name',
						'id' => 1
					]
				],
				'albumArtistId' => 5,
				'tracks' => [
					[
						'title' => 'The title',
						'uri' => '/link/to/track/1',
						'slug' => 'the-title',
						'id' => 1,
						'ordinal' => 4,
						'bitrate' => 123,
						'length' => 123,
						'artist' => ['id' => 3, 'uri' => '/link/to/artist/3'],
						'album' => ['id' => 4, 'uri' => '/link/to/album/4'],
						'files' => [
							'audio/mp3' => '/link/to/file/3'
						]
					]
				]
			],
			[
				'name' => 'The album name',
				'cover' => '/link/to/album/cover/4',
				'uri' => '/link/to/album/4',
				'slug' => 'the-album-name',
				'id' => 4,
				'year' => 2003,
				'artists' => [
					[
						'name' => 'The artist name3',
						'image' => null,
						'uri' => '/link/to/artist/3',
						'slug' => 'the-artist-name3',
						'id' => 3
					],
					[
						'name' => 'The artist name5',
						'image' => '/link/to/artist/cover/5',
						'uri' => '/link/to/artist/5',
						'slug' => 'the-artist-name5',
						'id' => 5
					]
				],
				'albumArtistId' => 1,
				'tracks' => [
					[
						'title' => 'The title',
						'uri' => '/link/to/track/1',
						'slug' => 'the-title',
						'id' => 1,
						'ordinal' => 4,
						'bitrate' => 123,
						'length' => 123,
						'artist' => ['id' => 3, 'uri' => '/link/to/artist/3'],
						'album' => ['id' => 4, 'uri' => '/link/to/album/4'],
						'files' => [
							'audio/mp3' => '/link/to/file/3'
						]
					]
				]
			]
		];

		$response = $this->controller->albums(null /*artist*/, true /*fulltree*/);

		$this->assertEquals($result, $response->getData());
		$this->assertTrue($response instanceof JSONResponse);
	}

	public function testAlbum() {
		$album = new Album();
		$album->setId(3);
		$album->setName('The name');
		$album->setYears([2013]);
		$album->setCoverFileId(5);
		$album->setArtistIds([1]);
		$album->setAlbumArtistId(1);
		$artist = new Artist();
		$artist->setId(1);
		$artist->setName('The artist name');
		$artist->setCoverFileId(199);
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
			->method('findById')
			->with($this->equalTo([1]), $this->equalTo($this->userId))
			->will($this->returnValue([$artist]));
		$this->trackBusinessLayer->expects($this->once())
			->method('findAllByAlbum')
			->with($this->equalTo($albumId))
			->will($this->returnValue([$track]));

		$result = [
			'name' => 'The name',
			'cover' => '/link/to/album/cover/3',
			'uri' => '/link/to/album/3',
			'slug' => 'the-name',
			'id' => 3,
			'year' => 2013,
			'artists' => [
				[
					'name' => 'The artist name',
					'image' => '/link/to/artist/cover/1',
					'uri' => '/link/to/artist/1',
					'slug' => 'the-artist-name',
					'id' => 1
				]
			],
			'albumArtistId' => 1,
			'tracks' => [
				[
					'title' => 'The title',
					'uri' => '/link/to/track/1',
					'slug' => 'the-title',
					'id' => 1,
					'ordinal' => 4,
					'bitrate' => 123,
					'length' => 123,
					'artist' => ['id' => 3, 'uri' => '/link/to/artist/3'],
					'album' => ['id' => 4, 'uri' => '/link/to/album/4'],
					'files' => [
						'audio/mp3' => '/link/to/file/3'
					]
				]
			]
		];

		$response = $this->controller->album($albumId, true /*fulltree*/);

		$this->assertEquals($result, $response->getData());
		$this->assertTrue($response instanceof JSONResponse);
	}

	public function testAlbumFulltree() {
		$album = new Album();
		$album->setId(3);
		$album->setName('The name');
		$album->setYears([2013]);
		$album->setCoverFileId(5);
		$album->setArtistIds([1]);
		$album->setAlbumArtistId(2);

		$albumId = 3;

		$this->albumBusinessLayer->expects($this->once())
			->method('find')
			->with($this->equalTo($albumId), $this->equalTo($this->userId))
			->will($this->returnValue($album));

		$result = [
			'name' => 'The name',
			'cover' => '/link/to/album/cover/3',
			'uri' => '/link/to/album/3',
			'slug' => 'the-name',
			'id' => 3,
			'year' => 2013,
			'artists' => [
				['id' => 1, 'uri' => '/link/to/artist/1']
			],
			'albumArtistId' => 2
		];

		$response = $this->controller->album($albumId, false /*fulltree*/);

		$this->assertEquals($result, $response->getData());
		$this->assertTrue($response instanceof JSONResponse);
	}

	public function testTracks() {
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
		$track2->setFileId(4);
		$track2->setMimetype('audio/mp3');
		$track2->setBitrate(123);

		$this->trackBusinessLayer->expects($this->once())
			->method('findAll')
			->with($this->equalTo($this->userId))
			->will($this->returnValue([$track1, $track2]));

		$result = [
			[
				'title' => 'The title',
				'uri' => '/link/to/track/1',
				'slug' => 'the-title',
				'id' => 1,
				'ordinal' => 4,
				'bitrate' => 123,
				'length' => 123,
				'artist' => ['id' => 3, 'uri' => '/link/to/artist/3'],
				'album' => ['id' => 1, 'uri' => '/link/to/album/1'],
				'files' => [
					'audio/mp3' => '/link/to/file/3'
				]
			],
			[
				'title' => 'The second title',
				'uri' => '/link/to/track/2',
				'slug' => 'the-second-title',
				'id' => 2,
				'ordinal' => 5,
				'bitrate' => 123,
				'length' => 103,
				'artist' => ['id' => 2, 'uri' => '/link/to/artist/2'],
				'album' => ['id' => 3, 'uri' => '/link/to/album/3'],
				'files' => [
					'audio/mp3' => '/link/to/file/4'
				]
			]
		];

		$response = $this->controller->tracks();

		$this->assertEquals($result, $response->getData());
		$this->assertTrue($response instanceof JSONResponse);
	}

	public function testTracksFulltree() {
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
		$album->setYears([2013]);
		$album->setCoverFileId(5);
		$album->setArtistIds([1]);
		$album->setAlbumArtistId(2);
		$artist = new Artist();
		$artist->setId(1);
		$artist->setName('The artist name');
		$artist->setCoverFileId(1111);
		$artist2 = new Artist();
		$artist2->setId(2);
		$artist2->setName('The other artist name');
		$artist2->setCoverFileId(2222);

		$this->trackBusinessLayer->expects($this->once())
			->method('findAll')
			->with($this->equalTo($this->userId))
			->will($this->returnValue([$track1]));
		$this->artistBusinessLayer->expects($this->once())
			->method('find')
			->with($this->equalTo(3), $this->equalTo($this->userId))
			->will($this->returnValue($artist));
		$this->albumBusinessLayer->expects($this->once())
			->method('find')
			->with($this->equalTo(1), $this->equalTo($this->userId))
			->will($this->returnValue($album));

		$result = [
			[
				'title' => 'The title',
				'uri' => '/link/to/track/1',
				'slug' => 'the-title',
				'id' => 1,
				'ordinal' => 4,
				'bitrate' => 123,
				'length' => 123,
				'artist' => [
					'name' => 'The artist name',
					'image' => '/link/to/artist/cover/1',
					'uri' => '/link/to/artist/1',
					'slug' => 'the-artist-name',
					'id' => 1
				],
				'album' => [
					'name' => 'The name',
					'cover' => '/link/to/album/cover/3',
					'uri' => '/link/to/album/3',
					'slug' => 'the-name',
					'id' => 3,
					'year' => 2013,
					'artists' => [
						['id' => 1, 'uri' => '/link/to/artist/1']
					],
					'albumArtistId' => 2
				],
				'files' => [
					'audio/mp3' => '/link/to/file/3'
				]
			]
		];

		$response = $this->controller->tracks(null /*artist*/, null /*album*/, true /*fulltree*/);

		$this->assertEquals($result, $response->getData());
		$this->assertTrue($response instanceof JSONResponse);
	}

	public function testTrack() {
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

		$result = [
			'title' => 'The title',
			'uri' => '/link/to/track/1',
			'slug' => 'the-title',
			'id' => 1,
			'ordinal' => 4,
			'bitrate' => 123,
			'length' => 123,
			'artist' => ['id' => 3, 'uri' => '/link/to/artist/3'],
			'album' => ['id' => 1, 'uri' => '/link/to/album/1'],
			'files' => [
				'audio/mp3' => '/link/to/file/3'
			]
		];

		$response = $this->controller->track($trackId);

		$this->assertEquals($result, $response->getData());
		$this->assertTrue($response instanceof JSONResponse);
	}

	public function testTrackFulltree() {
		$this->markTestSkipped();
	}

	public function testTracksByArtist() {
		$track1 = new Track();
		$track1->setId(1);
		$track1->setTitle('The title');
		$track1->setArtistId(3);
		$track1->setAlbumId(1);
		$track1->setNumber(4);
		$track1->setLength(123);
		$track1->setFileId(111);
		$track1->setMimetype('audio/mp3');
		$track1->setBitrate(123);
		$track2 = new Track();
		$track2->setId(2);
		$track2->setTitle('The second title');
		$track2->setArtistId(3);
		$track2->setAlbumId(3);
		$track2->setNumber(5);
		$track2->setLength(103);
		$track2->setFileId(222);
		$track2->setMimetype('audio/mp3');
		$track2->setBitrate(123);

		$artistId = 3;

		$this->trackBusinessLayer->expects($this->once())
			->method('findAllByArtist')
			->with($this->equalTo($artistId), $this->equalTo($this->userId))
			->will($this->returnValue([$track1, $track2]));

		$result = [
			[
				'title' => 'The title',
				'uri' => '/link/to/track/1',
				'slug' => 'the-title',
				'id' => 1,
				'ordinal' => 4,
				'bitrate' => 123,
				'length' => 123,
				'artist' => ['id' => 3, 'uri' => '/link/to/artist/3'],
				'album' => ['id' => 1, 'uri' => '/link/to/album/1'],
				'files' => [
					'audio/mp3' => '/link/to/file/111'
				]
			],
			[
				'title' => 'The second title',
				'uri' => '/link/to/track/2',
				'slug' => 'the-second-title',
				'id' => 2,
				'ordinal' => 5,
				'bitrate' => 123,
				'length' => 103,
				'artist' => ['id' => 3, 'uri' => '/link/to/artist/3'],
				'album' => ['id' => 3, 'uri' => '/link/to/album/3'],
				'files' => [
					'audio/mp3' => '/link/to/file/222'
				]
			]
		];

		$response = $this->controller->tracks($artistId, null /*album*/, false /*fulltree*/);

		$this->assertEquals($result, $response->getData());
		$this->assertTrue($response instanceof JSONResponse);
	}

	public function testTracksByAlbum() {
		$track1 = new Track();
		$track1->setId(1);
		$track1->setTitle('The title');
		$track1->setArtistId(3);
		$track1->setAlbumId(1);
		$track1->setNumber(4);
		$track1->setLength(123);
		$track1->setFileId(13);
		$track1->setMimetype('audio/mp3');
		$track1->setBitrate(123);
		$track2 = new Track();
		$track2->setId(2);
		$track2->setTitle('The second title');
		$track2->setArtistId(2);
		$track2->setAlbumId(1);
		$track2->setNumber(5);
		$track2->setLength(103);
		$track2->setFileId(55);
		$track2->setMimetype('audio/mp3');
		$track2->setBitrate(123);

		$albumId = 1;

		$this->trackBusinessLayer->expects($this->once())
			->method('findAllByAlbum')
			->with($this->equalTo($albumId), $this->equalTo($this->userId))
			->will($this->returnValue([$track1, $track2]));

		$result = [
			[
				'title' => 'The title',
				'uri' => '/link/to/track/1',
				'slug' => 'the-title',
				'id' => 1,
				'ordinal' => 4,
				'bitrate' => 123,
				'length' => 123,
				'artist' => ['id' => 3, 'uri' => '/link/to/artist/3'],
				'album' => ['id' => 1, 'uri' => '/link/to/album/1'],
				'files' => [
					'audio/mp3' => '/link/to/file/13'
				]
			],
			[
				'title' => 'The second title',
				'uri' => '/link/to/track/2',
				'slug' => 'the-second-title',
				'id' => 2,
				'ordinal' => 5,
				'bitrate' => 123,
				'length' => 103,
				'artist' => ['id' => 2, 'uri' => '/link/to/artist/2'],
				'album' => ['id' => 1, 'uri' => '/link/to/album/1'],
				'files' => [
					'audio/mp3' => '/link/to/file/55'
				]
			]
		];

		$response = $this->controller->tracks(null /*artist*/, $albumId, false /*fulltree*/);

		$this->assertEquals($result, $response->getData());
		$this->assertTrue($response instanceof JSONResponse);
	}
}
