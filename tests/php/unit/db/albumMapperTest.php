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

class AlbumMapperTest extends \OCA\Music\AppFramework\Utility\MapperTestUtility {

	private $mapper;
	private $albums;

	private $userId = 'john';
	private $id = 5;
	private $rows;

	public function setUp()
	{
		$this->beforeEach();

		$this->mapper = new AlbumMapper($this->api);

		// create mock items
		$album1 = new Album();
		$album1->setName('Test name');
		$album1->setYear(2013);
		$album1->setCoverFileId(3);
		$album1->resetUpdatedFields();
		$album2 = new Album();
		$album2->setName('Test name2');
		$album2->setYear(2012);
		$album2->setCoverFileId(4);
		$album2->resetUpdatedFields();
		$albumNull = new Album();
		$albumNull->setName(null);
		$albumNull->resetUpdatedFields();

		$this->albums = array(
			$album1,
			$album2,
			$albumNull
		);

		$this->rows = array(
			array('id' => $this->albums[0]->getId(), 'name' => 'Test name', 'year' => 2013, 'cover_file_id' => 3),
			array('id' => $this->albums[1]->getId(), 'name' => 'Test name2', 'year' => 2012, 'cover_file_id' => 4),
			array('id' => $this->albums[2]->getId(), 'name' => null),
		);

	}


	private function makeSelectQuery($condition=null){
		return 'SELECT `album`.`name`, `album`.`year`, `album`.`id`, '.
			'`album`.`cover_file_id` '.
			'FROM `*PREFIX*music_albums` `album` '.
			'WHERE `album`.`user_id` = ? ' . $condition;
	}

	public function testFind(){
		$sql = $this->makeSelectQuery('AND `album`.`id` = ?');
		$this->setMapperResult($sql, array($this->userId, $this->id), array($this->rows[0]));
		$result = $this->mapper->find($this->id, $this->userId);
		$this->assertEquals($this->albums[0], $result);
	}

	public function testFindAll(){
		$sql = $this->makeSelectQuery();
		$this->setMapperResult($sql, array($this->userId), $this->rows);
		$result = $this->mapper->findAll($this->userId);
		$this->assertEquals($this->albums, $result);
	}

	public function testGetAlbumArtistsByAlbumId(){
		$sql = 'SELECT DISTINCT * FROM `*PREFIX*music_album_artists` `artists`'.
			' WHERE `artists`.`album_id` IN (?,?,?)';
		$albumIds = array(1,2,3);
		$rows = array(
			array('album_id' => 1, 'artist_id' => 2),
			array('album_id' => 1, 'artist_id' => 5),
			array('album_id' => 2, 'artist_id' => 1),
			array('album_id' => 2, 'artist_id' => 3),
			array('album_id' => 2, 'artist_id' => 5),
			array('album_id' => 3, 'artist_id' => 4)
		);
		$albumArtists = array(
			1 => array(2,5),
			2 => array(1,3,5),
			3 => array(4)
		);
		$this->setMapperResult($sql, $albumIds, $rows);
		$result = $this->mapper->getAlbumArtistsByAlbumId($albumIds);
		$this->assertEquals($albumArtists, $result);
	}

	public function testFindAllByArtist(){
		$sql = 'SELECT `album`.`name`, `album`.`year`, `album`.`id`, '.
			'`album`.`cover_file_id` '.
			'FROM `*PREFIX*music_albums` `album` '.
			'JOIN `*PREFIX*music_album_artists` `artists` '.
			'ON `album`.`id` = `artists`.`album_id` '.
			'WHERE `album`.`user_id` = ? AND `artists`.`artist_id` = ? ';
		$artistId = 3;
		$this->setMapperResult($sql, array($this->userId, $artistId), $this->rows);
		$result = $this->mapper->findAllByArtist($artistId, $this->userId);
		$this->assertEquals($this->albums, $result);
	}

	public function testFindByNameAndYear(){
		$sql = 'SELECT `album`.`name`, `album`.`year`, `album`.`id`, '.
			'`album`.`cover_file_id` '.
			'FROM `*PREFIX*music_albums` `album` '.
			'WHERE `album`.`user_id` = ? AND `album`.`name` = ? AND `album`.`year` = ?';
		$albumName = 'test';
		$albumYear = 2005;
		$this->setMapperResult($sql, array($this->userId, $albumName, $albumYear), array($this->rows[0]));
		$result = $this->mapper->findByNameAndYear($albumName, $albumYear, $this->userId);
		$this->assertEquals($this->albums[0], $result);
	}

	public function testFindByNameAndYearYearIsNull(){
		$sql = 'SELECT `album`.`name`, `album`.`year`, `album`.`id`, '.
			'`album`.`cover_file_id` '.
			'FROM `*PREFIX*music_albums` `album` '.
			'WHERE `album`.`user_id` = ? AND `album`.`name` = ? AND `album`.`year` IS NULL';
		$albumName = 'test';
		$albumYear = null;
		$this->setMapperResult($sql, array($this->userId, $albumName), array($this->rows[0]));
		$result = $this->mapper->findByNameAndYear($albumName, $albumYear, $this->userId);
		$this->assertEquals($this->albums[0], $result);
	}

