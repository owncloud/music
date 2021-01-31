<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2020, 2021
 */

namespace OCA\Music\Db;

use \OCP\IL10N;
use \OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getName()
 * @method void setName(string $name)
 * @method string getLowerName()
 * @method void setLowerName(string $lowerName)
 * @method string getCreated()
 * @method setCreated(string $timestamp)
 * @method string getUpdated()
 * @method setUpdated(string $timestamp)
 * @method int getTrackCount()
 * @method void setTrackCount(int $count)
 * @method int getAlbumCount()
 * @method void setAlbumCount(int $count)
 * @method int getArtistCount()
 * @method void setArtistCount(int $count)
 * @method array getTrackIds()
 * @method void setTrackIds(array $trackIds)
 */
class Genre extends Entity {
	public $userId;
	public $name;
	public $lowerName;
	public $created;
	public $updated;
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

	public function getNameString(IL10N $l10n) {
		return $this->getName() ?: self::unknownNameString($l10n);
	}

	public function toApi() {
		return  [
			'id' => $this->getId(),
			'name' => $this->getName(),
			'trackIds' => $this->trackIds ? \array_map('\intval', $this->trackIds) : []
		];
	}

	public static function unknownNameString(IL10N $l10n) {
		return (string) $l10n->t('(Unknown genre)');
	}
}
