<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2013, 2014
 * @copyright Pauli Järvinen 2016 - 2025
 */

namespace OCA\Music\Service;

use OC\Hooks\PublicEmitter;

use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IL10N;
use OCP\L10N\IFactory;

use OCA\Music\AppFramework\Core\Logger;
use OCA\Music\BusinessLayer\ArtistBusinessLayer;
use OCA\Music\BusinessLayer\AlbumBusinessLayer;
use OCA\Music\BusinessLayer\GenreBusinessLayer;
use OCA\Music\BusinessLayer\PlaylistBusinessLayer;
use OCA\Music\BusinessLayer\TrackBusinessLayer;
use OCA\Music\Db\Cache;
use OCA\Music\Db\Maintenance;
use OCA\Music\Utility\ArrayUtil;
use OCA\Music\Utility\FilesUtil;
use OCA\Music\Utility\StringUtil;
use OCA\Music\Utility\Util;

use Symfony\Component\Console\Output\OutputInterface;

class Scanner extends PublicEmitter {
	private Extractor $extractor;
	private ArtistBusinessLayer $artistBusinessLayer;
	private AlbumBusinessLayer $albumBusinessLayer;
	private TrackBusinessLayer $trackBusinessLayer;
	private PlaylistBusinessLayer $playlistBusinessLayer;
	private GenreBusinessLayer $genreBusinessLayer;
	private Cache $cache;
	private CoverService $coverService;
	private Logger $logger;
	private Maintenance $maintenance;
	private LibrarySettings $librarySettings;
	private IRootFolder $rootFolder;
	private IConfig $config;
	private IFactory $l10nFactory;

	public function __construct(Extractor $extractor,
								ArtistBusinessLayer $artistBusinessLayer,
								AlbumBusinessLayer $albumBusinessLayer,
								TrackBusinessLayer $trackBusinessLayer,
								PlaylistBusinessLayer $playlistBusinessLayer,
								GenreBusinessLayer $genreBusinessLayer,
								Cache $cache,
								CoverService $coverService,
								Logger $logger,
								Maintenance $maintenance,
								LibrarySettings $librarySettings,
								IRootFolder $rootFolder,
								IConfig $config,
								IFactory $l10nFactory) {
		$this->extractor = $extractor;
		$this->artistBusinessLayer = $artistBusinessLayer;
		$this->albumBusinessLayer = $albumBusinessLayer;
		$this->trackBusinessLayer = $trackBusinessLayer;
		$this->playlistBusinessLayer = $playlistBusinessLayer;
		$this->genreBusinessLayer = $genreBusinessLayer;
		$this->cache = $cache;
		$this->coverService = $coverService;
		$this->logger = $logger;
		$this->maintenance = $maintenance;
		$this->librarySettings = $librarySettings;
		$this->rootFolder = $rootFolder;
		$this->config = $config;
		$this->l10nFactory = $l10nFactory;
	}

	/**
	 * Gets called by 'post_write' (file creation, file update) and 'post_share' hooks
	 */
	public function update(File $file, string $userId, string $filePath) : void {
		$mimetype = $file->getMimeType();
		$this->logger->log("update - $filePath - $mimetype", 'debug');

		if (!$this->librarySettings->pathBelongsToMusicLibrary($filePath, $userId)) {
			$this->logger->log("skipped - file is outside of specified music folder", 'debug');
		}
		elseif (StringUtil::startsWith($mimetype, 'image')) {
			$this->updateImage($file, $userId);
		}
		elseif (StringUtil::startsWith($mimetype, 'audio') && !self::isPlaylistMime($mimetype)) {
			$libraryRoot = $this->librarySettings->getFolder($userId);
			$this->updateAudio($file, $userId, $libraryRoot, $filePath, $mimetype, /*partOfScan=*/false);
		}
	}

