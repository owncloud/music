<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2020
 */

namespace OCA\Music\Utility;

use \OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use \OCA\Music\AppFramework\Core\Logger;
use \OCA\Music\BusinessLayer\PlaylistBusinessLayer;
use \OCA\Music\BusinessLayer\TrackBusinessLayer;
use \OCA\Music\Db\Track;

use \OCP\Files\File;
use \OCP\Files\Folder;

/**
 * Class responsible of exporting playlists to file and importing playlist
 * contents from file.
 */
class PlaylistFileService {
	private $playlistBusinessLayer;
	private $trackBusinessLayer;
	private $userFolder;
	private $userId;
	private $logger;

	public function __construct(
			PlaylistBusinessLayer $playlistBusinessLayer,
			TrackBusinessLayer $trackBusinessLayer,
			Folder $userFolder,
			$userId,
			Logger $logger) {
		$this->playlistBusinessLayer = $playlistBusinessLayer;
		$this->trackBusinessLayer = $trackBusinessLayer;
		$this->userFolder = $userFolder;
		$this->userId = $userId;
		$this->logger = $logger;
	}

	/**
	 * export the playlist to a file
	 * @param int $id playlist ID
	 * @param string $folderPath parent folder path
	 * @param string $collisionMode action to take on file name collision,
	 *								supported values:
	 *								- 'overwrite' The existing file will be overwritten
	 *								- 'keepboth' The new file is named with a suffix to make it unique
	 *								- 'abort' (default) The operation will fail
	 * @return string path of the written file
	 * @throws BusinessLayerException if playlist with ID not found
	 * @throws \OCP\Files\NotFoundException if the $folderPath is not a valid folder
	 * @throws \RuntimeException on name conflict if $collisionMode == 'abort'
	 * @throws \OCP\Files\NotPermittedException if the user is not allowed to write to the given folder
	 */
	public function exportToFile($id, $folderPath, $collisionMode) {
		$playlist = $this->playlistBusinessLayer->find($id, $this->userId);
		$tracks = $this->playlistBusinessLayer->getPlaylistTracks($id, $this->userId);
		$targetFolder = Util::getFolderFromRelativePath($this->userFolder, $folderPath);

		// Name the file according the playlist. File names cannot contain the '/' character on Linux, and in
		// owncloud/Nextcloud, the whole name must fit 250 characters, including the file extension. Reserve
		// another 5 characters to fit the postfix like " (xx)" on name collisions. If there are more than 100
		// exports of the same playlist with overly long name, then this function will fail but we can live
		// with that :).
		$filename = \str_replace('/', '-', $playlist->getName());
		$filename = Util::truncate($filename, 250 - 5 - 5);
		$filename .= '.m3u8';

		if ($targetFolder->nodeExists($filename)) {
			switch ($collisionMode) {
				case 'overwrite':
					$targetFolder->get($filename)->delete();
					break;
				case 'keepboth':
					$filename = $targetFolder->getNonExistingName($filename);
					break;
				default:
					throw new \RuntimeException('file already exists');
			}
		}

		$content = "#EXTM3U\n#EXTENC: UTF-8\n";
		foreach ($tracks as $track) {
			$nodes = $this->userFolder->getById($track->getFileId());
			if (\count($nodes) > 0) {
				$caption = self::captionForTrack($track);
				$content .= "#EXTINF:{$track->getLength()},$caption\n";
				$content .= Util::relativePath($targetFolder->getPath(), $nodes[0]->getPath()) . "\n";
			}
		}
		$file = $targetFolder->newFile($filename);
		$file->putContent($content);

		return $this->userFolder->getRelativePath($file->getPath());
	}
	
	/**
	 * export the playlist to a file
	 * @param int $id playlist ID
	 * @param string $filePath path of the file to import
	 * @return array with three keys:
	 * 			- 'playlist': The Playlist entity after the modification
	 * 			- 'imported_count': An integer showing the number of tracks imported
	 * 			- 'failed_count': An integer showing the number of tracks in the file which could not be imported
	 * @throws BusinessLayerException if playlist with ID not found
	 * @throws \OCP\Files\NotFoundException if the $filePath is not a valid file
	 * @throws \UnexpectedValueException if the $filePath points to a file of unsupported type
	 */
	public function importFromFile($id, $filePath) {
		$parsed = $this->doParseFile($this->userFolder->get($filePath));
		$trackFilesAndCaptions = $parsed['files'];
		$invalidPaths = $parsed['invalid_paths'];

		$trackIds = [];
		foreach ($trackFilesAndCaptions as $trackFileAndCaption) {
			$trackFile = $trackFileAndCaption['file'];
			if ($track = $this->trackBusinessLayer->findByFileId($trackFile->getId(), $this->userId)) {
				$trackIds[] = $track->getId();
			} else {
				$invalidPaths[] = $trackFile->getPath();
			}
		}

		$playlist = $this->playlistBusinessLayer->addTracks($trackIds, $id, $this->userId);

		if (\count($invalidPaths) > 0) {
			$this->logger->log('Some files were not found from the user\'s music library: '
								. \json_encode($invalidPaths, JSON_PARTIAL_OUTPUT_ON_ERROR), 'warn');
		}

		return [
			'playlist' => $playlist,
			'imported_count' => \count($trackIds),
			'failed_count' => \count($invalidPaths)
		];
	}

