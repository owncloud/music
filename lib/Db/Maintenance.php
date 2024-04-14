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
 * @copyright Pauli Järvinen 2017 - 2022
 */

namespace OCA\Music\Db;

use OCP\IDBConnection;

use OCA\Music\AppFramework\Core\Logger;

class Maintenance {

	/** @var IDBConnection */
	private $db;
	/** @var Logger */
	private $logger;

	public function __construct(IDBConnection $db, Logger $logger) {
		$this->db = $db;
		$this->logger = $logger;
	}

	/**
	 * Remove cover_file_id from album if the corresponding file does not exist
	 */
	private function removeObsoleteCoverImagesFromTable($table) {
		return $this->db->executeUpdate(
			"UPDATE `*PREFIX*$table` SET `cover_file_id` = NULL
			WHERE `cover_file_id` IS NOT NULL AND `cover_file_id` IN (
				SELECT `cover_file_id` FROM (
					SELECT `cover_file_id` FROM `*PREFIX*$table`
					LEFT JOIN `*PREFIX*filecache`
						ON `cover_file_id`=`fileid`
					WHERE `fileid` IS NULL
				) mysqlhack
			)"
		);
	}

	/**
	 * Remove cover_file_id from album if the corresponding file does not exist
	 */
	private function removeObsoleteAlbumCoverImages() {
		return $this->removeObsoleteCoverImagesFromTable('music_albums');
	}

	/**
	 * Remove cover_file_id from artist if the corresponding file does not exist
	 */
	private function removeObsoleteArtistCoverImages() {
		return $this->removeObsoleteCoverImagesFromTable('music_artists');
	}

