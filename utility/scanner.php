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

use \OCA\Music\AppFramework\Core\Db;
use \OCA\Music\AppFramework\Core\Logger;

use \OCA\Music\BusinessLayer\ArtistBusinessLayer;
use \OCA\Music\BusinessLayer\AlbumBusinessLayer;
use \OCA\Music\BusinessLayer\TrackBusinessLayer;



class Scanner extends PublicEmitter {

	private $extractor;
	private $artistBusinessLayer;
	private $albumBusinessLayer;
	private $trackBusinessLayer;
	private $logger;
	private $db;
	private $userId;
	private $configManager;
	private $appName;
	private $userFolder;

	public function __construct(Extractor $extractor,
								ArtistBusinessLayer $artistBusinessLayer,
								AlbumBusinessLayer $albumBusinessLayer,
								TrackBusinessLayer $trackBusinessLayer,
								Logger $logger,
								Db $db,
								$userId,
								IConfig $configManager,
								$appName,
								Folder $userFolder = null){
		$this->extractor = $extractor;
		$this->artistBusinessLayer = $artistBusinessLayer;
		$this->albumBusinessLayer = $albumBusinessLayer;
		$this->trackBusinessLayer = $trackBusinessLayer;
		$this->logger = $logger;
		$this->db = $db;
		$this->userId = $userId;
		$this->configManager = $configManager;
		$this->appName = $appName;
		$this->userFolder = $userFolder;

		// Trying to enable stream support
		if(ini_get('allow_url_fopen') !== '1') {
			$this->logger->log('allow_url_fopen is disabled. It is strongly advised to enable it in your php.ini', 'warn');
			@ini_set('allow_url_fopen', '1');
		}
	}

	public function updateById($fileId, $userId = null) {
		try {
			$files = $this->userFolder->getById($fileId);
			if(count($files) > 0) {
				// use first result
				$this->update($files[0], $userId);
			}
		} catch (\OCP\Files\NotFoundException $e) {
			// just ignore the error
			$this->logger->log('updateById - file not found - '. $fileId , 'debug');
		}
	}

	public function updateByPath($filePath, $userId = null) {
		try {
			$file = $this->userFolder->get($filePath);
			$this->update($file, $userId);
		} catch (\OCP\Files\NotFoundException $e) {
			// just ignore the error
			$this->logger->log('updateByPath - file not found - '. $filePath , 'debug');
		}
	}

