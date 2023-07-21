<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Gavin E <no.emai@address.for.me>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Gavin E 2020
 * @copyright Pauli Järvinen 2020 - 2023
 */

namespace OCA\Music\Db;

use OCA\Music\Utility\Util;

/**
 * @method int getType()
 * @method void setType(int $type)
 * @method int getEntryId()
 * @method void setEntryId(int $entryId)
 * @method int getPosition()
 * @method void setPosition(int $position)
 * @method ?string getComment()
 * @method void setComment(?string $comment)
 */
class Bookmark extends Entity {
	public $type;
	public $entryId;
	public $position;
	public $comment;

	public const TYPE_TRACK = 1;
	public const TYPE_PODCAST_EPISODE = 2;

	public function __construct() {
		$this->addType('type', 'int');
		$this->addType('entryId', 'int');
		$this->addType('position', 'int');
	}

	public function toSubsonicApi() : array {
		return [
			'position' => $this->getPosition(),
			'username' => $this->getUserId(),
			'comment' => $this->getComment() ?: '',
			'created' => Util::formatZuluDateTime($this->getCreated()),
			'changed' => Util::formatZuluDateTime($this->getUpdated())
		];
	}

	public function toAmpacheApi() : array {
		return [
			'id' => (string)$this->getId(),
			'owner' => $this->getUserId(),
			'object_type' => ($this->getType() == self::TYPE_TRACK) ? 'song' : 'podcast_episode',
			'object_id' => (string)$this->getEntryId(),
			'position' => (int)($this->getPosition() / 1000), // millisecods to seconds
			'client' => $this->getComment(),
			'creation_date' => Util::formatDateTimeUtcOffset($this->getCreated()),
			'update_date' => Util::formatDateTimeUtcOffset($this->getUpdated())
		];
	}
}
