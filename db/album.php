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

use \OCP\AppFramework\Db\Entity;

/**
 * @method string getName()
 * @method setName(string $name)
 * @method string getMbid()
 * @method setMbid(string $mbid)
 * @method int getYear()
 * @method setYear(int $year)
 * @method int getDisk()
 * @method setDisk(int $discnumber)
 * @method string getMbidGroup()
 * @method setMbidGroup(string $mbidGroup)
 * @method int getCoverFileId()
 * @method setCoverFileId(int $coverFileId)
 * @method array getArtistIds()
 * @method setArtistIds(array $artistIds)
 * @method string getUserId()
 * @method setUserId(string $userId)
 * @method int getAlbumArtistId()
 * @method setAlbumArtistId(int $albumArtistId)
 * @method Artist getAlbumArtist()
 * @method setArtist(Artist $albumArtist)
 * @method string getHash()
 * @method setHash(string $hash)
 * @method int getTrackCount()
 * @method setTrackCount(int $trackCount)
 */
class Album extends Entity {

	public $name;
	public $mbid;
	public $year;
	public $disk;
	public $mbidGroup;
	public $coverFileId;
	public $artistIds;
	public $userId;
	public $albumArtistId;
	public $albumArtist;
	public $hash;

	// the following attributes aren't filled automatically
	public $trackCount;

	public function __construct(){
		$this->addType('year', 'int');
		$this->addType('disk', 'int');
		$this->addType('coverFileId', 'int');
		$this->addType('albumArtistId', 'int');
	}

	/**
	 * Generates URL to album
	 * @param \OCP\IURLGenerator $urlGenerator
	 * @return string the url
	 */
	public function getUri(IURLGenerator $urlGenerator) {
		return $urlGenerator->linkToRoute(
			'music.api.album',
			array('albumIdOrSlug' => $this->id)
		);
	}

	/**
	 * Returns an array of all artists - each with ID and URL for that artist
	 * @param \OCP\IURLGenerator $urlGenerator URLGenerator
	 * @return array
	 */
	public function getArtists(IURLGenerator $urlGenerator) {
		$artists = array();
		foreach($this->artistIds as $artistId) {
			$artists[] = array(
				'id' => $artistId,
				'uri' => $urlGenerator->linkToRoute(
					'music.api.artist',
					array('artistIdOrSlug' => $artistId)
				)
			);
		}
		return $artists;
	}

	/**
	 * Returns the name of the album - if empty it returns the translated
	 * version of "Unknown album"
	 * @param object $l10n
	 * @return string
	 */
	public function getNameString($l10n) {
		$name = $this->getName();
		if ($name === null) {
			$name = $l10n->t('Unknown album');
			if(!is_string($name)) {
				/** @var \OC_L10N_String $name */
				$name = $name->__toString();
			}
		}
		return $name;
	}

	/**
	 * Creates object used for collection API (array with name, year, cover URL and ID)
	 * @param  IURLGenerator $urlGenerator URLGenerator
	 * @param  object $l10n L10n handler
	 * @return array collection API object
	 */
	public function toCollection(IURLGenerator $urlGenerator, $l10n) {
		$coverUrl = null;
		if ($this->getCoverFileId() > 0) {
			$coverUrl = $urlGenerator->linkToRoute('music.api.cover',
				array('albumIdOrSlug' => $this->getId()));
		}

		return array(
			'name' => $this->getNameString($l10n),
			'year' => $this->getYear(),
			'disk' => $this->getDisk(),
			'cover' => $coverUrl,
			'id' => $this->getId(),
		);
	}

	/**
	 * Creates object used by the shiva API (array with name, year, cover URL, ID, slug, URI and artists Array)
	 * @param  IURLGenerator $urlGenerator URLGenerator
	 * @param  object $l10n L10n handler
	 * @return array shiva API object
	 */
	public function toAPI(IURLGenerator $urlGenerator, $l10n) {
		$collection = $this->toCollection($urlGenerator, $l10n);
		$collection["uri"] = $this->getUri($urlGenerator);
		$collection["slug"] = $this->getid() . '-' .$this->slugify('name');
		$collection["albumArtistId"] = $this->getAlbumArtistId();
		$collection["artists"] = $this->getArtists($urlGenerator);

		return $collection;
	}
}
