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

use \OCP\IURLGenerator;

use \OCA\Music\AppFramework\Db\Entity;

/**
 * @method string getTitle()
 * @method setTitle(string $title)
 * @method int getNumber()
 * @method setNumber(int $number)
 * @method int getArtistId()
 * @method setArtistId(int $artistId)
 * @method Artist getArtist()
 * @method setArtist(Artist $artist)
 * @method int getAlbumId()
 * @method setAlbumId(int $albumId)
 * @method Album getAlbum()
 * @method setAlbum(Album $album)
 * @method int getLength()
 * @method setLength(int $length)
 * @method int getFileId()
 * @method setFileId(int $fileId)
 * @method int getBitrate()
 * @method setBitrate(int $bitrate)
 * @method string getMimetype()
 * @method setMimetype(string $mimetype)
 * @method string getUserId()
 * @method setUserId(string $userId)
 */
class Track extends Entity {

	public $title;
	public $number;
	public $artistId;
	public $artist;
	public $albumId;
	public $album;
	public $length;
	public $fileId;
	public $bitrate;
	public $uri;
	public $mimetype;
	public $userId;

	public function __construct(){
		$this->addType('number', 'int');
		$this->addType('artistId', 'int');
		$this->addType('albumId', 'int');
		$this->addType('length', 'int');
		$this->addType('bitrate', 'int');
		$this->addType('fileId', 'int');
	}

	public function getUri(IURLGenerator $urlGenerator) {
		return $urlGenerator->linkToRoute(
			'music.api.track',
			array('trackIdOrSlug' => $this->id)
		);
	}

	public function getArtistWithUri(IURLGenerator $urlGenerator) {
		return array(
			'id' => $this->artistId,
			'uri' => $urlGenerator->linkToRoute(
				'music.api.artist',
				array('artistIdOrSlug' => $this->artistId)
			)
		);
	}

	public function getAlbumWithUri(IURLGenerator $urlGenerator) {
		return array(
			'id' => $this->albumId,
			'uri' => $urlGenerator->linkToRoute(
				'music.api.album',
				array('albumIdOrSlug' => $this->albumId)
			)
		);
	}

	public function toCollection(IURLGenerator $urlGenerator, $userFolder) {
		$nodes = $userFolder->getById($this->getFileId());
		if(count($nodes) == 0 ) {
			throw new \OCP\Files\NotFoundException();
		}

		// get the first valid node
		$node = $nodes[0];
		$path = $node->getPath();

		$relativePath = $userFolder->getRelativePath($path);

		return array(
			'title' => $this->getTitle(),
			'number' => $this->getNumber(),
			'artistId' => $this->getArtistId(),
			'albumId' => $this->getAlbumId(),
			'files' => array($this->getMimetype() => $urlGenerator->getAbsoluteUrl('remote.php/webdav' . $relativePath)),
			'id' => $this->getId(),
		);
	}

	public function toAPI(IURLGenerator $urlGenerator) {
		return array(
			'title' => $this->getTitle(),
			'number' => $this->getNumber(),
			'artist' => $this->getArtistWithUri($urlGenerator),
			'album' => $this->getAlbumWithUri($urlGenerator),
			'length' => $this->getLength(),
			'files' => array($this->getMimetype() => $urlGenerator->linkToRoute(
				'music.api.download',
				array('fileId' => $this->getFileId())
			)),
			'bitrate' => $this->getBitrate(),
			'id' => $this->getId(),
			'slug' => $this->getId() . '-' . $this->slugify('title'),
			'uri' => $this->getUri($urlGenerator)
		);
	}

}