	public function fileMoved(File $file, string $userId) : void {
		$mimetype = $file->getMimeType();
		$this->logger->log('fileMoved - '. $file->getPath() . " - $mimetype", 'debug');

		if (StringUtil::startsWith($mimetype, 'image')) {
			// we don't need to track the identity of images and moving a file can be handled as it was 
			// a file deletion followed by a file addition
			$this->deleteImage([$file->getId()], [$userId]);
			$this->updateImage($file, $userId);
		}
		elseif (StringUtil::startsWith($mimetype, 'audio') && !self::isPlaylistMime($mimetype)) {
			if ($this->librarySettings->pathBelongsToMusicLibrary($file->getPath(), $userId)) {
				// In the new path, the file (now or still) belongs to the library. Even if it was already in the lib,
				// the new path may have an influence on the album or artist name (in case of incomplete metadata).
				$libraryRoot = $this->librarySettings->getFolder($userId);
				$this->updateAudio($file, $userId, $libraryRoot, $file->getPath(), $mimetype, /*partOfScan=*/false);
			} else {
				// In the new path, the file doesn't (still or any longer) belong to the library. Remove it if
				// it happened to be in the library.
				$this->deleteAudio([$file->getId()], [$userId]);
			}
		}
	}

	public function folderMoved(Folder $folder, string $userId) : void {
		$this->logger->log('folderMoved - '. $folder->getPath(), 'debug');

		$audioFiles = $folder->searchByMime('audio');

		if (\count($audioFiles) > 0) {
			if ($this->librarySettings->pathBelongsToMusicLibrary($folder->getPath(), $userId)) {
				// The new path of the folder belongs to the library but this doesn't necessarily mean
				// that all the file paths below belong to the library, because of the path exclusions.
				// Each file needs to be checked and updated separately.
				if (\count($audioFiles) <= 15) {
					foreach ($audioFiles as $file) {
						\assert($file instanceof File); // a clue for PHPStan
						$this->fileMoved($file, $userId);
					}
				} else {
					// There are too many files to handle them now as we don't want to delay the move operation
					// too much. The user will be prompted to rescan the files upon opening the Music app.
					$this->trackBusinessLayer->markTracksDirty(ArrayUtil::extractIds($audioFiles), [$userId]);
				}
			}
			else {
				// The new path of the folder doesn't belong to the library so neither does any of the
				// contained files. Remove audio files from the lib if found.
				$this->deleteAudio(ArrayUtil::extractIds($audioFiles), [$userId]);
			}
		}
	}

	private static function isPlaylistMime(string $mime) : bool {
		return $mime == 'audio/mpegurl' || $mime == 'audio/x-scpls';
	}

	private function updateImage(File $file, string $userId) : void {
		$coverFileId = $file->getId();
		$parentFolderId = $file->getParent()->getId();
		if ($this->albumBusinessLayer->updateFolderCover($coverFileId, $parentFolderId)) {
			$this->logger->log('updateImage - the image was set as cover for some album(s)', 'debug');
			$this->cache->remove($userId, 'collection');
		}

		$artistIds = $this->artistBusinessLayer->updateCover($file, $userId, $this->userL10N($userId));
		foreach ($artistIds as $artistId) {
			$this->logger->log("updateImage - the image was set as cover for the artist $artistId", 'debug');
			$this->coverService->removeArtistCoverFromCache($artistId, $userId);
		}
	}

	/**
	 * @return array Information about consumed time: ['analyze' => int|float, 'db update' => int|float]
	 */
	private function updateAudio(File $file, string $userId, Folder $libraryRoot, string $filePath, string $mimetype, bool $partOfScan) : array {
		$this->emit(self::class, 'update', [$filePath]);

		$time1 = \hrtime(true);
		$analysisEnabled = $this->librarySettings->getScanMetadataEnabled($userId);
		$meta = $this->extractMetadata($file, $libraryRoot, $filePath, $analysisEnabled);
		$fileId = $file->getId();
		$time2 = \hrtime(true);

		// add/update artist and get artist entity
		$artist = $this->artistBusinessLayer->addOrUpdateArtist($meta['artist'], $userId);
		$artistId = $artist->getId();

		// add/update albumArtist and get artist entity
		$albumArtist = $this->artistBusinessLayer->addOrUpdateArtist($meta['albumArtist'], $userId);
		$albumArtistId = $albumArtist->getId();

		// add/update album and get album entity
		$album = $this->albumBusinessLayer->addOrUpdateAlbum($meta['album'], $albumArtistId, $userId);
		$albumId = $album->getId();

		// add/update genre and get genre entity
		$genre = $this->genreBusinessLayer->addOrUpdateGenre($meta['genre'], $userId);

		// add/update track and get track entity
		$track = $this->trackBusinessLayer->addOrUpdateTrack(
				$meta['title'], $meta['trackNumber'], $meta['discNumber'], $meta['year'], $genre->getId(),
				$artistId, $albumId, $fileId, $mimetype, $userId, $meta['length'], $meta['bitrate']);

		// if present, use the embedded album art as cover for the respective album
		if ($meta['picture'] != null) {
			// during scanning, don't repeatedly change the file providing the art for the album
			if ($album->getCoverFileId() === null || !$partOfScan) {
				$this->albumBusinessLayer->setCover($fileId, $albumId);
				$this->coverService->removeAlbumCoverFromCache($albumId, $userId);
			}
		}
		// if this file is an existing file which previously was used as cover for an album but now
		// the file no longer contains any embedded album art
		elseif ($album->getCoverFileId() === $fileId) {
			$this->albumBusinessLayer->removeCovers([$fileId]);
			$this->findEmbeddedCoverForAlbum($albumId, $userId, $libraryRoot);
			$this->coverService->removeAlbumCoverFromCache($albumId, $userId);
		}
		$time3 = \hrtime(true);

		if (!$partOfScan) {
			// invalidate the cache as the music collection was changed
			$this->cache->remove($userId, 'collection');
		}

		$this->logger->log('imported entities - ' .
				"artist: $artistId, albumArtist: $albumArtistId, album: $albumId, track: {$track->getId()}",
				'debug');

		return [
			'analyze' => $time2 - $time1,
			'db update' => $time3 - $time2
		];
	}

