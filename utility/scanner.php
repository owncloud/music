<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @copyright Morris Jobke 2013, 2014
 */

namespace OCA\Music\Utility;

use OC\Hooks\PublicEmitter;

use \OCP\Files\Folder;
use \OCP\IConfig;

use \OCA\Music\AppFramework\Core\Logger;

use \OCA\Music\BusinessLayer\ArtistBusinessLayer;
use \OCA\Music\BusinessLayer\AlbumBusinessLayer;
use \OCA\Music\BusinessLayer\TrackBusinessLayer;
use \OCA\Music\BusinessLayer\PlaylistBusinessLayer;
use \OCA\Music\Db\Cache;
use OCP\IDBConnection;
use Symfony\Component\Console\Output\OutputInterface;


class Scanner extends PublicEmitter {

	private $extractor;
	private $artistBusinessLayer;
	private $albumBusinessLayer;
	private $trackBusinessLayer;
	private $playlistBusinessLayer;
	private $cache;
	private $logger;
	/** @var IDBConnection  */
	private $db;
	private $configManager;
	private $appName;
	private $userFolder;

	public function __construct(Extractor $extractor,
								ArtistBusinessLayer $artistBusinessLayer,
								AlbumBusinessLayer $albumBusinessLayer,
								TrackBusinessLayer $trackBusinessLayer,
								PlaylistBusinessLayer $playlistBusinessLayer,
								Cache $cache,
								Logger $logger,
								IDBConnection $db,
								IConfig $configManager,
								$appName,
								$userFolder = null){
		$this->extractor = $extractor;
		$this->artistBusinessLayer = $artistBusinessLayer;
		$this->albumBusinessLayer = $albumBusinessLayer;
		$this->trackBusinessLayer = $trackBusinessLayer;
		$this->playlistBusinessLayer = $playlistBusinessLayer;
		$this->cache = $cache;
		$this->logger = $logger;
		$this->db = $db;
		$this->configManager = $configManager;
		$this->appName = $appName;
		$this->userFolder = $userFolder;

		// Trying to enable stream support
		if(ini_get('allow_url_fopen') !== '1') {
			$this->logger->log('allow_url_fopen is disabled. It is strongly advised to enable it in your php.ini', 'warn');
			@ini_set('allow_url_fopen', '1');
		}
	}

	public function updateById($fileId, $userId) {
		// TODO properly initialize the user folder for external events (upload to public share)
		if ($this->userFolder === null) {
			return;
		}

		try {
			$files = $this->userFolder->getById($fileId);
			if(count($files) > 0) {
				// use first result
				$this->update($files[0], $userId, $this->userFolder);
			}
		} catch (\OCP\Files\NotFoundException $e) {
			// just ignore the error
			$this->logger->log('updateById - file not found - '. $fileId , 'debug');
		}
	}

