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

use \OCA\Music\AppFramework\Db\Entity;
use \OCA\Music\Core\API;


class Track extends Entity {

	public $title;
	public $number;
	public $artistId;
	public $artist;
	public $albumId;
	public $album;
	public $length;
	public $fileSize;
	public $fileId;
	public $filePath;
	public $bitrate;
	public $uri;
	public $mimetype;
	public $userId;

	public function __construct(){
		$this->addType('number', 'int');
		$this->addType('artistId', 'int');
		$this->addType('albumId', 'int');
		$this->addType('fileSize', 'int');
		$this->addType('length', 'int');
		$this->addType('bitrate', 'int');
		$this->addType('fileId', 'int');
	}

	public function getUri(API $api) {
		return $api->linkToRoute(
			'music_track',
			array('trackIdOrSlug' => $this->id)
		);
	}

	public function getArtistWithUri(API $api) {
		return array(
			'id' => $this->artistId,
			'uri' => $api->linkToRoute(
				'music_artist',
				array('artistIdOrSlug' => $this->artistId)
			)
		);
	}

	public function getAlbumWithUri(API $api) {
		return array(
			'id' => $this->albumId,
			'uri' => $api->linkToRoute(
				'music_album',
				array('albumIdOrSlug' => $this->albumId)
			)
		);
	}

	public function toCollection(API $api) {
		return array(
			'title' => $this->getTitle(),
			'number' => $this->getNumber(),
			'artistId' => $this->getArtistId(),
			'albumId' => $this->getAlbumId(),
			'files' => array($this->getMimetype() => $api->linkToRoute(
				'download',
				array('file' => strstr($this->getFilePath(),'/'))
			)),
			'id' => $this->getId(),
		);
	}

	public function toAPI(API $api) {
		return array(
			'title' => $this->getTitle(),
			'number' => $this->getNumber(),
			'artist' => $this->getArtistWithUri($api),
			'album' => $this->getAlbumWithUri($api),
			'length' => $this->getLength(),
			'files' => array($this->getMimetype() => $api->linkToRoute(
				'download',
				array('file' => $api->getView()->getPath($this->getFileId()))
			)),
			'bitrate' => $this->getBitrate(),
			'id' => $this->getId(),
			'slug' => $this->getId() . '-' . $this->slugify('title'),
			'uri' => $this->getUri($api)
		);
	}

}
