<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Volkan Gezer <volkangezer@gmail.com>
 * @copyright Morris Jobke 2014
 * @copyright Volkan Gezer 2014
 */

namespace OCA\Music\Db;

use \OCA\Music\AppFramework\Core\Db;
use \OCA\Music\AppFramework\Db\IMapper;
use \OCA\Music\AppFramework\Db\Mapper;

class PlaylistMapper extends Mapper implements IMapper {

	public function __construct(DB $db){
		parent::__construct($db, 'music_playlists', '\OCA\Music\Db\Playlist');
	}

	/**
	 * @param string $condition
	 */
	private function makeSelectQuery($condition=null){
		return 'SELECT `name`, `id` ' .
			'FROM `*PREFIX*music_playlists` ' .
			'WHERE `user_id` = ? ' . $condition;
	}

	/**
	 * @param string $userId
	 * @param integer $limit
	 * @param integer $offset
	 */
	public function findAll($userId, $limit=null, $offset=null){
		$sql = $this->makeSelectQuery();
		$params = array($userId);
		return $this->findEntities($sql, $params, $limit, $offset);
	}

	/**
	 * @param integer $id
	 * @param string $userId
	 */
	public function find($id, $userId){
		$sql = $this->makeSelectQuery('AND `id` = ?');
		$params = array($userId, $id);
		return $this->findEntity($sql, $params);
	}

	/**
	 * adds tracks to a playlist
	 * @param int[] $trackIds array of all track IDs to add
	 * @param int $id       playlist ID
	 */
	public function addTracks($trackIds, $id) {
		$currentTrackIds = $this->getTracks($id);

		// returns elements of $trackIds that are not in $currentTrackIds
		$newTrackIds = array_diff($trackIds, $currentTrackIds);

		$sql = 'INSERT INTO `*PREFIX*music_playlist_tracks` (`playlist_id`, ' .
					'`track_id`) VALUES ( ?, ? )';

		// this is called for each track ID, because the is no identical way
		// to do a multi insert for all supported databases
		foreach ($newTrackIds as $trackId) {
			$this->execute($sql, array($id, $trackId));
		}
	}

	/**
	 * gets track of a playlist
	 * @param  int $id ID of the playlist
	 * @return int[] list of all track IDs
	 */
	public function getTracks($id) {
		$sql = 'SELECT `track_id` FROM `*PREFIX*music_playlist_tracks` '.
				'WHERE `playlist_id` = ?';
		$result = $this->execute($sql, array($id));

		$trackIds = array();
		while($row = $result->fetchRow()){
			$trackIds[] = (int) $row['track_id'];
		}

		return $trackIds;
	}

	/**
	 * deletes a playlist
	 * @param int $id       playlist ID
	 */
	public function delete($id) {
		// remove all tracks in it
		$this->removeTracks($id);

		// then remove playlist
		$sql = 'DELETE FROM `*PREFIX*music_playlists` ' .
					'WHERE `id` = ?';
		$this->execute($sql, array($id));

	}

	/**
	 * removes tracks from a playlist
	 * @param int $id       playlist ID
	 * @param int[] $trackIds array of all track IDs to remove - if empty all tracks will be removed
	 */
	public function removeTracks($id, $trackIds = null) {
		// TODO delete multiple per SQL statement
		$sql = 'DELETE FROM `*PREFIX*music_playlist_tracks` ' .
					'WHERE `playlist_id` = ?';

		if(is_null($trackIds)) {
			$this->execute($sql, array($id));
		} else {
			$sql .= 'AND `track_id` = ?';
			foreach ($trackIds as $trackId) {
				$this->execute($sql, array($id, $trackId));
			}
		}

	}

}