	/**
	 * Gets called by 'post_write' hook (file creation, file update)
	 * @param \OCP\Files\Node $file the file
	 */
	public function update($file, $userId, $userHome){
		// debug logging
		$this->logger->log('update - '. $file->getPath() , 'debug');

		if(!($file instanceof \OCP\Files\File)) {
			return;
		}

		if(!$userHome) {
			$userHome = $this->userFolder;
		}

		if(!$userHome) {
			return;
		}

		$musicFolder = $this->getUserMusicFolder($userId, $userHome);
		// skip files that aren't inside the user specified path
		if(!self::startsWith($file->getPath(), $musicFolder->getPath())) {
			$this->logger->log('skipped - outside of specified path' , 'debug');
			return;
		}

		$mimetype = $file->getMimeType();

		// debug logging
		$this->logger->log('update - mimetype '. $mimetype , 'debug');
		$this->emit('\OCA\Music\Utility\Scanner', 'update', array($file->getPath()));

		if(self::startsWith($mimetype, 'image')) {
			$coverFileId = $file->getId();
			$parentFolderId = $file->getParent()->getId();
			if ($this->albumBusinessLayer->updateFolderCover($coverFileId, $parentFolderId)) {
				$this->logger->log('update - the image was set as cover for some album(s)', 'debug');
				$this->cache->remove($userId);
			}
			return;
		}

		if(!self::startsWith($mimetype, 'audio') && !self::startsWith($mimetype, 'application/ogg')) {
			return;
		}

		if(ini_get('allow_url_fopen')) {

			$fieldsFromFileName = self::parseFileName($file->getName());
			$fileInfo = $this->extractor->extract($file);

			// Track artist and album artist
			$artist = self::getId3Tag($fileInfo, 'artist');
			$albumArtist = self::getFirstOfId3Tags($fileInfo, ['band', 'albumartist', 'album artist', 'album_artist']);

			// use artist and albumArtist as fallbacks for each other
			if(self::isNullOrEmpty($albumArtist)){
				$albumArtist = $artist;
			}

			if(self::isNullOrEmpty($artist)){
				$artist = $albumArtist;
			}

			// set 'Unknown Artist' in case neither artist nor albumArtist was found
			if(self::isNullOrEmpty($artist)){
				$artist = null;
				$albumArtist = null;
			}

			// title
			$title = self::getId3Tag($fileInfo, 'title');
			if(self::isNullOrEmpty($title)){
				$title = $fieldsFromFileName['title'];
			}

			// album
			$album = self::getId3Tag($fileInfo, 'album');
			if(self::isNullOrEmpty($album)){
				// album name not set in fileinfo, use parent folder name as album name unless it is the root folder
				if ( $userHome->getId() === $file->getParent()->getId() ) {
					$album = null;
				} else {
					$album = $file->getParent()->getName();
				}
			}

			// track number
			$trackNumber = self::getFirstOfId3Tags($fileInfo, ['track_number', 'tracknumber', 'track'], 
					$fieldsFromFileName['track_number']);
			$trackNumber = self::normalizeOrdinal($trackNumber);

			// disc number
			$discNumber = self::getFirstOfId3Tags($fileInfo, ['discnumber', 'part_of_a_set'], '1');
			$discNumber = self::normalizeOrdinal($discNumber);

			// year
			$year = self::getFirstOfId3Tags($fileInfo, ['year', 'date']);
			$year = self::normalizeYear($year);

			$fileId = $file->getId();

			$length = null;
			if (array_key_exists('playtime_seconds', $fileInfo)) {
				$length = ceil($fileInfo['playtime_seconds']);
			}

			$bitrate = null;
			if (array_key_exists('audio', $fileInfo) && array_key_exists('bitrate', $fileInfo['audio'])) {
				$bitrate = $fileInfo['audio']['bitrate'];
			}

			// debug logging
			$this->logger->log('extracted metadata - ' .
				"artist: $artist, albumArtist: $albumArtist, album: $album, title: $title, track#: $trackNumber, ".
				"disc#: $discNumber, year: $year, mimetype: $mimetype, length: $length, bitrate: $bitrate, ".
				"fileId: $fileId, userId: $userId", 'debug');

			// add/update artist and get artist entity
			$artist = $this->artistBusinessLayer->addOrUpdateArtist($artist, $userId);
			$artistId = $artist->getId();

			// add/update albumArtist and get artist entity
			$albumArtist = $this->artistBusinessLayer->addOrUpdateArtist($albumArtist, $userId);
			$albumArtistId = $albumArtist->getId();

			// add/update album and get album entity
			$album = $this->albumBusinessLayer->addOrUpdateAlbum($album, $year, $discNumber, $albumArtistId, $userId);
			$albumId = $album->getId();

			// add/update track and get track entity
			$track = $this->trackBusinessLayer->addOrUpdateTrack($title, $trackNumber, $artistId,
				$albumId, $fileId, $mimetype, $userId, $length, $bitrate);

			// if present, use the embedded album art as cover for the respective album
			if(self::getId3Tag($fileInfo, 'picture') != null) {
				$this->albumBusinessLayer->setCover($fileId, $albumId);
			}
			// if this file is an existing file which previously was used as cover for an album but now
			// the file no longer contains any embedded album art
			else if($this->fileIsCoverForAlbum($fileId, $albumId, $userId)) {
				$this->albumBusinessLayer->removeCover($fileId);
				$this->findEmbeddedCoverForAlbum($albumId, $userId);
			}

			// invalidate the cache as the music collection was changed
			$this->cache->remove($userId);

			// debug logging
			$this->logger->log('imported entities - ' .
				"artist: $artistId, albumArtist: $albumArtistId, album: $albumId, track: {$track->getId()}",
				'debug');
		}

	}

	/**
	 * @param \OCP\Files\Node $musicFile
	 * @return Array with image MIME and content or null
	 */
	public function parseEmbeddedCoverArt($musicFile){
		$fileInfo = $this->extractor->extract($musicFile);
		return self::getId3Tag($fileInfo, 'picture');
	}

