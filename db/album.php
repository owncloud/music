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

use \OCP\IURLGenerator;

use \OCA\Music\AppFramework\Db\Entity;

use \OCA\Music\Core\API;

/**
 * @method int getId()
 * @method setId(int $id)
 * @method string getName()
 * @method setName(string $name)
 * @method int getYear()
 * @method setYear(int $year)
 * @method int getCoverFileId()
 * @method setCoverFileId(int $coverFileId)
 * @method array getArtistIds()
 * @method setArtistIds(array $artistIds)
 * @method string getUserId()
 * @method setUserId(string $userId)
 * @method int getTrackCount()
 * @method setTrackCount(int $trackCount)
 * @method string getArtist()
 * @method setArtist(string $artist)
 */
class Album extends Entity {

	public $name;
	public $year;
	public $coverFileId;
	public $artistIds;
	public $artists;
	public $userId;

	// the following attributes aren't filled automatically
	public $trackCount;
	public $artist; // just used for Ampache as this supports just one artist

	public function __construct(){
		$this->addType('year', 'int');
		$this->addType('coverFileId', 'int');
	}

	/**
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
	 * @param \OCP\IURLGenerator $urlGenerator
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
	 * @param object $l10n
	 * @return string
	 */
	public function getNameString($l10n) {
		$name = $this->getName();
		if ($name === null) {
			$name = $l10n->t('Unknown album')->__toString();
		}
		return $name;
	}

	public function toCollection(IURLGenerator $urlGenerator, $l10n) {
		$coverUrl = null;
		if($this->getCoverFileId()) {
			$coverUrl = $urlGenerator->linkToRoute('music.api.cover',
					array('albumIdOrSlug' => $this->getId()));
		}
		return array(
				'name' => $this->getNameString($l10n),
				'year' => $this->getYear(),
				'cover' => $coverUrl,
				'id' => $this->getId(),
		);
	}

	public function toAPI(IURLGenerator $urlGenerator, $l10n) {
		$coverUrl = null;
		if($this->getCoverFileId() > 0) {
			$coverUrl = $urlGenerator->linkToRoute('music.api.cover',
					array('albumIdOrSlug' => $this->getId()));
		}
		return array(
			'name' => $this->getNameString($l10n),
			'year' => $this->getYear(),
			'cover' => $coverUrl,
			'uri' => $this->getUri($urlGenerator),
			'slug' => $this->getid() . '-' .$this->slugify('name'),
			'id' => $this->getId(),
			'artists' => $this->getArtists($urlGenerator)
		);
	}
}