	private function extractMetadata(File $file, Folder $libraryRoot, string $filePath, bool $analyzeFile) : array {
		$fieldsFromFileName = self::parseFileName($file->getName());
		$fileInfo = $analyzeFile ? $this->extractor->extract($file) : [];
		$meta = [];

		// Track artist and album artist
		$meta['artist'] = ExtractorGetID3::getTag($fileInfo, 'artist');
		$meta['albumArtist'] = ExtractorGetID3::getFirstOfTags($fileInfo, ['band', 'albumartist', 'album artist', 'album_artist']);

		// use artist and albumArtist as fallbacks for each other
		if (!StringUtil::isNonEmptyString($meta['albumArtist'])) {
			$meta['albumArtist'] = $meta['artist'];
		}

		if (!StringUtil::isNonEmptyString($meta['artist'])) {
			$meta['artist'] = $meta['albumArtist'];
		}

		if (!StringUtil::isNonEmptyString($meta['artist'])) {
			// neither artist nor albumArtist set in fileinfo, use the second level parent folder name
			// unless it is the user's library root folder
			$dirPath = \dirname(\dirname($filePath));
			if (StringUtil::startsWith($libraryRoot->getPath(), $dirPath)) {
				$artistName = null;
			} else {
				$artistName = \basename($dirPath);
			}

			$meta['artist'] = $artistName;
			$meta['albumArtist'] = $artistName;
		}

		// title
		$meta['title'] = ExtractorGetID3::getTag($fileInfo, 'title');
		if (!StringUtil::isNonEmptyString($meta['title'])) {
			$meta['title'] = $fieldsFromFileName['title'];
		}

		// album
		$meta['album'] = ExtractorGetID3::getTag($fileInfo, 'album');
		if (!StringUtil::isNonEmptyString($meta['album'])) {
			// album name not set in fileinfo, use parent folder name as album name unless it is the user's library root folder
			$dirPath = \dirname($filePath);
			if ($libraryRoot->getPath() === $dirPath) {
				$meta['album'] = null;
			} else {
				$meta['album'] = \basename($dirPath);
			}
		}

		// track number
		$meta['trackNumber'] = ExtractorGetID3::getFirstOfTags($fileInfo, ['track_number', 'tracknumber', 'track'],
				$fieldsFromFileName['track_number']);
		$meta['trackNumber'] = self::normalizeOrdinal($meta['trackNumber']);

		// disc number
		$meta['discNumber'] = ExtractorGetID3::getFirstOfTags($fileInfo, ['disc_number', 'discnumber', 'part_of_a_set'], '1');
		$meta['discNumber'] = self::normalizeOrdinal($meta['discNumber']);

		// year
		$meta['year'] = ExtractorGetID3::getFirstOfTags($fileInfo, ['year', 'date', 'creation_date']);
		$meta['year'] = self::normalizeYear($meta['year']);

		$meta['genre'] = ExtractorGetID3::getTag($fileInfo, 'genre') ?: ''; // empty string used for "scanned but unknown"

		$meta['picture'] = ExtractorGetID3::getTag($fileInfo, 'picture', true);

		$meta['length'] = self::normalizeUnsigned($fileInfo['playtime_seconds'] ?? null);

		$meta['bitrate'] = self::normalizeUnsigned($fileInfo['audio']['bitrate'] ?? null);

		return $meta;
	}

