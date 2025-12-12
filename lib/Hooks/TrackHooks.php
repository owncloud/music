<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Matthew Wells
 * @copyright Matthew Wells 2025
 */

namespace OCA\Music\Hooks;

use OCA\Music\BusinessLayer\TrackBusinessLayer;
use OCA\Music\Service\ScrobblerService;

class TrackHooks {
	private TrackBusinessLayer $trackBusinessLayer;

	/** @var ScrobblerService[] */
	private array $scrobblerServices;

	public function __construct(TrackBusinessLayer $trackBusinessLayer, array $scrobblerServices) {
		$this->trackBusinessLayer = $trackBusinessLayer;
		$this->scrobblerServices = $scrobblerServices;
	}

	public function register() : void {
		$this->trackBusinessLayer->listen(
			TrackBusinessLayer::class,
			'recordTrackPlayed',
			fn (int $trackId, string $userId, \DateTime $timeOfPlay) => $this->scrobble($trackId, $userId, $timeOfPlay)
		);
	}

	private function scrobble(int $trackId, string $userId, \DateTime $timeOfPlay) : void {
		foreach ($this->scrobblerServices as $scrobblerService) {
			$scrobblerService->scrobbleTrack([$trackId], $userId, $timeOfPlay);
		}
	}
}