	/**
	 * 
	 * @param int $fileId
	 * @throws \OCP\Files\NotFoundException if the $filePath is not a valid file
	 * @throws \UnexpectedValueException if the $filePath points to a file of unsupported type
	 * @return array
	 */
	public function parseFile($fileId) {
		$nodes = $this->userFolder->getById($fileId);
		if (\count($nodes) > 0) {
			return $this->doParseFile($nodes[0]);
		} else {
			throw new \OCP\Files\NotFoundException();
		}
	}

	private function doParseFile(File $file) {
		$mime = $file->getMimeType();

		if ($mime == 'audio/mpegurl') {
			$entries = $this->parseM3uFile($file);
		} elseif ($mime == 'audio/x-scpls') {
			$entries = $this->parsePlsFile($file);
		} else {
			throw new \UnexpectedValueException("file mime type '$mime' is not suported");
		}

		// find the parsed entries from the file system
		$trackFiles = [];
		$invalidPaths = [];
		$cwd = $this->userFolder->getRelativePath($file->getParent()->getPath());

		foreach ($entries as $entry) {
			$path = Util::resolveRelativePath($cwd, $entry['path']);
			try {
				$trackFiles[] = [
					'file' => $this->userFolder->get($path),
					'caption' => $entry['caption']
				];
			} catch (\OCP\Files\NotFoundException $ex) {
				$invalidPaths[] = $path;
			}
		}

		return [
			'files' => $trackFiles,
			'invalid_paths' => $invalidPaths
		];
	}

	private function parseM3uFile(File $file) {
		$entries = [];

		// By default, files with extension .m3u8 are interpreted as UTF-8 and files with extension
		// .m3u as ISO-8859-1. These can be overridden with the tag '#EXTENC' in the file contents.
		$encoding = Util::endsWith($file->getPath(), '.m3u8', /*ignoreCase=*/true) ? 'UTF-8' : 'ISO-8859-1';

		$caption = null;

		$fp = $file->fopen('r');
		while ($line = \fgets($fp)) {
			$line = \mb_convert_encoding($line, \mb_internal_encoding(), $encoding);
			$line = \trim($line);
			if (Util::startsWith($line, '#')) {
				// comment or extended fromat attribute line
				if ($value = self::extractExtM3uField($line, 'EXTENC')) {
					// update the used encoding with the explicitly defined one
					$encoding = $value;
				}
				elseif ($value = self::extractExtM3uField($line, 'EXTINF')) {
					// The format should be "length,caption". Set caption to null if the field is badly formatted.
					$parts = \explode(',', $value, 2);
					$caption = \trim(Util::arrayGetOrDefault($parts, 1));
				}
			}
			else {
				$entries[] = [
					'path' => $line,
					'caption' => $caption
				];
				$caption = null; // the caption has been used up
			}
		}
		\fclose($fp);

		return $entries;
	}

	private function parsePlsFile(File $file) {
		$files = [];
		$titles = [];

		$content = $file->getContent();

		// If the file doesn't seem to be UTF-8, then assume it to be ISO-8859-1
		if (!\mb_check_encoding($content, 'UTF-8')) {
			$content = \mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
		}

		$fp = \fopen("php://temp", 'r+');
		\fputs($fp, $content);
		\rewind($fp);

		// the first line should always be [playlist]
		if (\trim(\fgets($fp)) != '[playlist]') {
			throw new \UnexpectedValueException('the file is not in valid PLS format');
		}

		// the rest of the non-empty lines should be in format "key=value"
		while ($line = \fgets($fp)) {
			$line = \trim($line);
			// ignore empty and malformed lines
			if (\strpos($line, '=') !== false) {
				list($key, $value) = \explode('=', $line, 2);
				// we are interested only on the File# and Title# lines
				if (Util::startsWith($key, 'File')) {
					$idx = \substr($key, \strlen('File'));
					$files[$idx] = $value;
				}
				elseif (Util::startsWith($key, 'Title')) {
					$idx = \substr($key, \strlen('Title'));
					$titles[$idx] = $value;
				}
			}
		}
		\fclose($fp);

		$entries = [];
		foreach ($files as $idx => $file) {
			$entries[] = [
				'path' => $file,
				'caption' => Util::arrayGetOrDefault($titles, $idx)
			];
		}

		return $entries;
	}

	private static function captionForTrack(Track $track) {
		$title = $track->getTitle();
		$artist = $track->getArtistName();

		return empty($artist) ? $title : "$artist - $title";
	}

	private static function extractExtM3uField($line, $field) {
		if (Util::startsWith($line, "#$field:")) {
			return \trim(\substr($line, \strlen("#$field:")));
		} else {
			return null;
		}
	}
}