	/**
	 * @param string[] $affectedUsers
	 * @param int[] $affectedAlbums
	 * @param int[] $affectedArtists
	 */
	private function invalidateCacheOnDelete(array $affectedUsers, array $affectedAlbums, array $affectedArtists) : void {
		// Delete may be for one file or for a folder containing thousands of albums.
		// If loads of albums got affected, then ditch the whole cache of the affected
		// users because removing the cached covers one-by-one could delay the delete
		// operation significantly.
		$albumCount = \count($affectedAlbums);
		$artistCount = \count($affectedArtists);
		$userCount = \count($affectedUsers);

		if ($albumCount + $artistCount > 100) {
			$this->logger->log("Delete operation affected $albumCount albums and $artistCount artists. " .
								"Invalidate the whole cache of all affected users ($userCount).", 'debug');
			foreach ($affectedUsers as $user) {
				$this->cache->remove($user);
			}
		} else {
			// remove the cached covers
			if ($artistCount > 0) {
				$this->logger->log("Remove covers of $artistCount artist(s) from the cache (if present)", 'debug');
				foreach ($affectedArtists as $artistId) {
					$this->coverService->removeArtistCoverFromCache($artistId);
				}
			}

			if ($albumCount > 0) {
				$this->logger->log("Remove covers of $albumCount album(s) from the cache (if present)", 'debug');
				foreach ($affectedAlbums as $albumId) {
					$this->coverService->removeAlbumCoverFromCache($albumId);
				}
			}

			// remove the cached collection regardless of if covers were affected; it may be that this
			// function got called after a track was deleted and there are no album/artist changes
			foreach ($affectedUsers as $user) {
				$this->cache->remove($user, 'collection');
			}
		}
	}

	/**
	 * @param int[] $fileIds
	 * @param string[]|null $userIds
	 * @return boolean true if anything was removed
	 */
	private function deleteAudio(array $fileIds, ?array $userIds=null) : bool {
		$this->logger->log('deleteAudio - '. \implode(', ', $fileIds), 'debug');

		$result = $this->trackBusinessLayer->deleteTracks($fileIds, $userIds);

		if ($result) { // one or more tracks were removed
			// remove obsolete artists and albums, and track references in playlists
			$this->albumBusinessLayer->deleteById($result['obsoleteAlbums']);
			$this->artistBusinessLayer->deleteById($result['obsoleteArtists']);
			$this->playlistBusinessLayer->removeTracksFromAllLists($result['deletedTracks']);

			// check if a removed track was used as embedded cover art file for a remaining album
			foreach ($result['remainingAlbums'] as $albumId) {
				if ($this->albumBusinessLayer->albumCoverIsOneOfFiles($albumId, $fileIds)) {
					$this->albumBusinessLayer->setCover(null, $albumId);
					$this->findEmbeddedCoverForAlbum($albumId);
					$this->coverService->removeAlbumCoverFromCache($albumId);
				}
			}

			$this->invalidateCacheOnDelete(
					$result['affectedUsers'], $result['obsoleteAlbums'], $result['obsoleteArtists']);

			$this->logger->log('removed entities - ' . \json_encode($result), 'debug');
			$this->emit(self::class, 'delete', [$result['deletedTracks'], $result['affectedUsers']]);
		}

		return $result !== false;
	}

	/**
	 * @param int[] $fileIds
	 * @param string[]|null $userIds
	 * @return boolean true if anything was removed
	 */
	private function deleteImage(array $fileIds, ?array $userIds=null) : bool {
		$this->logger->log('deleteImage - '. \implode(', ', $fileIds), 'debug');

		$affectedAlbums = $this->albumBusinessLayer->removeCovers($fileIds, $userIds);
		$affectedArtists = $this->artistBusinessLayer->removeCovers($fileIds, $userIds);

		$affectedUsers = \array_merge(
			ArrayUtil::extractUserIds($affectedAlbums),
			ArrayUtil::extractUserIds($affectedArtists)
		);
		$affectedUsers = \array_unique($affectedUsers);

		$this->invalidateCacheOnDelete(
				$affectedUsers, ArrayUtil::extractIds($affectedAlbums), ArrayUtil::extractIds($affectedArtists));

		return (\count($affectedAlbums) + \count($affectedArtists) > 0);
	}