	/**
	 * Get called by 'unshare' hook and 'delete' hook
	 * @param int $fileId the id of the deleted file
	 * @param string $userId the user id of the user to delete the track from
	 */
	public function delete($fileId, $userId){
		// debug logging
		$this->logger->log('delete - '. $fileId , 'debug');
		$this->emit('\OCA\Music\Utility\Scanner', 'delete', array($fileId, $userId));

		$result = $this->trackBusinessLayer->deleteTrack($fileId, $userId);

		if ($result) { // this was a track file
			// remove obsolete artists and albums, and track references in playlists
			$this->albumBusinessLayer->deleteById($result['obsoleteAlbums']);
			$this->artistBusinessLayer->deleteById($result['obsoleteArtists']);
			$this->playlistBusinessLayer->removeTracksFromAllLists($result['deletedTracks']);

			// check if the removed track was used as embedded cover art file for a remaining album
			foreach ($result['remainingAlbums'] as $albumId) {
				if ($this->fileIsCoverForAlbum($fileId, $albumId, $userId)) {
					$this->albumBusinessLayer->removeCover($fileId);
					$this->findEmbeddedCoverForAlbum($albumId, $userId);
				}
			}

			// invalidate the cache as the music collection was changed
			$this->cache->remove($userId);

			// debug logging
			$this->logger->log('removed entities - ' . json_encode($result), 'debug');
		}
		// maybe this was an image file
		else if ($this->albumBusinessLayer->removeCover($fileId)) {
			$this->cache->remove($userId);
		}
	}

	public function getUserMusicFolder($userId, $userHome) {
		$musicPath = $this->configManager->getUserValue($userId, $this->appName, 'path');
		
		if ($musicPath !== null && $musicPath !== '/' && $musicPath !== '') {
			return $userHome->get($musicPath);
		} else {
			return $userHome;
		}
	}

	/**
	 * search for files by mimetype inside an optional user specified path
	 *
	 * @return \OCP\Files\Node[]
	 */
	public function getMusicFiles($userId, $userHome) {
		try {
			$folder = $this->getUserMusicFolder($userId, $userHome);
		} catch (\OCP\Files\NotFoundException $e) {
			return array();
		}

		$audio = $folder->searchByMime('audio');
		$ogg = $folder->searchByMime('application/ogg');

		return array_merge($audio, $ogg);
	}

	public function getScannedFiles($userId) {
		return $this->trackBusinessLayer->findAllFileIds($userId);
	}

	public function getUnscannedMusicFileIds($userId, $userHome) {
		$scannedIds = $this->getScannedFiles($userId);
		$musicFiles = $this->getMusicFiles($userId, $userHome);
		$allIds = array_map(function($f) { return $f->getId(); }, $musicFiles);
		$unscannedIds = array_values(array_diff($allIds, $scannedIds));

		$count = count($unscannedIds);
		if ($count) {
			$this->logger->log("Found $count unscanned music files for user $userId", 'info');
		} else {
			$this->logger->log("No unscanned music files for user $userId", 'debug');
		}

		return $unscannedIds;
	}

	public function scanFiles($userId, $userHome, $fileIds, OutputInterface $debugOutput = null) {
		$count = count($fileIds);
		$this->logger->log("Scanning $count files of user $userId", 'debug');

		// back up the execution time limit
		$executionTime = intval(ini_get('max_execution_time'));
		// set execution time limit to unlimited
		set_time_limit(0);

		$count = 0;
		foreach ($fileIds as $fileId) {
			$fileNodes = $userHome->getById($fileId);
			if (count($fileNodes) > 0) {
				$file = $fileNodes[0];
				if($debugOutput) {
					$before = memory_get_usage(true);
				}
				$this->update($file, $userId, $userHome);
				if($debugOutput) {
					$after = memory_get_usage(true);
					$diff = $after - $before;
					$afterFileSize = new FileSize($after);
					$diffFileSize = new FileSize($diff);
					$humanFilesizeAfter = $afterFileSize->getHumanReadable();
					$humanFilesizeDiff = $diffFileSize->getHumanReadable();
					$path = $file->getPath();
					$debugOutput->writeln("\e[1m $count \e[0m $humanFilesizeAfter \e[1m $diff \e[0m ($humanFilesizeDiff) $path");
				}
				$count++;
			}
			else {
				$this->logger->log("File with id $fileId not found for user $userId", 'warn');
			}
		}

		// reset execution time limit
		set_time_limit($executionTime);

		return $count;
	}