	/**
	 * Get called by 'post_write' hook (file creation, file update)
	 * @param \OCP\Files\Node $file the file
	 */
	public function update($file, $userId){
		// debug logging
		$this->logger->log('update - '. $file->getPath() , 'debug');

		if(!($file instanceof \OCP\Files\File)) {
			return;
		}

		$mimetype = $file->getMimeType();

		// debug logging
		$this->logger->log('update - mimetype '. $mimetype , 'debug');
		$this->emit('\OCA\Music\Utility\Scanner', 'update', array($file->getPath()));

		if(substr($mimetype, 0, 5) === 'image') {
			$coverFileId = $file->getId();
			$parentFolderId = $file->getParent()->getId();
			$this->albumBusinessLayer->updateCover($coverFileId, $parentFolderId);
			return;
		}

		if(substr($mimetype, 0, 5) !== 'audio' && substr($mimetype, 0, 15) !== 'application/ogg' ) {
			return;
		}

		if(ini_get('allow_url_fopen')) {
			// TODO find a way to get this for a sharee
			$isSharee = $userId && $this->userId !== $userId;

			$musicPath = $this->configManager->getUserValue($this->userId, $this->appName, 'path');
			if($musicPath !== null || $musicPath !== '/' || $musicPath !== '') {
				// TODO verify
				$musicPath = $this->userFolder->get($musicPath)->getPath();
				// skip files that aren't inside the user specified path (and also for sharees - TODO remove this)
				if(!$isSharee && substr($file->getPath(), 0, strlen($musicPath)) !== $musicPath) {
					$this->logger->log('skipped - outside of specified path' , 'debug');
					return;
				}
			}


			$fileInfo = $this->extractor->extract('oc://' . $file->getPath());

			$hasComments = array_key_exists('comments', $fileInfo);

			if(!$hasComments) {
				// TODO: fix this dirty fallback
				// fallback to local file path removed
				$this->logger->log('fallback metadata extraction - removed code', 'debug');
				// $fileInfo = $this->extractor->extract($this->api->getLocalFilePath($path));
				// $hasComments = array_key_exists('comments', $fileInfo);
			}

			// artist
			$artist = null;
			if($hasComments && array_key_exists('artist', $fileInfo['comments'])){
				$artist = $fileInfo['comments']['artist'][0];
				if(count($fileInfo['comments']['artist']) > 1) {
					$this->logger->log('multiple artists found (use shortest): ' . implode(', ', $fileInfo['comments']['artist']), 'debug');
					// determine shortest, because the longer names are just concatenations of all artists
					for($i=0; $i < count($fileInfo['comments']['artist']); $i++){
						if(strlen($fileInfo['comments']['artist'][$i]) < strlen($artist)) {
							$artist = $fileInfo['comments']['artist'][$i];
						}
					}

				}
			}
			if($artist === ''){
				// assume artist is not set
				$artist = null;
			}

			$alternativeTrackNumber = null;
			// title
			$title = null;
			if($hasComments && array_key_exists('title', $fileInfo['comments'])){
				$title = $fileInfo['comments']['title'][0];
			}
			if($title === null || $title === ''){
				// fallback to file name
				$title = $file->getName();
				if(preg_match('/^(\d+)\W*[.-]\W*(.*)/', $title, $matches) === 1) {
					$alternativeTrackNumber = $matches[1];
					if(preg_match('/(.*)(\.(mp3|ogg))$/', $matches[2], $titleMatches) === 1) {
						$title = $titleMatches[1];
					} else {
						$title = $matches[2];
					}
				}
			}

			// album
			$album = null;
			if($hasComments && array_key_exists('album', $fileInfo['comments'])){
				$album = $fileInfo['comments']['album'][0];
			}
			if($album === ''){
				// assume album is not set
				$album = null;
			}

			// track number
			$trackNumber = null;
			if($hasComments && array_key_exists('track_number', $fileInfo['comments'])){
				$trackNumber = $fileInfo['comments']['track_number'][0];
			}
			if($trackNumber === null && $alternativeTrackNumber !== null) {
				$trackNumber = $alternativeTrackNumber;
			}
			// convert track number '1/10' to '1'
			$tmp = explode('/', $trackNumber);
			$trackNumber = $tmp[0];

			// check for numeric values - cast them to int and verify it's a natural number above 0
			if(is_numeric($trackNumber) && ((int)$trackNumber) > 0) {
				$trackNumber = (int)$trackNumber;
			} else {
				$trackNumber = null;
			}

			$year = null;
			if($hasComments && array_key_exists('year', $fileInfo['comments'])){
				$year = $fileInfo['comments']['year'][0];
				if(!ctype_digit($year)) {
					$year = null;
				}

			}
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
				sprintf('artist: %s, album: %s, title: %s, track#: %s, year: %s, mimetype: %s, length: %s, bitrate: %s, fileId: %i, this->userId: %s, userId: %s',
					$artist, $album, $title, $trackNumber, $year, $mimetype, $length, $bitrate, $fileId, $this->userId, $userId), 'debug');

			if(!$userId) {
				$userId = $this->userId;
			}

			// add artist and get artist entity
			$artist = $this->artistBusinessLayer->addArtistIfNotExist($artist, $userId);
			$artistId = $artist->getId();

			// add album and get album entity
			$album = $this->albumBusinessLayer->addAlbumIfNotExist($album, $year, $artistId, $userId);
			$albumId = $album->getId();

			// add track and get track entity
			$track = $this->trackBusinessLayer->addTrackIfNotExist($title, $trackNumber, $artistId,
				$albumId, $fileId, $mimetype, $userId, $length, $bitrate);

			// debug logging
			$this->logger->log('imported entities - ' .
				sprintf('artist: %d, album: %d, track: %d', $artistId, $albumId, $track->getId()),
				'debug');
		}

	}

	/**
	 * Get called by 'delete' hook (file deletion)
	 * @param string $path the path of the file
	 */
	public function deleteByPath($path){
		try {
			$fileId = $this->userFolder->get($path)->getId();
			$this->delete($fileId);
		} catch (\OCP\Files\NotFoundException $e) {
			// just ignore the error
			$this->logger->log('delete - file not found - '. $path , 'debug');
		}
	}
	/**
	 * Get called by 'unshare' hook and 'deleteByPath'
	 * @param int $fileId the file id of the track
	 * @param string $userId the user id of the user to delete the track from
	 */
	public function delete($fileId, $userId = null){
		// debug logging
		$this->logger->log('delete - '. $fileId , 'debug');
		$this->emit('\OCA\Music\Utility\Scanner', 'delete', array($fileId, $userId));

		if ($userId === null) {
			$userId = $this->userId;
		}

		$remaining = $this->trackBusinessLayer->deleteTrack($fileId, $userId);

		$this->albumBusinessLayer->deleteById($remaining['albumIds']);
		$this->artistBusinessLayer->deleteById($remaining['artistIds']);

		// debug logging
		$this->logger->log('removed entities - albums: [' . implode(',', $remaining ['albumIds']) .
			'], artists: [' . implode(',', $remaining['artistIds']) . ']' , 'debug');

		$this->albumBusinessLayer->removeCover($fileId);
	}

	/**
	 * search for files by mimetype inside an optional user specified path
	 *
	 * @return \OCP\Files\Node[]
	 */
	public function getMusicFiles() {
		$musicPath = $this->configManager->getUserValue($this->userId, $this->appName, 'path');

		$folder = $this->userFolder;
		if($musicPath !== null && $musicPath !== '/' && $musicPath !== '') {
			try {
				$folder = $this->userFolder->get($musicPath);
			} catch (\OCP\Files\NotFoundException $e) {
				return array();
			}
		}

		$audio = $folder->searchByMime('audio');
		$ogg = $folder->searchByMime('application/ogg');

		return array_merge($audio, $ogg);
	}

	public function getScannedFiles($userId = NULL) {
		$sql = 'SELECT `file_id` FROM `*PREFIX*music_tracks`';
		$params = array();
		if($userId) {
			$sql .= ' WHERE `user_id` = ?';
			$params = array($userId);
		}
		$query = $this->db->prepareQuery($sql);
		$result = $query->execute($params);
		$fileIds = array_map(function($i) { return $i['file_id']; }, $result->fetchAll());

		return $fileIds;
	}

	/**
	 * Rescan the whole file base for new files
	 */
	public function rescan($userId = null, $batch = false, $userHome = null, $debug = false, $output = null) {
		$this->logger->log('Rescan triggered', 'info');

		if($userHome !== null){
			// $userHome can be injected by batch scan process
			$this->userFolder = $userHome;
		}

		// get execution time limit
		$executionTime = intval(ini_get('max_execution_time'));
		// set execution time limit to unlimited
		set_time_limit(0);

		$fileIds = $this->getScannedFiles($userId);
		$music = $this->getMusicFiles();

		$count = 0;
		foreach ($music as $file) {
			if(!$batch && $count >= 20) {
				// break scan - 20 files are already scanned
				break;
			}
			if(in_array($file->getId(), $fileIds)) {
				// skip this file as it's already scanned
				continue;
			}
			if($debug) {
				$before = memory_get_usage(true);
			}
			$this->update($file, $userId);
			if($debug && $output) {
				$after = memory_get_usage(true);
				$diff = $after - $before;
				$afterFileSize = new FileSize($after);
				$diffFileSize = new FileSize($diff);
				$humanFilesizeAfter = $afterFileSize->getHumanReadable();
				$humanFilesizeDiff = $diffFileSize->getHumanReadable();
				$path = $file->getPath();
				$output->writeln("\e[1m $count \e[0m $humanFilesizeAfter \e[1m $diff \e[0m ($humanFilesizeDiff) $path");
			}
			$count++;
		}
		// find album covers
		$this->albumBusinessLayer->findCovers();

		// reset execution time limit
		set_time_limit($executionTime);

		$totalCount = count($music);
		$processedCount = $count;
		if(!$batch) {
			$processedCount += count($fileIds);
		}
		$this->logger->log(sprintf('Rescan finished (%d/%d)', $processedCount, $totalCount), 'info');

		return array('processed' => $processedCount, 'scanned' => $count, 'total' => $totalCount);
	}

	/**
	 * Update music path
	 */
	public function updatePath($path, $userId = null) {
		// TODO currently this function is quite dumb
		// it just drops all entries of an user from the tables
		if ($userId === null) {
			$userId = $this->userId;
		}

		$sqls = array(
			'DELETE FROM `*PREFIX*music_tracks` WHERE `user_id` = ?;',
			'DELETE FROM `*PREFIX*music_albums` WHERE `user_id` = ?;',
			'DELETE FROM `*PREFIX*music_artists` WHERE `user_id` = ?;'
		);

		foreach ($sqls as $sql) {
			$query = $this->db->prepareQuery($sql);
			$query->execute(array($userId));
		}

		$query = $this->db->prepareQuery('DELETE FROM `*PREFIX*music_album_artists` WHERE `album_id` NOT IN (SELECT `id` FROM `*PREFIX*music_albums` GROUP BY `id`);');
		$query->execute();
	}
}