	/**
	 * Gets called by 'unshare' hook and 'delete' hook
	 *
	 * @param int $fileId ID of the deleted files
	 * @param string[]|null $userIds the IDs of the users to remove the file from; if omitted,
	 *                               the file is removed from all users (ie. owner and sharees)
	 */
	public function delete(int $fileId, ?array $userIds=null) : void {
		if (!$this->deleteAudio([$fileId], $userIds) && !$this->deleteImage([$fileId], $userIds)) {
			$this->logger->log("deleted file $fileId was not an indexed " .
					'audio file or a cover image', 'debug');
		}
	}

	/**
	 * Remove all audio files and cover images in the given folder from the database.
	 * This gets called when a folder is deleted or unshared from the user.
	 *
	 * @param Folder $folder
	 * @param string[]|null $userIds the IDs of the users to remove the folder from; if omitted,
	 *                               the folder is removed from all users (ie. owner and sharees)
	 */
	public function deleteFolder(Folder $folder, ?array $userIds=null) : void {
		$audioFiles = $folder->searchByMime('audio');
		if (\count($audioFiles) > 0) {
			$this->deleteAudio(ArrayUtil::extractIds($audioFiles), $userIds);
		}

		// NOTE: When a folder is removed, we don't need to check for any image
		// files in the folder. This is because those images could be potentially
		// used as covers only on the audio files of the same folder and those
		// were already removed above.
	}

	/**
	 * search for image files by mimetype inside user specified library path
	 * (which defaults to user home dir)
	 *
	 * @return File[]
	 */
	private function getImageFiles(string $userId) : array {
		try {
			$folder = $this->librarySettings->getFolder($userId);
		} catch (\OCP\Files\NotFoundException $e) {
			return [];
		}

		$images = $folder->searchByMime('image');

		// filter out any images in the excluded folders
		return \array_filter($images, function ($image) use ($userId) {
			return ($image instanceof File) // assure PHPStan that Node indeed is File
				&& $this->librarySettings->pathBelongsToMusicLibrary($image->getPath(), $userId);
		});
	}

	private function getScannedFileIds(string $userId) : array {
		return $this->trackBusinessLayer->findAllFileIds($userId);
	}

	private function getMusicFolder(string $userId, ?string $path) : Folder {
		$folder = $this->librarySettings->getFolder($userId);

		if (!empty($path)) {
			$userFolder = $this->resolveUserFolder($userId);
			$requestedFolder = FilesUtil::getFolderFromRelativePath($userFolder, $path);
			if ($folder->isSubNode($requestedFolder) || $folder->getPath() == $requestedFolder->getPath()) {
				$folder = $requestedFolder;
			} else {
				throw new \OCP\Files\NotFoundException();
			}
		}

		return $folder;
	}

	/**
	 * Search for music files by mimetype inside user specified library path
	 * (which defaults to user home dir). Exclude given array of IDs.
	 * Optionally, limit the search to only the specified path. If this path doesn't
	 * point within the library path, then nothing will be found.
	 *
	 * @param int[] $excludeIds
	 * @return int[]
	 */
	private function getAllMusicFileIdsExcluding(string $userId, ?string $path, array $excludeIds) : array {
		try {
			$folder = $this->getMusicFolder($userId, $path);
		} catch (\OCP\Files\NotFoundException $e) {
			return [];
		}

		// Search files with mime 'audio/*' but filter out the playlist files and files under excluded folders
		$files = $folder->searchByMime('audio');

		// Look-up-table of IDs to be excluded from the final result
		$excludeIdsLut = \array_flip($excludeIds);

		$files = \array_filter($files, function ($f) use ($userId, $excludeIdsLut) {
			return !isset($excludeIdsLut[$f->getId()])
					&& !self::isPlaylistMime($f->getMimeType())
					&& $this->librarySettings->pathBelongsToMusicLibrary($f->getPath(), $userId);
		});

		return \array_values(ArrayUtil::extractIds($files)); // the array may be sparse before array_values
	}

	public function getAllMusicFileIds(string $userId, ?string $path = null) : array {
		return $this->getAllMusicFileIdsExcluding($userId, $path, []);
	}

