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

use \OCP\Files\Folder;
use \OCP\IConfig;

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

		$filename = \str_replace('/', '-', $playlist->getName()) . '.m3u8';

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
				$content .= "#EXTINF:{$track->getLength()},{$track->getTitle()}\n";
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
	 */
	public function importFromFile($id, $filePath) {
		$file = $this->userFolder->get($filePath);

		$trackIds = [];
		$failedCount = 0;

		$cwd = \dirname($filePath);
		$fp = $file->fopen('r');
		while ($line = \fgets($fp)) {
			$line = \trim($line);
			if (Util::startsWith($line, '#')) {
				// comment line
			} else {
				$path = Util::resolveRelativePath($cwd, $line);
				try {
					$trackFile = $this->userFolder->get($path);
					if ($track = $this->trackBusinessLayer->findByFileId($trackFile->getId(), $this->userId)) {
						$trackIds[] = $track->getId();
					} else {
						$failedCount++;
					}
				}
				catch (\OCP\Files\NotFoundException $ex) {
					$failedCount++;
				}
			}
		}
		\fclose($fp);

		$playlist = $this->playlistBusinessLayer->addTracks($trackIds, $id, $this->userId);

		return [
			'playlist' => $playlist,
			'imported_count' => \count($trackIds),
			'failed_count' => $failedCount
		];
	}	
}
