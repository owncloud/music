<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2016
 */

namespace OCA\Music\BusinessLayer;

use \OCA\Music\AppFramework\BusinessLayer\BusinessLayer;
use \OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use \OCA\Music\AppFramework\Core\Logger;
use \OCP\AppFramework\Db\DoesNotExistException;
use \OCP\AppFramework\Db\MultipleObjectsReturnedException;


use \OCA\Music\Db\PlaylistMapper;
use \OCA\Music\Db\Playlist;

class PlaylistBusinessLayer extends BusinessLayer {

	private $logger;

	public function __construct(PlaylistMapper $playlistMapper, Logger $logger) {
		parent::__construct($playlistMapper);
		$this->logger = $logger;
	}

	public function addTracks($trackIds, $playlistId, $userId) {
		$playlist = $this->find($playlistId, $userId);
		$prevTrackIds = $playlist->getTrackIdsAsArray();
		$playlist->setTrackIdsFromArray(array_merge($prevTrackIds, $trackIds));
		$this->mapper->update($playlist);
		return $playlist;
	}

	public function removeTracks($trackIndices, $playlistId, $userId) {
		$playlist = $this->find($playlistId, $userId);
		$trackIds = $playlist->getTrackIdsAsArray();
		$trackIds = array_diff_key($trackIds, array_flip($trackIndices));
		$playlist->setTrackIdsFromArray($trackIds);
		$this->mapper->update($playlist);
		return $playlist;
	}

	public function moveTrack($fromIndex, $toIndex, $playlistId, $userId) {
		$playlist = $this->find($playlistId, $userId);
		$trackIds = $playlist->getTrackIdsAsArray();
		$movedTrack = array_splice($trackIds, $fromIndex, 1);
		array_splice($trackIds, $toIndex, 0, $movedTrack);
		$playlist->setTrackIdsFromArray($trackIds);
		$this->mapper->update($playlist);
		return $playlist;
	}

	public function create($name, $userId) {
		$playlist = new Playlist();
		$playlist->setName($name);
		$playlist->setUserId($userId);

		return $this->mapper->insert($playlist);
	}

	public function rename($name, $playlistId, $userId) {
		$playlist = $this->find($playlistId, $userId);
		$playlist->setName($name);
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
				$playlist->setTrackIdsFromArray(array_diff($prevTrackIds, [$trackId]));
				$this->mapper->update($playlist);
			}
		}
	}
}
