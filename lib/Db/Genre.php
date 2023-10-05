<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2020 - 2023
 */

namespace OCA\Music\Db;

use OCP\IL10N;

/**
 * @method string getName()
 * @method void setName(string $name)
 * @method string getLowerName()
 * @method void setLowerName(string $lowerName)
 *
 * @method int getTrackCount()
 * @method int getAlbumCount()
 * @method int getArtistCount()
 */
class Genre extends Entity {
	public $name;
	public $lowerName;
	// not from the music_genres table but still part of the standard content of this entity
	public $trackCount;
	public $albumCount;
	public $artistCount;

	// not part of the standard content, injected separately when needed
	public $trackIds;

	public function __construct() {
		$this->addType('trackCount', 'int');
		$this->addType('albumCount', 'int');
		$this->addType('artistCount', 'int');
	}

	/**
	 * @return ?int[]
	 */
	public function getTrackIds() : ?array {
		return $this->trackIds;
	}

	/**
	 * @param int[] $trackIds
	 */
	public function setTrackIds(array $trackIds) : void {
		$this->trackIds = $trackIds;
	}

	public function getNameString(IL10N $l10n) : string {
		return $this->getName() ?: self::unknownNameString($l10n);
	}

	public function toApi() : array {
		return  [
			'id' => $this->getId(),
			'name' => $this->getName(),
			'trackIds' => $this->trackIds ? \array_map('\intval', $this->trackIds) : []
		];
	}

	public function toAmpacheApi(IL10N $l10n) : array {
		return [
			'id' => (string)$this->getId(),
			'name' => $this->getNameString($l10n),
			'albums' => $this->getAlbumCount(),
			'artists' => $this->getArtistCount(),
			'songs' => $this->getTrackCount(),
			'videos' => 0,
			'playlists' => 0,
			'live_streams' => 0
		];
	}

	public static function unknownNameString(IL10N $l10n) : string {
		return (string) $l10n->t('(Unknown genre)');
	}
}
