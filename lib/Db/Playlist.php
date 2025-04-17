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
 * @copyright Pauli Järvinen 2017 - 2025
 */

namespace OCA\Music\Db;

use OCA\Music\Utility\Util;
use OCP\IURLGenerator;

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
	private $readOnly;

	public function __construct() {
		$this->addType('rating', 'int');
	}

	public function getDuration() : ?int {
		return $this->duration;
	}

	public function setDuration(?int $duration) : void {
		$this->duration = $duration;
	}

	public function getReadOnly() : bool {
		return $this->readOnly ?? false;
	}

	public function setReadOnly(bool $readOnly) : void {
		$this->readOnly = $readOnly;
	}

	public function getTrackCount() : int {
		return \count($this->getTrackIdsAsArray());
	}

	/**
	 * @return int[]
	 */
	public function getTrackIdsAsArray() : array {
		if ($this->isEmpty()) {
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

	public function getTrackIdsHash() : ?string {
		return $this->isEmpty() ? null : \md5($this->getTrackIds());
	}

	public function toApi(IURLGenerator $urlGenerator) : array {
		return [
			'name' => $this->getName(),
			'trackIds' => $this->getTrackIdsAsArray(),
			'id' => $this->getId(),
			'created' => $this->getCreated(),
			'updated' => $this->getUpdated(),
			'comment' => $this->getComment(),
			'cover' => $this->getCoverUrl($urlGenerator)
		];
	}

	public function toShivaApi(IURLGenerator $urlGenerator) : array {
		$trackIds = $this->getTrackIdsAsArray();
		return [
			'name' => $this->getName(),
			'length' => \count($trackIds),
			'tracks' => \array_map(fn($id, $index) => [
				'id' => $id,
				'index' => $index,
				'uri' => $urlGenerator->linkToRoute('music.shivaApi.track', ['id' => $id])
			], $trackIds, \array_keys($trackIds)),
			'id' => $this->getId(),
			'creation_date' => $this->getCreated()
		];
	}

	public function toAmpacheApi(callable $createImageUrl, bool $includeTracks) : array {
		$result = [
			'id' => (string)$this->getId(),
			'name' => $this->getName(),
			'owner' => $this->getUserId(),
			'art' => $createImageUrl($this),
			'flag' => !empty($this->getStarred()),
			'rating' => $this->getRating() ?? 0,
			'type' => 'Private',
			'has_access' => !$this->getReadOnly(),
			'has_collaborate' => !$this->getReadOnly(),
			'last_update' => \strtotime($this->getUpdated()),
			'md5' => $this->getTrackIdsHash()
		];
		$result['has_art'] = !empty($result['art']);

		if ($includeTracks) {
			$ids = $this->getTrackIdsAsArray();
			$result['items'] = ['playlisttrack' => \array_map(fn(int $trackId, int $index) => [
				'id' => (string)$trackId,
				'text' => $index + 1
			], $ids, \array_keys($ids))];
		} else {
			$result['items'] = $this->getTrackCount();
		}

		return $result;
	}

	public function toSubsonicApi() : array {
		return [
			'id' => (string)$this->getId(),
			'name' => $this->getName(),
			'owner' => $this->userId,
			'public' => false,
			'songCount' => $this->getTrackCount(),
			'duration' => $this->getDuration(),
			'comment' => $this->getComment() ?: '',
			'created' => Util::formatZuluDateTime($this->getCreated()),
			'changed' => Util::formatZuluDateTime($this->getUpdated()),
			'coverArt' => 'pl-' . $this->getId() // work around: DSub always fetches the art using ID like "pl-NNN" even if we would use some other format here
		];
	}

	private function isEmpty() : bool {
		// the list is empty if there is nothing between the leading and trailing '|'
		return (!$this->trackIds || \strlen($this->trackIds) < 3);
	}

	private function getCoverUrl(IURLGenerator $urlGenerator) : ?string {
		// the list might not have an id in case it's a generated playlist
		if ($this->getId() && !$this->isEmpty()) {
			return $urlGenerator->linkToRoute('music.playlistApi.getCover', ['id' => $this->getId()]);
		} else {
			return null;
		}
	}
}
