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

	/**
	 * Return a playlist
	 * @param int $playlistId the id of the playlist
	 * @param string $userId the name of the user
	 * @return Playlist playlist
	 */
	public function find($playlistId, $userId) {
		$playlist = $this->mapper->find($playlistId, $userId);
		$playlist->setTrackIds($this->mapper->getTracks($playlistId));
		return $playlist;
	}

	/**
	 * Returns all playlists
	 * @param string $userId the name of the user
	 * @return Playlist[] playlists
	 */
	public function findAll($userId) {
		$playlists = $this->mapper->findAll($userId);
		foreach ($playlists as $list) {
			$list->setTrackIds($this->mapper->getTracks($list->getId()));
		}
		return $playlists;
	}

	public function addTracks($trackIds, $playlistId, $userId) {
		$playlist = $this->mapper->find($playlistId, $userId);
		$this->mapper->addTracks($trackIds, $playlistId);
		$playlist->setTrackIds($this->mapper->getTracks($playlistId));
		return $playlist;
	}

	public function removeTracks($trackIds, $playlistId, $userId) {
		$playlist = $this->mapper->find($playlistId, $userId);
		$this->mapper->removeTracks($playlistId, $trackIds);
		$playlist->setTrackIds($this->mapper->getTracks($playlistId));
		return $playlist;
	}

	public function create($name, $userId) {
		$playlist = new Playlist();
		$playlist->setName($name);
		$playlist->setUserId($userId);

		return $this->mapper->insert($playlist);
	}

	public function rename($name, $playlistId, $userId) {
		$playlist = $this->mapper->find($playlistId, $userId);
		$playlist->setName($name);
		$this->mapper->update($playlist);
		$playlist->setTrackIds($this->mapper->getTracks($playlistId));
		return $playlist;
	}

	public function delete($playlistId, $userId) {
		parent::delete($playlistId, $userId);
		$this->mapper->removeTracks($playlistId);
	}
}
