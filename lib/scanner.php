<?php

/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OCA\Media;

//class for scanning directories for music
class Scanner {
	/**
	 * @var Extractor $extractor
	 */
	private $extractor;

	/**
	 * @var Collection $collection
	 */
	private $collection;

	/**
	 * @param Collection $collection
	 */
	public function __construct($collection) {
		$this->collection = $collection;
		$this->extractor = new Extractor_GetID3();
	}

	/**
	 * get a list of all music files of the user
	 *
	 * @return array
	 */
	public function getMusic() {
		$music = \OC\Files\Filesystem::searchByMime('audio');
		$ogg = \OC\Files\Filesystem::searchByMime('application/ogg');
		$music = array_merge($music, $ogg);
		foreach ($music as &$file) {
			$file = $file['path'];
		}
		return $music;
	}

	/**
	 * scan all music for the current user
	 *
	 * @return int the number of songs found
	 */
	public function scanCollection() {
		$music = $this->getMusic();
		\OC_Hook::emit('media', 'song_count', array('count' => count($music)));
		$songs = 0;
		foreach ($music as $file) {
			$this->scanFile($file);
			$songs++;
			\OC_Hook::emit('media', 'song_scanned', array('path' => $file, 'count' => $songs));
		}
		return $songs;
	}

	/**
	 * scan a file for music
	 *
	 * @param string $path
	 * @return boolean
	 */
	public function scanFile($path) {
		$mimeType = \OC\Files\Filesystem::getMimeType($path);
		if ($mimeType === 'application/ogg' or substr($mimeType, 0, 5) === 'audio') {
			$data = $this->extractor->extract($path);
			if (!empty($data)) {
				$artistId = $this->collection->addArtist($data['artist']);
				$albumId = $this->collection->addAlbum($data['album'], $artistId);

				$this->collection->addSong($data['title'], $path, $artistId, $albumId, $data['length'], $data['track'], $data['size']);
			}
		}
		return true;
	}
}
