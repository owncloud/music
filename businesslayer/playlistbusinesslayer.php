<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2016 - 2020
 */

namespace OCA\Music\BusinessLayer;

use \OCA\Music\AppFramework\BusinessLayer\BusinessLayer;
use \OCA\Music\AppFramework\Core\Logger;

use \OCA\Music\Db\PlaylistMapper;
use \OCA\Music\Db\Playlist;

use \OCA\Music\Utility\Util;

class PlaylistBusinessLayer extends BusinessLayer {
	private $logger;
	private $trackBusinessLayer;

	public function __construct(
			PlaylistMapper $playlistMapper,
			TrackBusinessLayer $trackBusinessLayer,
			Logger $logger) {
		parent::__construct($playlistMapper);
		$this->logger = $logger;
		$this->trackBusinessLayer = $trackBusinessLayer;
	}

	public function addTracks($trackIds, $playlistId, $userId) {
		$playlist = $this->find($playlistId, $userId);
		$prevTrackIds = $playlist->getTrackIdsAsArray();
		$playlist->setTrackIdsFromArray(\array_merge($prevTrackIds, $trackIds));
		$this->mapper->update($playlist);
		return $playlist;
	}

	public function removeTracks($trackIndices, $playlistId, $userId) {
		$playlist = $this->find($playlistId, $userId);
		$trackIds = $playlist->getTrackIdsAsArray();
		$trackIds = \array_diff_key($trackIds, \array_flip($trackIndices));
		$playlist->setTrackIdsFromArray($trackIds);
		$this->mapper->update($playlist);
		return $playlist;
	}

	public function moveTrack($fromIndex, $toIndex, $playlistId, $userId) {
		$playlist = $this->find($playlistId, $userId);
		$trackIds = $playlist->getTrackIdsAsArray();
		$movedTrack = \array_splice($trackIds, $fromIndex, 1);
		\array_splice($trackIds, $toIndex, 0, $movedTrack);
		$playlist->setTrackIdsFromArray($trackIds);
		$this->mapper->update($playlist);
		return $playlist;
	}

	public function create($name, $userId) {
		$playlist = new Playlist();
		$playlist->setName(Util::truncate($name, 256)); // some DB setups can't truncate automatically to column max size
		$playlist->setUserId($userId);

		return $this->mapper->insert($playlist);
	}

	public function rename($name, $playlistId, $userId) {
		$playlist = $this->find($playlistId, $userId);
		$playlist->setName(Util::truncate($name, 256)); // some DB setups can't truncate automatically to column max size
		$this->mapper->update($playlist);
		return $playlist;
	}

	/**
	 * removes tracks from all available playlists
	 * @param int[] $trackIds array of all track IDs to remove
	 */
	public function removeTracksFromAllLists($trackIds) {
		foreach ($trackIds as $trackId) {
			$affectedLists = $this->mapper->findListsContainingTrack($trackId);

			foreach ($affectedLists as $playlist) {
				$prevTrackIds = $playlist->getTrackIdsAsArray();
				$playlist->setTrackIdsFromArray(\array_diff($prevTrackIds, [$trackId]));
				$this->mapper->update($playlist);
			}
		}
	}

	/**
	 * get list of Track objects belonging to a given playlist
	 * @param int $playlistId
	 * @param string $userId
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return Track[]
	 */
	public function getPlaylistTracks($playlistId, $userId, $limit=null, $offset=null) {
		$playlist = $this->find($playlistId, $userId);
		$trackIds = $playlist->getTrackIdsAsArray();

		$trackIds = \array_slice($trackIds, \intval($offset), $limit);

		$tracks = empty($trackIds) ? [] : $this->trackBusinessLayer->findById($trackIds, $userId);

		// The $tracks contains the songs in unspecified order and with no duplicates.
		// Build a new array where the tracks are in the same order as in $trackIds.
		// First create an index as a middle-step.
		$tracksById = Util::createIdLookupTable($tracks);

		$playlistTracks = [];
		foreach ($trackIds as $index => $trackId) {
			$track = $tracksById[$trackId];
			// in case the same track comes up again in the list, clone the track object
			// to have different number of the instances
			if ($track->getNumberOnPlaylist() !== null) {
				$track = clone $track;
			}
			$track->setNumberOnPlaylist(\intval($offset) + $index + 1);
			$playlistTracks[] = $track;
		}

		return $playlistTracks;
	}

	/**
	 * get the total duration of all the tracks on a playlist
	 * 
	 * @param int $playlistId        	
	 * @param string $userId        	
	 * @return int duration in seconds
	 */
	public function getDuration($playlistId, $userId) {
		$playlist = $this->find ( $playlistId, $userId );
		$trackIds = $playlist->getTrackIdsAsArray ();
		$durations = $this->trackBusinessLayer->mapper->getDurations ( $trackIds );

		// We can't simply sum up the values of $durations array, because the playlist may
		// contain duplicate entries, and those are not reflected in $durations.
		$sum = 0;
		foreach ( $trackIds as $trackId ) {
			$sum += $durations [$trackId];
		}

		return $sum;
	}

}
