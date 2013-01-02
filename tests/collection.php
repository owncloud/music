<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Media;

// get absolute path of file directory
$path = realpath( dirname( __FILE__ ) ) . '/';
require_once($path . "../lib/collection.php");

class Collection extends \PHPUnit_Framework_TestCase {
	/**
	 * @var \OCA\Media\Collection $collection
	 */
	private $collection;

	private $prefix;

	public function setUp() {
		$this->collection = new \OCA\Media\Collection(uniqid());
		$this->prefix = uniqid();
	}

	public function tearDown() {
		$this->collection->clear();
	}

	public function testBasic() {
		$this->assertEquals(0, $this->collection->getArtistCount());
		$this->assertEquals(0, $this->collection->getAlbumCount());
		$this->assertEquals(0, $this->collection->getSongCount());
		$this->assertEquals(array(), $this->collection->getArtists());
		$this->assertEquals(array(), $this->collection->getAlbums());
		$this->assertEquals(array(), $this->collection->getsongs());
		$this->assertEquals(0, $this->collection->getArtistId($this->prefix . 'foo'));

		$artistId = $this->collection->addArtist($this->prefix . 'foo');
		$this->assertEquals($artistId, $this->collection->getArtistId($this->prefix . 'foo'));
		$this->assertEquals($this->prefix . 'foo', $this->collection->getArtistName($artistId));
		$this->assertEquals(0, $this->collection->getArtistCount()); //no songs for our newly added artist, so it doesn't count

		$albumId = $this->collection->addAlbum($this->prefix . 'bar', $artistId);
		$this->assertEquals($albumId, $this->collection->getAlbumId($this->prefix . 'bar', $artistId));
		$this->assertEquals($this->prefix . 'bar', $this->collection->getAlbumName($albumId));
		$this->assertEquals(0, $this->collection->getAlbumCount());

		$songId = $this->collection->addSong('foobar1', '/dummy/path/1', $artistId, $albumId, 100, 1, 1000);
		$this->assertEquals($songId, $this->collection->getSongId('foobar1', $artistId, $albumId));
		$this->assertEquals($songId, $this->collection->getSongByPath('/dummy/path/1'));
		$songId = $this->collection->addSong('foobar2', '/dummy/path/2', $artistId, $albumId, 100, 1, 1000);
		$this->assertEquals($songId, $this->collection->getSongId('foobar2', $artistId, $albumId));
		$songId = $this->collection->addSong('foobar3', '/dummy/3', $artistId, $albumId, 100, 1, 1000);
		$this->assertEquals($songId, $this->collection->getSongId('foobar3', $artistId, $albumId));

		//after we added a song, the artists and album count
		$this->assertEquals(1, $this->collection->getArtistCount());
		$this->assertEquals(1, $this->collection->getAlbumCount());
		$this->assertEquals(3, $this->collection->getSongCount());

		$this->assertEquals(2, $this->collection->getSongCountByPath('/dummy/path'));
		$this->assertEquals(3, $this->collection->getSongCountByPath('/dummy'));
		$this->collection->moveSong('/dummy/path/2', '/dummy/2');
		$this->assertEquals(1, $this->collection->getSongCountByPath('/dummy/path'));
		$this->assertEquals(3, $this->collection->getSongCountByPath('/dummy'));

		$this->collection->deleteSongByPath('/dummy/path');
		$this->assertEquals(1, $this->collection->getArtistCount());
		$this->assertEquals(1, $this->collection->getAlbumCount());
		$this->assertEquals(2, $this->collection->getSongCount());

		$this->collection->deleteSongByPath('/dummy');
		$this->assertEquals(0, $this->collection->getArtistCount());
		$this->assertEquals(0, $this->collection->getAlbumCount());
		$this->assertEquals(0, $this->collection->getSongCount());
	}
}
