<?php

/**
 * ownCloud - Music app
 *
 * @author Morris Jobke
 * @copyright 2013 Morris Jobke <morris.jobke@gmail.com>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Music\Utility;

use \OCA\Music\BusinessLayer\ArtistBusinessLayer;
use \OCA\Music\BusinessLayer\AlbumBusinessLayer;
use \OCA\Music\BusinessLayer\TrackBusinessLayer;

use \OCA\Music\AppFramework\Core\API;
use OC\Hooks\PublicEmitter;

class Scanner extends PublicEmitter {

	private $api;
	private $extractor;
	private $artistBusinessLayer;
	private $albumBusinessLayer;
	private $trackBusinessLayer;

	public function __construct(API $api, Extractor $extractor, ArtistBusinessLayer $artistBusinessLayer,
		AlbumBusinessLayer $albumBusinessLayer, TrackBusinessLayer $trackBusinessLayer){
		$this->api = $api;
		$this->extractor = $extractor;
		$this->artistBusinessLayer = $artistBusinessLayer;
		$this->albumBusinessLayer = $albumBusinessLayer;
		$this->trackBusinessLayer = $trackBusinessLayer;

		// Trying to enable stream support
		if(ini_get('allow_url_fopen') !== '1') {
			$this->api->log('allow_url_fopen is disabled. It is strongly advised to enable it in your php.ini', 'warn');
			@ini_set('allow_url_fopen', '1');
		}
	}

	/**
	 * Get called by 'post_write' hook (file creation, file update)
	 * @param string $path the path of the file
	 */
	public function update($path, $userId = NULL){
		// debug logging
		$this->api->log('update - '. $path , 'debug');

		$metadata = $this->api->getFileInfo($path);

		if($metadata === false) {
			$this->api->log('cannot determine metadata for path ' . $path, 'debug');
			return;
		}

		// debug logging
		$this->api->log('update - mimetype '. $metadata['mimetype'] , 'debug');
		$this->emit('\OCA\Music\Utility\Scanner', 'update', array($path));

		if(substr($metadata['mimetype'], 0, 5) === 'image') {
			$coverFileId = $metadata['fileid'];
			$parentFolderId = $metadata['parent'];
			$this->albumBusinessLayer->updateCover($coverFileId, $parentFolderId);
			return;
		}

		if(substr($metadata['mimetype'], 0, 5) !== 'audio' && substr($metadata['mimetype'], 0, 15) !== 'application/ogg' ) {
			return;
		}

		if(ini_get('allow_url_fopen')) {
			$fileInfo = $this->extractor->extract('oc://' . $this->api->getView()->getAbsolutePath($path));

			$hasComments = array_key_exists('comments', $fileInfo);

			if(!$hasComments) {
				// TODO: fix this dirty fallback
				// fallback to local file path
				$this->api->log('fallback metadata extraction', 'debug');
				$fileInfo = $this->extractor->extract($this->api->getLocalFilePath($path));
				$hasComments = array_key_exists('comments', $fileInfo);
			}

			if (!$userId) $userId = $this->api->getUserId();

			// artist
			$artist = null;
			if($hasComments && array_key_exists('artist', $fileInfo['comments'])){
				$artist = $fileInfo['comments']['artist'][0];
				if(count($fileInfo['comments']['artist']) > 1) {
					$this->api->log('multiple artists found (use shortest): ' . implode(', ', $fileInfo['comments']['artist']), 'debug');
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
				$title = $metadata['name'];
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

			$year = null;
			if($hasComments && array_key_exists('year', $fileInfo['comments'])){
				$year = $fileInfo['comments']['year'][0];
				if(!ctype_digit($year)) {
					$year = null;
				}

			}
			$mimetype = $metadata['mimetype'];
			$fileId = $metadata['fileid'];

			// debug logging
			$this->api->log('extracted metadata - ' .
				sprintf('artist: %s, album: %s, title: %s, track#: %s, year: %s, mimetype: %s, fileId: %i',
					$artist, $album, $title, $trackNumber, $year, $mimetype, $fileId), 'debug');

			// add artist and get artist entity
			$artist = $this->artistBusinessLayer->addArtistIfNotExist($artist, $userId);
			$artistId = $artist->getId();

			// add album and get album entity
			$album = $this->albumBusinessLayer->addAlbumIfNotExist($album, $year, $artistId, $userId);
			$albumId = $album->getId();

			// add track and get track entity
			$track = $this->trackBusinessLayer->addTrackIfNotExist($title, $trackNumber, $artistId,
				$albumId, $fileId, $mimetype, $userId);

			// debug logging
			$this->api->log('imported entities - ' .
				sprintf('artist: %d, album: %d, track: %d', $artistId, $albumId, $track->getId()),
				'debug');
		}

	}

	/**
	 * Get called by 'delete' hook (file deletion)
	 * @param string $path the path of the file
	 */
	public function delete($path){
		// debug logging
		$this->api->log('delete - '. $path , 'debug');
		$this->emit('\OCA\Music\Utility\Scanner', 'delete', array($path));

		$metadata = $this->api->getFileInfo($path);
		$fileId = $metadata['fileid'];
		$userId = $this->api->getUserId();

		$remaining = $this->trackBusinessLayer->deleteTrack($fileId, $userId);

		$this->albumBusinessLayer->deleteById($remaining['albumIds']);
		$this->artistBusinessLayer->deleteById($remaining['artistIds']);

		// debug logging
		$this->api->log('removed entities - albums: [' . implode(',', $remaining ['albumIds']) .
			'], artists: [' . implode(',', $remaining['artistIds']) . ']' , 'debug');

		$this->albumBusinessLayer->removeCover($fileId);
	}

	/**
	 * Rescan the whole file base for new files
	 */
	public function rescan($userId = NULL) {
		// get execution time limit
		$executionTime = intval(ini_get('max_execution_time'));
		// set execution time limit to unlimited
		set_time_limit(0);

		$music = $this->api->searchByMime('audio');
		$ogg = $this->api->searchByMime('application/ogg');
		$music = array_merge($music, $ogg);
		foreach ($music as $file) {
			$this->update($file['path'], $userId);
		}
		// find album covers
		$this->albumBusinessLayer->findCovers();

		// reset execution time limit
		set_time_limit($executionTime);
	}

	/**
	 * Removes orphaned data from the database
	 */
	public function cleanUp() {
		$sqls = array(
			'UPDATE `*PREFIX*music_albums` SET `cover_file_id` = NULL WHERE `cover_file_id` IS NOT NULL AND `cover_file_id` NOT IN (SELECT `fileid` FROM `*PREFIX*filecache`);',
			'DELETE FROM `*PREFIX*music_tracks` WHERE `file_id` NOT IN (SELECT `fileid` FROM `*PREFIX*filecache`);',
			'DELETE FROM `*PREFIX*music_albums` WHERE `id` NOT IN (SELECT `album_id` FROM `*PREFIX*music_tracks` GROUP BY `album_id`);',
			'DELETE FROM `*PREFIX*music_album_artists` WHERE `album_id` NOT IN (SELECT `id` FROM `*PREFIX*music_albums` GROUP BY `id`);',
			'DELETE FROM `*PREFIX*music_artists` WHERE `id` NOT IN (SELECT `artist_id` FROM `*PREFIX*music_album_artists` GROUP BY `artist_id`);'
		);

		foreach ($sqls as $sql) {
			$query = $this->api->prepareQuery($sql);
			$query->execute();
		}
	}
}
