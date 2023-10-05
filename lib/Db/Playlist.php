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
 * @copyright Pauli Järvinen 2017 - 2023
 */

namespace OCA\Music\Db;

use OCA\Music\Utility\Util;

/**
 * @method string getName()
 * @method setName(string $name)
 * @method string getTrackIds()
 * @method setTrackIds(string $trackIds)
 * @method string getComment()
 * @method setComment(string $comment)
 * @method ?string getStarred()
 * @method void setStarred(?string $timestamp)
 * @method ?int getRating()
 * @method setRating(?int $rating)
 */
class Playlist extends Entity {
	public $name;
	public $trackIds;
	public $comment;
	public $starred;
	public $rating;

	// injected separately when needed
	private $duration;

	public function __construct() {
		$this->addType('rating', 'int');
	}

	public function getDuration() : ?int {
		return $this->duration;
	}

	public function setDuration(?int $duration) : void {
		$this->duration = $duration;
	}

	public function getTrackCount() : int {
		return \count($this->getTrackIdsAsArray());
	}

	/**
	 * @return int[]
	 */
	public function getTrackIdsAsArray() : array {
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
	public function setTrackIdsFromArray(array $trackIds) : void {
		// encode to format like "|123|45|667|"
		$this->setTrackIds('|' . \implode('|', $trackIds) . '|');
	}

	public function toAPI() : array {
		return [
			'name' => $this->getName(),
			'trackIds' => $this->getTrackIdsAsArray(),
			'id' => $this->getId(),
			'created' => $this->getCreated(),
			'updated' => $this->getUpdated(),
			'comment' => $this->getComment()
		];
	}

	public function toAmpacheApi(callable $createImageUrl) : array {
		return [
			'id' => (string)$this->getId(),
			'name' => $this->getName(),
			'owner' => $this->getUserId(),
			'items' => $this->getTrackCount(),
			'art' => $createImageUrl($this),
			'flag' => !empty($this->getStarred()),
			'rating' => $this->getRating() ?? 0,
			'type' => 'Private'
		];
	}

	public function toSubsonicApi() : array {
		return [
			'id' => $this->getId(),
			'name' => $this->getName(),
			'owner' => $this->userId,
			'public' => false,
			'songCount' => $this->getTrackCount(),
			'duration' => $this->getDuration(),
			'comment' => $this->getComment() ?: '',
			'created' => Util::formatZuluDateTime($this->getCreated()),
			'changed' => Util::formatZuluDateTime($this->getUpdated()),
			'coverArt' => 'pl-' . $this->getId() // work around: DSub always fetches the art using ID like "pl-NNN" even if we  use some other format here
		];
	}
}