	public function testFindByNameAndYearNameIsNull(){
		$sql = 'SELECT `album`.`name`, `album`.`year`, `album`.`id`, '.
			'`album`.`cover_file_id` '.
			'FROM `*PREFIX*music_albums` `album` '.
			'WHERE `album`.`user_id` = ? AND `album`.`name` IS NULL AND `album`.`year` = ?';
		$albumName = null;
		$albumYear = 2014;
		$this->setMapperResult($sql, array($this->userId, $albumYear), array($this->rows[0]));
		$result = $this->mapper->findByNameAndYear($albumName, $albumYear, $this->userId);
		$this->assertEquals($this->albums[0], $result);
	}

	public function testFindByNameAndYearBothNull(){
		$sql = 'SELECT `album`.`name`, `album`.`year`, `album`.`id`, '.
			'`album`.`cover_file_id` '.
			'FROM `*PREFIX*music_albums` `album` '.
			'WHERE `album`.`user_id` = ? AND `album`.`name` IS NULL AND `album`.`year` IS NULL';
		$albumName = null;
		$albumYear = null;
		$this->setMapperResult($sql, array($this->userId), array($this->rows[0]));
		$result = $this->mapper->findByNameAndYear($albumName, $albumYear, $this->userId);
		$this->assertEquals($this->albums[0], $result);
	}

	public function testAddAlbumArtistRelationIfNotExistNoAdd(){
		$sql = 'SELECT 1 FROM `*PREFIX*music_album_artists` `relation` '.
			'WHERE `relation`.`album_id` = ? AND `relation`.`artist_id` = ?';
		$albumId = 1;
		$artistId = 2;
		$this->setMapperResult($sql, array($albumId, $artistId), array(array('select' => '1')));
		$this->mapper->addAlbumArtistRelationIfNotExist($albumId, $artistId);
	}

	public function testAddAlbumArtistRelationIfNotExistAdd(){
		$albumId = 1;
		$artistId = 2;
		$sql = 'SELECT 1 FROM `*PREFIX*music_album_artists` `relation` '.
			'WHERE `relation`.`album_id` = ? AND `relation`.`artist_id` = ?';
		$arguments = array($albumId, $artistId);
		$sql2 = 'INSERT INTO `*PREFIX*music_album_artists` (`album_id`, `artist_id`) '.
			'VALUES (?, ?)';

		$pdoResult = $this->getMock('Result',
			array('fetchRow'));
		$pdoResult->expects($this->any())
			->method('fetchRow');

		$query = $this->getMock('Query',
			array('execute'));
		$query->expects($this->at(0))
			->method('execute')
			->with($this->equalTo($arguments))
			->will($this->returnValue($pdoResult));
		$this->api->expects($this->at(0))
			->method('prepareQuery')
			->with($this->equalTo($sql))
			->will(($this->returnValue($query)));

		$query->expects($this->at(1))
			->method('execute')
			->with($this->equalTo($arguments))
			->will($this->returnValue($pdoResult));
		$this->api->expects($this->at(1))
			->method('prepareQuery')
			->with($this->equalTo($sql2))
			->will(($this->returnValue($query)));

		$this->mapper->addAlbumArtistRelationIfNotExist($albumId, $artistId);
	}

	public function testDeleteByIdNone(){
		$albumIds = array();

		$this->api->expects($this->never())
			->method('prepareQuery');

		$this->mapper->deleteById($albumIds);
	}

	public function testDeleteById(){
		$albumIds = array(1, 2);

		$sql = 'DELETE FROM `*PREFIX*music_album_artists` WHERE `album_id` IN (?,?)';
		$arguments = $albumIds;
		$sql2 = 'DELETE FROM `*PREFIX*music_albums` WHERE `id` IN (?,?)';

		$pdoResult = $this->getMock('Result',
			array('fetchRow'));
		$pdoResult->expects($this->any())
			->method('fetchRow');

		$query = $this->getMock('Query',
			array('execute'));
		$query->expects($this->at(0))
			->method('execute')
			->with($this->equalTo($arguments))
			->will($this->returnValue($pdoResult));
		$this->api->expects($this->at(0))
			->method('prepareQuery')
			->with($this->equalTo($sql))
			->will(($this->returnValue($query)));

		$query->expects($this->at(1))
			->method('execute')
			->with($this->equalTo($arguments))
			->will($this->returnValue($pdoResult));
		$this->api->expects($this->at(1))
			->method('prepareQuery')
			->with($this->equalTo($sql2))
			->will(($this->returnValue($query)));

		$this->mapper->deleteById($albumIds);
	}