	public function getUnscannedMusicFileIds(string $userId, ?string $path = null) : array {
		$scannedIds = $this->getScannedFileIds($userId);
		$unscannedIds = $this->getAllMusicFileIdsExcluding($userId, $path, $scannedIds);

		$count = \count($unscannedIds);
		if ($count) {
			$this->logger->log("Found $count unscanned music files for user $userId", 'info');
		} else {
			$this->logger->log("No unscanned music files for user $userId", 'debug');
		}

		return $unscannedIds;
	}

	/**
	 * Find already scanned music files which have been modified since the time they were scanned
	 *
	 * @return int[]
	 */
	public function getDirtyMusicFileIds(string $userId, ?string $path = null) : array {
		$tracks = $this->trackBusinessLayer->findAllDirty($userId);
		$fileIds = \array_map(fn($t) => $t->getFileId(), $tracks);

		// filter by path if given
		if (!empty($path)) {
			try {
				$folder = $this->getMusicFolder($userId, $path);
			} catch (\OCP\Files\NotFoundException $e) {
				return [];
			}
			$fileIds = \array_filter($fileIds, fn(int $fileId) => (\count($folder->getById($fileId)) > 0));
		}

		return \array_values($fileIds); // make the array non-sparse
	}

	/**
	 * @return array ['count' => int, 'anlz_time' => int, 'db_time' => int], times in milliseconds
	 */
	public function scanFiles(string $userId, array $fileIds, ?OutputInterface $debugOutput = null) : array {
		$count = \count($fileIds);
		$this->logger->log("Scanning $count files of user $userId", 'debug');

		// back up the execution time limit
		$executionTime = \intval(\ini_get('max_execution_time'));
		// set execution time limit to unlimited
		\set_time_limit(0);

		$libraryRoot = $this->librarySettings->getFolder($userId);

		$count = 0;
		$totalAnalyzeTime = 0;
		$totalDbTime = 0;
		foreach ($fileIds as $fileId) {
			$this->cache->set($userId, 'scanning', (string)\time()); // update scanning status to prevent simultaneous background cleanup execution

			$file = $libraryRoot->getById($fileId)[0] ?? null;
			if ($file != null && !$this->librarySettings->pathBelongsToMusicLibrary($file->getPath(), $userId)) {
				$this->emit(self::class, 'exclude', [$file->getPath()]);
				$file = null;
			}
			if ($file instanceof File) {
				$memBefore = $debugOutput ? \memory_get_usage(true) : 0;
				list('analyze' => $analyzeTime, 'db update' => $dbTime)
					= $this->updateAudio($file, $userId, $libraryRoot, $file->getPath(), $file->getMimetype(), /*partOfScan=*/true);
				if ($debugOutput) {
					$memAfter = \memory_get_usage(true);
					$memDelta = $memAfter - $memBefore;
					$fmtMemAfter = Util::formatFileSize($memAfter);
					$fmtMemDelta = \mb_chr(0x0394) . Util::formatFileSize($memDelta);
					$path = $file->getPath();
					$fmtAnalyzeTime = 'anlz:' . (int)($analyzeTime / 1000000) . 'ms';
					$fmtDbTime = 'db:' . (int)($dbTime / 1000000) . 'ms';
					$debugOutput->writeln("\e[1m $count \e[0m $fmtMemAfter \e[1m ($fmtMemDelta) \e[0m $fmtAnalyzeTime \e[1m $fmtDbTime \e[0m $path");
				}
				$count++;
				$totalAnalyzeTime += $analyzeTime;
				$totalDbTime += $dbTime;
			} else {
				$this->logger->log("File with id $fileId not found for user $userId, removing it from the library if present", 'info');
				$this->deleteAudio([$fileId], [$userId]);
			}
		}

		// reset execution time limit
		\set_time_limit($executionTime);

		// invalidate the cache as the collection has changed and clear the 'scanning' status
		$this->cache->remove($userId, 'collection');
		$this->cache->remove($userId, 'scanning'); // this isn't completely thread-safe, in case there would be multiple simultaneous scan jobs for the same user for some bizarre reason

		return [
			'count' => $count,
			'anlz_time' => (int)($totalAnalyzeTime / 1000000),
			'db_time' => (int)($totalDbTime / 1000000)
		];
	}

