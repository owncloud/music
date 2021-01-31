<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2014
 * @copyright Pauli Järvinen 2017 - 2021
 */

namespace OCA\Music\Db;

use \OCP\AppFramework\Db\Entity;

/**
 * @method string getName()
 * @method setName(string $name)
 * @method string getTrackIds()
 * @method setTrackIds(string $trackIds)
 * @method string getUserId()
 * @method setUserId(string $userId)
 * @method string getCreated()
 * @method setCreated(string $timestamp)
 * @method string getUpdated()
 * @method setUpdated(string $timestamp)
 * @method string getComment()
 * @method setComment(string $comment)
 */
class Playlist extends Entity {
	public $name;
	public $userId;
	public $trackIds;
	public $created;
	public $updated;
	public $comment;

	/**
	 * @return integer
	 */
	public function getTrackCount() {
		return \count($this->getTrackIdsAsArray());
	}

	/**
	 * @return int[]
	 */
	public function getTrackIdsAsArray() {
		if (!$this->trackIds || \strlen($this->trackIds) < 3) {
			// the list is empty if there is nothing between the leading and trailing '|'
			return [];
		} else {
			$encoded = \substr($this->trackIds, 1, -1); // omit leading and trailing '|'
			return \array_map('intval', \explode('|', $encoded));
		}
	}

	/**
	 * @param int[] $trackIds
	 */
	public function setTrackIdsFromArray($trackIds) {
		// encode to format like "|123|45|667|"
		$this->setTrackIds('|' . \implode('|', $trackIds) . '|');
	}

	public function toAPI() {
		return [
			'name' => $this->getName(),
			'trackIds' => $this->getTrackIdsAsArray(),
			'id' => $this->getId(),
			'created' => $this->getCreated(),
			'updated' => $this->getUpdated(),
			'comment' => $this->getComment()
		];
	}
}