	/**
	 * Wipe clean the music database of the given user, or all users
	 * @param string $userId
	 * @param boolean $allUsers
	 */
	public function resetDb($userId, $allUsers = false) {
		if ($userId && $allUsers) {
			throw new InvalidArgumentException('userId should be null if allUsers targeted');
		}

		$sqls = array(
				'DELETE FROM `*PREFIX*music_tracks`',
				'DELETE FROM `*PREFIX*music_albums`',
				'DELETE FROM `*PREFIX*music_artists`',
				'UPDATE *PREFIX*music_playlists SET track_ids=NULL',
				'DELETE FROM `*PREFIX*music_cache`'
		);

		foreach ($sqls as $sql) {
			$params = [];
			if (!$allUsers) {
				$sql .=  ' WHERE `user_id` = ?';
				$params[] = $userId;
			}
			$this->db->executeUpdate($sql, $params);
		}

		if ($allUsers) {
			$this->logger->log("Erased music databases of all users", 'info');
		} else {
			$this->logger->log("Erased music database of user $userId", 'info');
		}
	}

	/**
	 * Update music path
	 */
	public function updatePath($path, $userId) {
		// TODO currently this function is quite dumb
		// it just drops all entries of an user from the tables
		$this->logger->log("Changing music collection path of user $userId to $path", 'info');
		$this->resetDb($userId);
	}

	public function findCovers() {
		$affectedUsers = $this->albumBusinessLayer->findCovers();
		// scratch the cache for those users whose music collection was touched
		foreach ($affectedUsers as $user) {
			$this->cache->remove($user);
			$this->logger->log('album cover(s) were found for user '. $user , 'debug');
		}
		return !empty($affectedUsers);
	}

	private static function startsWith($string, $potentialStart) {
		return substr($string, 0, strlen($potentialStart)) === $potentialStart;
	}

	private static function isNullOrEmpty($string) {
		return $string === null || $string === '';
	}

	private static function getId3Tag($fileInfo, $tag) {
		if(array_key_exists('comments', $fileInfo)) {
			$comments = $fileInfo['comments'];
			if(array_key_exists($tag, $comments)) {
				return $comments[$tag][0];
			}
		}
		return null;
	}

	private static function getFirstOfId3Tags($fileInfo, array $tags, $defaultValue = null) {
		foreach ($tags as $tag) {
			$value = self::getId3Tag($fileInfo, $tag);
			if (!self::isNullOrEmpty($value)) {
				return $value;
			}
		}
		return $defaultValue;
	}

	private static function normalizeOrdinal($ordinal) {
		// convert format '1/10' to '1'
		$tmp = explode('/', $ordinal);
		$ordinal = $tmp[0];

		// check for numeric values - cast them to int and verify it's a natural number above 0
		if(is_numeric($ordinal) && ((int)$ordinal) > 0) {
			$ordinal = (int)$ordinal;
		} else {
			$ordinal = null;
		}

		return $ordinal;
	}

	private static function parseFileName($fileName) {
		// If the file name starts e.g like "12 something" or "12. something" or "12 - something",
		// the preceeding number is extracted as track number. Everything after the optional track
		// number + delimiters part but before the file extension is extracted as title.
		// The file extension consists of a '.' followed by 1-4 "word characters".
		if(preg_match('/^((\d+)\s*[\s.-]\s*)?(.+)\.(\w{1,4})$/', $fileName, $matches) === 1) {
			return ['track_number' => $matches[2], 'title' => $matches[3]];
		} else {
			return ['track_number' => null, 'title' => $fileName];
		}
	}

	private static function normalizeYear($date) {
		if(ctype_digit($date)) {
			return $date; // the date is a valid year as-is
		} else if(preg_match('/^(\d\d\d\d)-\d\d-\d\d.*/', $date, $matches) === 1) {
			return $matches[1]; // year from ISO-formatted date yyyy-mm-dd
		} else {
			return null;
		}
	}

	private function fileIsCoverForAlbum($fileId, $albumId, $userId) {
		$album = $this->albumBusinessLayer->find($albumId, $userId);
		return ($album != null && $album->getCoverFileId() == $fileId);
	}

	/**
	 * Loop through the tracks of an album and set the first track containing embedded cover art
	 * as cover file for the album
	 * @param int $albumId
	 * @param int $userId
	 */
	private function findEmbeddedCoverForAlbum($albumId, $userId) {
		if ($this->userFolder != null) {
			$tracks = $this->trackBusinessLayer->findAllByAlbum($albumId, $userId);
			foreach ($tracks as $track) {
				$nodes = $this->userFolder->getById($track->getFileId());
				if(count($nodes) > 0) {
					// parse the first valid node and check if it contains embedded cover art
					$image = $this->parseEmbeddedCoverArt($nodes[0]);
					if ($image != null) {
						$this->albumBusinessLayer->setCover($track->getFileId(), $albumId);
						break;
					}
				}
			}
		}
	}
}
