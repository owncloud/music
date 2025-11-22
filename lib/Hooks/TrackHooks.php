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

	private ScrobblerService $scrobblerService;

	public function __construct(TrackBusinessLayer $trackBusinessLayer, ScrobblerService $scrobblerService) {
		$this->trackBusinessLayer = $trackBusinessLayer;
		$this->scrobblerService = $scrobblerService;
	}

	public function register() : void {
		$this->trackBusinessLayer->listen(
			TrackBusinessLayer::class,
			'recordTrackPlayed',
			fn (int $trackId, string $userId, \DateTime $timeOfPlay) => $this->scrobble($trackId, $userId, $timeOfPlay)
		);
	}

	private function scrobble(int $trackId, string $userId, \DateTime $timeOfPlay) : void {
		$this->scrobblerService->scrobbleTrack([$trackId], $userId, $timeOfPlay);
	}
}