	/**
	 * Check the availability of all the indexed audio files of the user. Remove
	 * from the index any which are not available.
	 * @return int Number of removed files
	 */
	public function removeUnavailableFiles(string $userId) : int {
		$indexedFiles = $this->getScannedFileIds($userId);
		$availableFiles = $this->getAllMusicFileIds($userId);
		$unavailableFiles = ArrayUtil::diff($indexedFiles, $availableFiles);

		$count = \count($unavailableFiles);
		if ($count > 0) {
			$this->logger->log('The following files are no longer available within the library of the '.
				"user $userId, removing: " . (string)\json_encode($unavailableFiles), 'info');
			$this->deleteAudio($unavailableFiles, [$userId]);
		}
		return $count;
	}

	/**
	 * Parse and get basic info about a file. The file does not have to be indexed in the database.
	 */
	public function getFileInfo(int $fileId, string $userId, Folder $userFolder) : ?array {
		$info = $this->getIndexedFileInfo($fileId, $userId, $userFolder)
			?: $this->getUnindexedFileInfo($fileId, $userId, $userFolder);

		// base64-encode and wrap the cover image if available
		if ($info !== null && $info['cover'] !== null) {
			$mime = $info['cover']['mimetype'];
			$content = $info['cover']['content'];
			$info['cover'] = 'data:' . $mime. ';base64,' . \base64_encode($content);
		}

		return $info;
	}

	private function getIndexedFileInfo(int $fileId, string $userId, Folder $userFolder) : ?array {
		$track = $this->trackBusinessLayer->findByFileId($fileId, $userId);
		if ($track !== null) {
			$artist = $this->artistBusinessLayer->find($track->getArtistId(), $userId);
			$album = $this->albumBusinessLayer->find($track->getAlbumId(), $userId);
			return [
				'title'      => $track->getTitle(),
				'artist'     => $artist->getName(),
				'cover'      => $this->coverService->getCover($album, $userId, $userFolder),
				'in_library' => true
			];
		}
		return null;
	}

	private function getUnindexedFileInfo(int $fileId, string $userId, Folder $userFolder) : ?array {
		$file = $userFolder->getById($fileId)[0] ?? null;
		if ($file instanceof File) {
			$metadata = $this->extractMetadata($file, $userFolder, $file->getPath(), true);
			$cover = $metadata['picture'];
			if ($cover != null) {
				$cover = $this->coverService->scaleDownAndCrop([
					'mimetype' => $cover['image_mime'],
					'content' => $cover['data']
				], 200);
			}
			return [
				'title'      => $metadata['title'],
				'artist'     => $metadata['artist'],
				'cover'      => $cover,
				'in_library' => $this->librarySettings->pathBelongsToMusicLibrary($file->getPath(), $userId)
			];
		}
		return null;
	}

	/**
	 * Update music path
	 */
	public function updatePath(string $oldPath, string $newPath, string $userId) : void {
		$this->logger->log("Changing music collection path of user $userId from $oldPath to $newPath", 'info');

		$userHome = $this->resolveUserFolder($userId);

		try {
			$oldFolder = FilesUtil::getFolderFromRelativePath($userHome, $oldPath);
			$newFolder = FilesUtil::getFolderFromRelativePath($userHome, $newPath);

			if ($newFolder->getPath() === $oldFolder->getPath()) {
				$this->logger->log('New collection path is the same as the old path, nothing to do', 'debug');
			} elseif ($newFolder->isSubNode($oldFolder)) {
				$this->logger->log('New collection path is (grand) parent of old path, previous content is still valid', 'debug');
			} elseif ($oldFolder->isSubNode($newFolder)) {
				$this->logger->log('Old collection path is (grand) parent of new path, checking the validity of previous content', 'debug');
				$this->removeUnavailableFiles($userId);
			} else {
				$this->logger->log('Old and new collection paths are unrelated, erasing the previous collection content', 'debug');
				$this->maintenance->resetLibrary($userId);
			}
		} catch (\OCP\Files\NotFoundException $e) {
			$this->logger->log('One of the paths was invalid, erasing the previous collection content', 'warn');
			$this->maintenance->resetLibrary($userId);
		}
	}

	/**
	 * Find external cover images for albums which do not yet have one.
	 * Target either one user or all users.
	 * @param string|null $userId
	 * @return bool true if any albums were updated; false otherwise
	 */
	public function findAlbumCovers(?string $userId = null) : bool {
		$affectedUsers = $this->albumBusinessLayer->findCovers($userId);
		// scratch the cache for those users whose music collection was touched
		foreach ($affectedUsers as $user) {
			$this->cache->remove($user, 'collection');
			$this->logger->log('album cover(s) were found for user '. $user, 'debug');
		}
		return !empty($affectedUsers);
	}

