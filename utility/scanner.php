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
	private $rootFolder;

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
								Folder $rootFolder){
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
		$this->rootFolder = $rootFolder;

		// Trying to enable stream support
		if(ini_get('allow_url_fopen') !== '1') {
			$this->logger->log('allow_url_fopen is disabled. It is strongly advised to enable it in your php.ini', 'warn');
			@ini_set('allow_url_fopen', '1');
		}
	}

	/**
	 * Gets called by 'post_write' (file creation, file update) and 'post_share' hooks
	 * @param \OCP\Files\File $file the file
	 * @param string userId
	 * @param \OCP\Files\Folder $userHome
	 * @param string|null $filePath Deducted from $file if not given
	 */
	public function update($file, $userId, $userHome, $filePath = null){
		if (!$filePath) {
			$filePath = $file->getPath();
		}

		// debug logging
		$this->logger->log("update - $filePath", 'debug');

		if(!($file instanceof \OCP\Files\File) || !$userId || !($userHome instanceof \OCP\Files\Folder)) {
			$this->logger->log('Invalid arguments given to Scanner.update - file='.get_class($file).
					", userId=$userId, userHome=".get_class($userHome), 'warn');
			return;
		}

		// skip files that aren't inside the user specified path
		$musicFolder = $this->getUserMusicFolder($userId, $userHome);
		$musicPath = $musicFolder->getPath();
		if(!self::startsWith($filePath, $musicPath)) {
			$this->logger->log("skipped - file is outside of specified path $musicPath", 'debug');
			return;
		}

		$mimetype = $file->getMimeType();

		// debug logging
		$this->logger->log("update - mimetype $mimetype", 'debug');
		$this->emit('\OCA\Music\Utility\Scanner', 'update', array($filePath));

		if(self::startsWith($mimetype, 'image')) {
			$this->updateImage($file, $userId);
		}
		else if(self::startsWith($mimetype, 'audio') || self::startsWith($mimetype, 'application/ogg')) {
			$this->updateAudio($file, $userId, $userHome, $filePath, $mimetype);
		}
	}

	private function updateImage($file, $userId) {
		$coverFileId = $file->getId();
		$parentFolderId = $file->getParent()->getId();
		if ($this->albumBusinessLayer->updateFolderCover($coverFileId, $parentFolderId)) {
			$this->logger->log('updateImage - the image was set as cover for some album(s)', 'debug');
			$this->cache->remove($userId);
		}
	}

	private function updateAudio($file, $userId, $userHome, $filePath, $mimetype) {
		if(ini_get('allow_url_fopen')) {

			$meta = $this->extractMetadata($file, $userHome, $filePath);
			$fileId = $file->getId();

			// debug logging
			$this->logger->log('extracted metadata - ' . json_encode($meta), 'debug');

			// add/update artist and get artist entity
			$artist = $this->artistBusinessLayer->addOrUpdateArtist($meta['artist'], $userId);
			$artistId = $artist->getId();

			// add/update albumArtist and get artist entity
			$albumArtist = $this->artistBusinessLayer->addOrUpdateArtist($meta['albumArtist'], $userId);
			$albumArtistId = $albumArtist->getId();

			// add/update album and get album entity
			$album = $this->albumBusinessLayer->addOrUpdateAlbum(
					$meta['album'], $meta['year'], $meta['discNumber'], $albumArtistId, $userId);
			$albumId = $album->getId();

			// add/update track and get track entity
			$track = $this->trackBusinessLayer->addOrUpdateTrack($meta['title'], $meta['trackNumber'],
					$artistId, $albumId, $fileId, $mimetype, $userId, $meta['length'], $meta['bitrate']);

			// if present, use the embedded album art as cover for the respective album
			if($meta['picture'] != null) {
				$this->albumBusinessLayer->setCover($fileId, $albumId);
			}
			// if this file is an existing file which previously was used as cover for an album but now
			// the file no longer contains any embedded album art
			else if($this->albumBusinessLayer->fileIsCoverForAlbum($fileId, $albumId)) {
				$this->albumBusinessLayer->removeCover($fileId);
				$this->findEmbeddedCoverForAlbum($albumId, $userId, $userHome);
			}

			// invalidate the cache as the music collection was changed
			$this->cache->remove($userId);
		
			// debug logging
			$this->logger->log('imported entities - ' .
					"artist: $artistId, albumArtist: $albumArtistId, album: $albumId, track: {$track->getId()}",
					'debug');
		}
	}

	private function extractMetadata($file, $userHome, $filePath) {
		$fieldsFromFileName = self::parseFileName($file->getName());
		$fileInfo = $this->extractor->extract($file);
		$meta = [];

		// Track artist and album artist
		$meta['artist'] = self::getId3Tag($fileInfo, 'artist');
		$meta['albumArtist'] = self::getFirstOfId3Tags($fileInfo, ['band', 'albumartist', 'album artist', 'album_artist']);

		// use artist and albumArtist as fallbacks for each other
		if(self::isNullOrEmpty($meta['albumArtist'])){
			$meta['albumArtist'] = $meta['artist'];
		}

		if(self::isNullOrEmpty($meta['artist'])){
			$meta['artist'] = $meta['albumArtist'];
		}

		// set 'Unknown Artist' in case neither artist nor albumArtist was found
		if(self::isNullOrEmpty($meta['artist'])){
			$meta['artist'] = null;
			$meta['albumArtist'] = null;
		}

		// title
		$meta['title'] = self::getId3Tag($fileInfo, 'title');
		if(self::isNullOrEmpty($meta['title'])){
			$meta['title'] = $fieldsFromFileName['title'];
		}

		// album
		$meta['album'] = self::getId3Tag($fileInfo, 'album');
		if(self::isNullOrEmpty($meta['album'])){
			// album name not set in fileinfo, use parent folder name as album name unless it is the root folder
			$dirPath = dirname($filePath);
			if ($userHome->getPath() === $dirPath) {
				$meta['album'] = null;
			} else {
				$meta['album'] = basename($dirPath);
			}
		}

		// track number
		$meta['trackNumber'] = self::getFirstOfId3Tags($fileInfo, ['track_number', 'tracknumber', 'track'],
				$fieldsFromFileName['track_number']);
		$meta['trackNumber'] = self::normalizeOrdinal($meta['trackNumber']);

		// disc number
		$meta['discNumber'] = self::getFirstOfId3Tags($fileInfo, ['discnumber', 'part_of_a_set'], '1');
		$meta['discNumber'] = self::normalizeOrdinal($meta['discNumber']);

		// year
		$meta['year'] = self::getFirstOfId3Tags($fileInfo, ['year', 'date']);
		$meta['year'] = self::normalizeYear($meta['year']);

		$meta['picture'] = self::getId3Tag($fileInfo, 'picture');

		if (array_key_exists('playtime_seconds', $fileInfo)) {
			$meta['length'] = ceil($fileInfo['playtime_seconds']);
		} else {
			$meta['length'] = null;
		}

		if (array_key_exists('audio', $fileInfo) && array_key_exists('bitrate', $fileInfo['audio'])) {
			$meta['bitrate'] = $fileInfo['audio']['bitrate'];
		} else {
			$meta['bitrate'] = null;
		}

		return $meta;
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
	 * @param string|null $userId the id of the user to remove the file from; if omitted,
	 *                            the file is removed from all users (ie. owner and sharees)
	 */
	public function delete($fileId, $userId=null){
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
				if ($this->albumBusinessLayer->fileIsCoverForAlbum($fileId, $albumId)) {
					$affectedUsers = $this->albumBusinessLayer->removeCover($fileId, $userId);
					foreach ($affectedUsers as $affectedUser) {
						$this->findEmbeddedCoverForAlbum($albumId, $affectedUser, $this->resolveUserFolder($affectedUser));
					}
				}
			}

			// invalidate the cache of all affected users as their music collections were changed
			foreach ($result['affectedUsers'] as $affectedUser) {
				$this->cache->remove($affectedUser);
			}

			// debug logging
			$this->logger->log('removed entities - ' . json_encode($result), 'debug');
		}
		// maybe this was an image file
		else if ($affectedUsers = $this->albumBusinessLayer->removeCover($fileId, $userId)) {
			foreach ($affectedUsers as $affectedUser) {
				$this->findEmbeddedCoverForAlbum($albumId, $affectedUser, $this->resolveUserFolder($affectedUser));
			}
		}
	}

	/**
	 * Remove all audio files in the given folder from the database.
	 * This gets called when a folder is deleted or unshared from the user.
	 * 
	 * @param \OCP\Files\Folder $folder
	 * @param string|null $userId the id of the user to remove the file from; if omitted,
	 *                            the file is removed from all users (ie. owner and sharees)
	 */
	public function deleteFolder($folder, $userId=null) {
		$filesToHandle = array_merge(
				$folder->searchByMime('audio'),
				$folder->searchByMime('application/ogg')
		);
		foreach ($filesToHandle as $file) {
			$this->delete($file->getId(), $userId);
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
	 * @return \OCP\Files\File[]
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

	public function resolveUserFolder($userId) {
		$dir = '/' . $userId;
		$root = $this->rootFolder;

		// copy of getUserServer of server container
		$folder = null;

		if (!$root->nodeExists($dir)) {
			$folder = $root->newFolder($dir);
		} else {
			$folder = $root->get($dir);
		}

		$dir = '/files';
		if (!$folder->nodeExists($dir)) {
			$folder = $folder->newFolder($dir);
		} else {
			$folder = $folder->get($dir);
		}
	
		return $folder;
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

	/**
	 * Loop through the tracks of an album and set the first track containing embedded cover art
	 * as cover file for the album
	 * @param int $albumId
	 * @param string $userId
	 * @param Node $userFolder
	 */
	private function findEmbeddedCoverForAlbum($albumId, $userId, $userFolder) {
		$tracks = $this->trackBusinessLayer->findAllByAlbum($albumId, $userId);
		foreach ($tracks as $track) {
			$nodes = $userFolder->getById($track->getFileId());
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
