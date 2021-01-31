<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2013, 2014
 * @copyright Pauli Järvinen 2016 - 2021
 */

namespace OCA\Music\Db;

use \OCP\IL10N;
use \OCP\IURLGenerator;
use \OCP\AppFramework\Db\Entity;

use \OCA\Music\Utility\Util;

/**
 * @method string getTitle()
 * @method void setTitle(string $title)
 * @method int getNumber()
 * @method void setNumber(int $number)
 * @method int getDisk()
 * @method void setDisk(int $disk)
 * @method int getYear()
 * @method void setYear(int $year)
 * @method int getArtistId()
 * @method void setArtistId(int $artistId)
 * @method string getArtistName()
 * @method void setArtistName(string $artistName)
 * @method int getAlbumId()
 * @method void setAlbumId(int $albumId)
 * @method string getAlbumName()
 * @method void setAlbumName(string $albumName)
 * @method Album getAlbum()
 * @method void setAlbum(Album $album)
 * @method int getLength()
 * @method void setLength(int $length)
 * @method int getFileId()
 * @method void setFileId(int $fileId)
 * @method int getBitrate()
 * @method void setBitrate(int $bitrate)
 * @method string getMimetype()
 * @method void setMimetype(string $mimetype)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getMbid()
 * @method void setMbid(string $mbid)
 * @method string getStarred()
 * @method void setStarred(string $timestamp)
 * @method int getGenreId()
 * @method void setGenreId(int $genreId)
 * @method string getCreated()
 * @method setCreated(string $timestamp)
 * @method string getUpdated()
 * @method setUpdated(string $timestamp)
 * @method string getGenreName()
 * @method void setGenreName(string $genreName)
 * @method string getFilename()
 * @method void setFilename(string $filename)
 * @method int getSize()
 * @method void setSize(int $size)
 * @method int getNumberOnPlaylist()
 * @method void setNumberOnPlaylist(int $number)
 */
class Track extends Entity {
	public $title;
	public $number;
	public $disk;
	public $year;
	public $artistId;
	public $albumId;
	public $length;
	public $fileId;
	public $bitrate;
	public $uri;
	public $mimetype;
	public $userId;
	public $mbid;
	public $starred;
	public $genreId;
	public $created;
	public $updated;
	public $filename;
	public $size;
	// not from the music_tracks table but still part of the standard content of this entity:
	public $albumName;
	public $artistName;
	public $genreName;

	// the rest of the variables are injected separately when needed
	public $album;
	public $numberOnPlaylist;

	public function __construct() {
		$this->addType('number', 'int');
		$this->addType('disk', 'int');
		$this->addType('year', 'int');
		$this->addType('artistId', 'int');
		$this->addType('albumId', 'int');
		$this->addType('length', 'int');
		$this->addType('bitrate', 'int');
		$this->addType('fileId', 'int');
		$this->addType('genreId', 'int');
		$this->addType('size', 'int');
	}

	public function getUri(IURLGenerator $urlGenerator) {
		return $urlGenerator->linkToRoute(
			'music.api.track',
			['trackIdOrSlug' => $this->id]
		);
	}

	public function getArtistWithUri(IURLGenerator $urlGenerator) {
		return [
			'id' => $this->artistId,
			'uri' => $urlGenerator->linkToRoute(
				'music.api.artist',
				['artistIdOrSlug' => $this->artistId]
			)
		];
	}

	public function getAlbumWithUri(IURLGenerator $urlGenerator) {
		return [
			'id' => $this->albumId,
			'uri' => $urlGenerator->linkToRoute(
				'music.api.album',
				['albumIdOrSlug' => $this->albumId]
			)
		];
	}

	public function getArtistNameString(IL10N $l10n) {
		return $this->getArtistName() ?: Artist::unknownNameString($l10n);
	}

	public function getAlbumNameString(IL10N $l10n) {
		return $this->getAlbumName() ?: Album::unknownNameString($l10n);
	}

	public function getGenreNameString(IL10N $l10n) {
		return $this->getGenreName() ?: Genre::unknownNameString($l10n);
	}

	public function toCollection(IL10N $l10n) {
		return [
			'title' => $this->getTitle(),
			'number' => $this->getNumber(),
			'disk' => $this->getDisk(),
			'artistName' => $this->getArtistNameString($l10n),
			'artistId' => $this->getArtistId(),
			'length' => $this->getLength(),
			'files' => [$this->getMimetype() => $this->getFileId()],
			'id' => $this->getId(),
		];
	}

	public function toAPI(IURLGenerator $urlGenerator) {
		return [
			'title' => $this->getTitle(),
			'ordinal' => $this->getAdjustedTrackNumber(),
			'artist' => $this->getArtistWithUri($urlGenerator),
			'album' => $this->getAlbumWithUri($urlGenerator),
			'length' => $this->getLength(),
			'files' => [$this->getMimetype() => $urlGenerator->linkToRoute(
				'music.api.download',
				['fileId' => $this->getFileId()]
			)],
			'bitrate' => $this->getBitrate(),
			'id' => $this->getId(),
			'slug' => $this->getId() . '-' . $this->slugify('title'),
			'uri' => $this->getUri($urlGenerator)
		];
	}

	public function getAdjustedTrackNumber() {
		// Number on playlist overrides the track number if it is set.
		if ($this->numberOnPlaylist !== null) {
			$trackNumber = $this->numberOnPlaylist;
		} else {
			// On single-disk albums, the track number is given as-is.
			// On multi-disk albums, the disk-number is applied to the track number.
			// In case we have no Album reference, the best we can do is to apply the
			// disk number if it is greater than 1. For disk 1, we don't know if this
			// is a multi-disk album or not.
			$numberOfDisks = ($this->album) ? $this->album->getNumberOfDisks() : null;
			$trackNumber = $this->getNumber();

			if ($this->disk > 1 || $numberOfDisks > 1) {
				$trackNumber = $trackNumber ?: 0;
				$trackNumber += (100 * $this->disk);
			}
		}

		return $trackNumber;
	}

	public function getFileExtension() {
		$parts = \explode('.', $this->getFilename());
		return \end($parts);
	}

	public static function compareArtistAndTitle(Track $a, Track $b) {
		$artistResult = Util::stringCaseCompare($a->getArtistName(), $b->getArtistName());

		return $artistResult ?: Util::stringCaseCompare($a->getTitle(), $b->getTitle());
	}
}
