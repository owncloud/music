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

use \OCA\Music\Core\API;

/**
 * @method string getName()
 * @method setName(string $name)
 * @method string getImage()
 * @method setImage(string $image)
 * @method string getUserId()
 * @method setUserId(string $userId)
 * @method int getAlbumCount()
 * @method setAlbumCount(int $albumCount)
 * @method int getTrackCount()
 * @method setTrackCount(int $trackCount)
 */
class Artist extends Entity {

	public $name;
	public $image; // URL
	public $userId;

	// the following attributes aren't filled automatically
	public $albumCount;
	public $trackCount;

	public function getUri(IURLGenerator $urlGenerator) {
		return $urlGenerator->linkToRoute(
			'music.api.artist',
			array('artistIdOrSlug' => $this->id)
		);
	}

	public function getNameString($l10n) {
		$name = $this->getName();
		if ($name === null) {
			$name = $l10n->t('Unknown artist')->__toString();
		}
		return $name;
	}

	public function toCollection($l10n) {
		return array(
			'id' => $this->getId(),
			'name' => $this->getNameString($l10n)
		);
	}

	public function toAPI(IURLGenerator $urlGenerator, $l10n) {
		return array(
			'id' => $this->getId(),
			'name' => $this->getNameString($l10n),
			'image' => $this->getImage(),
			'slug' => $this->getId() . '-' . $this->slugify('name'),
			'uri' => $this->getUri($urlGenerator)
		);
	}
}
