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


class Album extends Entity {

	public $name;
	public $year;
	public $coverFileId;
	public $coverFilePath;
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

	public function getUri(API $api) {
		return $api->linkToRoute(
			'music_album',
			array('albumIdOrSlug' => $this->id)
		);
	}

	public function getArtists(API $api) {
		$artists = array();
		foreach($this->artistIds as $artistId) {
			$artists[] = array(
				'id' => $artistId,
				'uri' => $api->linkToRoute(
					'music_artist',
					array('artistIdOrSlug' => $artistId)
				)
			);
		}
		return $artists;
	}

	public function getNameString(API $api) {
		$name = $this->getName();
		if ($name === null) {
			$name = $api->getTrans()->t('Unknown album')->__toString();
		}
		return $name;
	}

	public function toCollection(API $api) {
		$coverUrl = null;
		if($this->getCoverFilePath()) {
			$coverUrl = $api->linkToRoute('download',
					array('file' => strstr($this->getCoverFilePath(),'/')));
		}
		return array(
				'name' => $this->getNameString($api),
				'year' => $this->getYear(),
				'cover' => $coverUrl,
				'id' => $this->getId(),
		);
	}

	public function toAPI(API $api) {
		$coverUrl = null;
		if($this->getCoverFileId() > 0) {
			$coverUrl = $api->linkToRoute('download',
				array('file' => $api->getView()->getPath($this->getCoverFileId())));
		}
		return array(
			'name' => $this->getNameString($api),
			'year' => $this->getYear(),
			'cover' => $coverUrl,
			'uri' => $this->getUri($api),
			'slug' => $this->getid() . '-' .$this->slugify('name'),
			'id' => $this->getId(),
			'artists' => $this->getArtists($api)
		);
	}
}