	/**
	 * Remove all such rows from $tgtTable which don't have corresponding rows in $refTable
	 * so that $tgtTableKey = $refTableKey.
	 * @param string $tgtTable
	 * @param string $refTable
	 * @param string $tgtTableKey
	 * @param string $refTableKey
	 * @param string|null $extraCond
	 * @return int Number of removed rows
	 */
	private function removeUnreferencedDbRows($tgtTable, $refTable, $tgtTableKey, $refTableKey, $extraCond=null) {
		$tgtTable = '*PREFIX*' . $tgtTable;
		$refTable = '*PREFIX*' . $refTable;

		return $this->db->executeUpdate(
			"DELETE FROM `$tgtTable` WHERE `id` IN (
				SELECT `id` FROM (
					SELECT `$tgtTable`.`id`
					FROM `$tgtTable` LEFT JOIN `$refTable`
					ON `$tgtTable`.`$tgtTableKey` = `$refTable`.`$refTableKey`
					WHERE `$refTable`.`$refTableKey` IS NULL
				) mysqlhack
			)"
			.
			(empty($extraCond) ? '' : " AND $extraCond")
		);
	}

	/**
	 * Remove tracks which do not have corresponding file in the file system
	 * @return int Number of removed tracks
	 */
	private function removeObsoleteTracks() {
		return $this->removeUnreferencedDbRows('music_tracks', 'filecache', 'file_id', 'fileid');
	}

	/**
	 * Remove tracks which belong to non-existing album
	 * @return int Number of removed tracks
	 */
	private function removeTracksWithNoAlbum() {
		return $this->removeUnreferencedDbRows('music_tracks', 'music_albums', 'album_id', 'id');
	}

	/**
	 * Remove tracks which are performed by non-existing artist
	 * @return int Number of removed tracks
	 */
	private function removeTracksWithNoArtist() {
		return $this->removeUnreferencedDbRows('music_tracks', 'music_artists', 'artist_id', 'id');
	}

	/**
	 * Remove albums which have no tracks
	 * @return int Number of removed albums
	 */
	private function removeObsoleteAlbums() {
		return $this->removeUnreferencedDbRows('music_albums', 'music_tracks', 'id', 'album_id');
	}

	/**
	 * Remove albums which have a non-existing album artist
	 * @return int Number of removed albums
	 */
	private function removeAlbumsWithNoArtist() {
		return $this->removeUnreferencedDbRows('music_albums', 'music_artists', 'album_artist_id', 'id');
	}

	/**
	 * Remove artists which have no albums and no tracks
	 * @return int Number of removed artists
	 */
	private function removeObsoleteArtists() {
		// Note: This originally used the NOT IN operation but that was terribly inefficient on PostgreSQL,
		// see https://github.com/owncloud/music/issues/997
		return $this->db->executeUpdate(
			'DELETE FROM `*PREFIX*music_artists`
				WHERE NOT EXISTS (SELECT 1 FROM `*PREFIX*music_albums` WHERE `*PREFIX*music_artists`.`id` = `album_artist_id` LIMIT 1)
				AND   NOT EXISTS (SELECT 1 FROM `*PREFIX*music_tracks` WHERE `*PREFIX*music_artists`.`id` = `artist_id` LIMIT 1)'
		);
	}

	/**
	 * Remove bookmarks referring tracks which do not exist
	 * @return int Number of removed bookmarks
	 */
	private function removeObsoleteBookmarks() {
		return $this->removeUnreferencedDbRows('music_bookmarks', 'music_tracks', 'entry_id', 'id', '`type` = 1')
			+ $this->removeUnreferencedDbRows('music_bookmarks', 'music_podcast_episodes', 'entry_id', 'id', '`type` = 2');
	}

	/**
	 * Remove podcast episodes which have a non-existing podcast channel
	 * @return int Number of removed albums
	 */
	private function removeObsoletePodcastEpisodes() {
		return $this->removeUnreferencedDbRows('music_podcast_episodes', 'music_podcast_channels', 'channel_id', 'id');
	}

	/**
	 * Removes orphaned data from the database
	 * @return array describing the number of removed entries per type
	 */
	public function cleanUp() : array {
		$removedCovers = $this->removeObsoleteAlbumCoverImages();
		$removedCovers += $this->removeObsoleteArtistCoverImages();

		$removedTracks = $this->removeObsoleteTracks();
		$removedAlbums = $this->removeObsoleteAlbums();
		$removedArtists = $this->removeObsoleteArtists();
		$removedBookmarks = $this->removeObsoleteBookmarks();
		$removedEpisodes = $this->removeObsoletePodcastEpisodes();

		$removedAlbums += $this->removeAlbumsWithNoArtist();
		$removedTracks += $this->removeTracksWithNoAlbum();
		$removedTracks += $this->removeTracksWithNoArtist();

		return [
			'covers' => $removedCovers,
			'artists' => $removedArtists,
			'albums' => $removedAlbums,
			'tracks' => $removedTracks,
			'bookmarks' => $removedBookmarks,
			'podcast_episodes' => $removedEpisodes
		];
	}

	/**
	 * Wipe clean the given table, either targeting a specific user all users
	 * @param string $table Name of the table, _excluding_ the prefix *PREFIX*music_
	 * @param ?string $userId
	 * @param bool $allUsers
	 * @throws \InvalidArgumentException
	 */
	private function resetTable(string $table, ?string $userId, bool $allUsers = false) : void {
		if ($userId && $allUsers) {
			throw new \InvalidArgumentException('userId should be null if allUsers targeted');
		}

		$params = [];
		$sql = "DELETE FROM `*PREFIX*music_$table`";
		if (!$allUsers) {
			$sql .=  ' WHERE `user_id` = ?';
			$params[] = $userId;
		}
		$this->db->executeUpdate($sql, $params);

	}

	/**
	 * Wipe clean the music library of the given user, or all users
	 */
	public function resetLibrary(?string $userId, bool $allUsers = false) : void {
		$tables = [
			'tracks',
			'albums',
			'artists',
			'playlists',
			'genres',
			'bookmarks',
			'cache'
		];

		foreach ($tables as $table) {
			$this->resetTable($table, $userId, $allUsers);
		}

		if ($allUsers) {
			$this->logger->log("Erased music databases of all users", 'info');
		} else {
			$this->logger->log("Erased music database of user $userId", 'info');
		}
	}

	/**
	 * Wipe clean all the music of the given user, including the library, podcasts, radio, Ampache/Subsonic keys
	 */
	public function resetAllData(string $userId) : void {
		$this->resetLibrary($userId);

		$tables = [
			'ampache_sessions',
			'ampache_users',
			'podcast_channels',
			'podcast_episodes',
			'radio_stations'
		];

		foreach ($tables as $table) {
			$this->resetTable($table, $userId);
		}
	}
}