	public function testCount(){
		$sql = 'SELECT COUNT(*) FROM `*PREFIX*music_albums` WHERE `user_id` = ?';
		$this->setMapperResult($sql, array($this->userId), array(array('COUNT(*)' => 4)));
		$result = $this->mapper->count($this->userId);
		$this->assertEquals(4, $result);
	}

	public function testCountByArtist(){
		$artistId = 2;
		$sql = 'SELECT COUNT(*) '.
			'FROM `*PREFIX*music_albums` `album` '.
			'JOIN `*PREFIX*music_album_artists` `artists` '.
			'ON `album`.`id` = `artists`.`album_id` '.
			'WHERE `album`.`user_id` = ? AND `artists`.`artist_id` = ? ';
		$this->setMapperResult($sql, array($this->userId, $artistId), array(array('COUNT(*)' => 4)));
		$result = $this->mapper->countByArtist($artistId, $this->userId);
		$this->assertEquals(4, $result);
	}

	public function testRemoveCover(){
		$fileId = 7;
		$sql = 'UPDATE `*PREFIX*music_albums`
				SET `cover_file_id` = NULL
				WHERE `cover_file_id` = ?';
		$this->setMapperResult($sql, array($fileId), array());
		$this->mapper->removeCover($fileId);
	}

	public function testUpdateCover(){
		$coverFileId = 9;
		$parentFileId = 7;
		$sql = 'UPDATE `*PREFIX*music_albums`
				SET `cover_file_id` = ?
				WHERE `cover_file_id` IS NULL AND `id` IN (
					SELECT DISTINCT `tracks`.`album_id`
					FROM `*PREFIX*music_tracks` `tracks`
					JOIN `*PREFIX*filecache` `files` ON `tracks`.`file_id` = `files`.`fileid`
					WHERE `files`.`parent` = ?
				)';
		$this->setMapperResult($sql, array($coverFileId, $parentFileId), array());
		$this->mapper->updateCover($coverFileId, $parentFileId);
	}

	public function testFindAlbumCover(){
		$albumId = 9;
		$parentFileId = 3;
		$imageFileId = 7;
		$sql = 'SELECT `fileid`, `name`
					FROM `*PREFIX*filecache`
					JOIN `*PREFIX*mimetypes` ON `*PREFIX*mimetypes`.`id` = `*PREFIX*filecache`.`mimetype`
					WHERE `parent` = ? AND `*PREFIX*mimetypes`.`mimetype` LIKE \'image%\'';
		$expected = array(
			array('fileid' => 5, 'name' => '1123213.jpg'),
			array('fileid' => $imageFileId, 'name' => 'coverasd.jpg'),
			array('fileid' => 4, 'name' => 'albumart.jpg'),
			array('fileid' => 6, 'name' => 'folder.jpg'),
			array('fileid' => 8, 'name' => 'front.jpg'),
		);
		$this->setMapperResult($sql, array($parentFileId), $expected, null, null, 0);

		$sql = 'UPDATE `*PREFIX*music_albums`
				SET `cover_file_id` = ? WHERE `id` = ?';
		$this->setMapperResult($sql, array($imageFileId, $albumId), array(), null, null, 1);
		$this->mapper->findAlbumCover($albumId, $parentFileId);
	}

	public function testGetAlbumsWithoutCover() {
		$sql = 'SELECT DISTINCT `albums`.`id`, `files`.`parent`
				FROM `*PREFIX*music_albums` `albums`
				JOIN `*PREFIX*music_tracks` `tracks` ON `albums`.`id` = `tracks`.`album_id`
				JOIN `*PREFIX*filecache` `files` ON `tracks`.`file_id` = `files`.`fileid`
				WHERE `albums`.`cover_file_id` IS NULL';
		$expectedRows = array(
			array('id' => 1, 'parent' => 6),
			array('id' => 37, 'parent' => 19),
		);
		$this->setMapperResult($sql, array(), $expectedRows);
		$result = $this->mapper->getAlbumsWithoutCover();
		$expectedResult =array(
			array('albumId' => 1, 'parentFolderId' => 6),
			array('albumId' => 37, 'parentFolderId' => 19),
		);
		$this->assertEquals($expectedResult, $result);
	}

	public function testFindAllByName(){
		$sql = $this->makeSelectQuery('AND `album`.`name` = ? ');
		$this->setMapperResult($sql, array($this->userId, 123), array($this->rows[0]));
		$result = $this->mapper->findAllByName(123, $this->userId);
		$this->assertEquals(array($this->albums[0]), $result);
	}

	public function testFindAllByNameFuzzy(){
		$sql = $this->makeSelectQuery('AND LOWER(`album`.`name`) LIKE LOWER(?) ');
		$this->setMapperResult($sql, array($this->userId, '%test123test%'), array($this->rows[0]));
		$result = $this->mapper->findAllByName('test123test', $this->userId, true);
		$this->assertEquals(array($this->albums[0]), $result);
	}
}