	/**
	 * Find external cover images for artists which do not yet have one.
	 * @param string $userId
	 * @return bool true if any albums were updated; false otherwise
	 */
	public function findArtistCovers(string $userId) : bool {
		$allImages = $this->getImageFiles($userId);
		return $this->artistBusinessLayer->updateCovers($allImages, $userId, $this->userL10N($userId));
	}

	public function resolveUserFolder(string $userId) : Folder {
		return $this->rootFolder->getUserFolder($userId);
	}

	/**
	 * Get the selected localization of the user, even in case there is no logged in user in the context.
	 */
	private function userL10N(string $userId) : IL10N {
		$languageCode = $this->config->getUserValue($userId, 'core', 'lang');
		return $this->l10nFactory->get('music', $languageCode);
	}

	/**
	 * @param int|float|string|null $ordinal
	 * @return int|float|null
	 */
	private static function normalizeOrdinal(/*mixed*/ $ordinal) {
		if (\is_string($ordinal)) {
			// convert format '1/10' to '1'
			$ordinal = \explode('/', $ordinal)[0];
		}

		// check for numeric values - cast them to int and verify it's a natural number above 0
		if (\is_numeric($ordinal) && ((int)$ordinal) > 0) {
			$ordinal = (int)$ordinal;
		} else {
			$ordinal = null;
		}

		return Util::limit($ordinal, 0, Util::SINT32_MAX); // can't use UINT32_MAX since PostgreSQL has no unsigned types
	}

	private static function parseFileName(string $fileName) : array {
		$matches = null;
		// If the file name starts e.g like "12. something" or "12 - something", the
		// preceding number is extracted as track number. Everything after the optional
		// track number + delimiters part but before the file extension is extracted as title.
		// The file extension consists of a '.' followed by 1-4 "word characters".
		if (\preg_match('/^((\d+)\s*[.-]\s+)?(.+)\.(\w{1,4})$/', $fileName, $matches) === 1) {
			return ['track_number' => $matches[2], 'title' => $matches[3]];
		} else {
			return ['track_number' => null, 'title' => $fileName];
		}
	}

	/**
	 * @param int|float|string|null $date
	 * @return int|float|null
	 */
	private static function normalizeYear(/*mixed*/ $date) {
		$year = null;
		$matches = null;

		if (\is_numeric($date)) {
			$year = (int)$date; // the date is a valid year as-is
		} elseif (\is_string($date) && \preg_match('/^(\d\d\d\d)-\d\d-\d\d.*/', $date, $matches) === 1) {
			$year = (int)$matches[1]; // year from ISO-formatted date yyyy-mm-dd
		} else {
			$year = null;
		}

		return Util::limit($year, Util::SINT32_MIN, Util::SINT32_MAX);
	}

	/**
	 * @param int|float|string|null $value
	 * @return int|float|null
	 */
	private static function normalizeUnsigned(/*mixed*/ $value) {
		if (\is_numeric($value)) {
			$value = (int)\round((float)$value);
		} else {
			$value = null;
		}
		return Util::limit($value, 0, Util::SINT32_MAX); // can't use UINT32_MAX since PostgreSQL has no unsigned types
	}

	/**
	 * Loop through the tracks of an album and set the first track containing embedded cover art
	 * as cover file for the album
	 * @param int $albumId
	 * @param string|null $userId name of user, deducted from $albumId if omitted
	 * @param Folder|null $baseFolder base folder for the search, library root of $userId is used if omitted
	 */
	private function findEmbeddedCoverForAlbum(int $albumId, ?string $userId=null, ?Folder $baseFolder=null) : void {
		if ($userId === null) {
			$userId = $this->albumBusinessLayer->findAlbumOwner($albumId);
		}
		if ($baseFolder === null) {
			$baseFolder = $this->librarySettings->getFolder($userId);
		}

		$tracks = $this->trackBusinessLayer->findAllByAlbum($albumId, $userId);
		foreach ($tracks as $track) {
			$file = $baseFolder->getById($track->getFileId())[0] ?? null;
			if ($file instanceof File) {
				$image = $this->extractor->parseEmbeddedCoverArt($file);
				if ($image != null) {
					$this->albumBusinessLayer->setCover($track->getFileId(), $albumId);
					break;
				}
			}
		}
	}
}
