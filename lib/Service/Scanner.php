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

	public function __construct(ExtractorGetID3 $extractor,
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
		$isImage = StringUtil::startsWith($mimetype, 'image');
		$isAudio = (StringUtil::startsWith($mimetype, 'audio') && !self::isPlaylistMime($mimetype));

		if (($isImage || $isAudio) && $this->librarySettings->pathBelongsToMusicLibrary($filePath, $userId)) {
			$this->logger->debug("audio or image file within lib path updated: $filePath");

			if ($isImage) {
				$this->updateImage($file, $userId);
			}
			elseif ($isAudio) {
				$libraryRoot = $this->librarySettings->getFolder($userId);
				$this->updateAudio($file, $userId, $libraryRoot, $filePath, $mimetype, /*partOfScan=*/false);
			}
		}
	}

	public function fileMoved(File $file, string $userId) : void {
		$mimetype = $file->getMimeType();

		if (StringUtil::startsWith($mimetype, 'image')) {
			$this->logger->debug('image file moved: '. $file->getPath());
			// we don't need to track the identity of images and moving a file can be handled as it was 
			// a file deletion followed by a file addition
			$this->deleteImage([$file->getId()], [$userId]);
			$this->updateImage($file, $userId);
		}
		elseif (StringUtil::startsWith($mimetype, 'audio') && !self::isPlaylistMime($mimetype)) {
			$this->logger->debug('audio file moved: '. $file->getPath());
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
		$audioFiles = $folder->searchByMime('audio');
		$audioCount = \count($audioFiles);

		if ($audioCount > 0) {
			$this->logger->debug("folder with $audioCount audio files moved: ". $folder->getPath());

			if ($this->librarySettings->pathBelongsToMusicLibrary($folder->getPath(), $userId)) {
				// The new path of the folder belongs to the library but this doesn't necessarily mean
				// that all the file paths below belong to the library, because of the path exclusions.
				// Each file needs to be checked and updated separately.
				if ($audioCount <= 15) {
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
			$this->logger->debug('updateImage - the image was set as cover for some album(s)');
			$this->cache->remove($userId, 'collection');
		}

		$artistIds = $this->artistBusinessLayer->updateCover($file, $userId, $this->userL10N($userId));
		foreach ($artistIds as $artistId) {
			$this->logger->debug("updateImage - the image was set as cover for the artist $artistId");
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

		$this->logger->debug('imported entities - ' .
				"artist: $artistId, albumArtist: $albumArtistId, album: $albumId, track: {$track->getId()}");

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
			$this->logger->debug("Delete operation affected $albumCount albums and $artistCount artists. " .
								"Invalidate the whole cache of all affected users ($userCount).");
			foreach ($affectedUsers as $user) {
				$this->cache->remove($user);
			}
		} else {
			// remove the cached covers
			if ($artistCount > 0) {
				$this->logger->debug("Remove covers of $artistCount artist(s) from the cache (if present)");
				foreach ($affectedArtists as $artistId) {
					$this->coverService->removeArtistCoverFromCache($artistId);
				}
			}

			if ($albumCount > 0) {
				$this->logger->debug("Remove covers of $albumCount album(s) from the cache (if present)");
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
	public function deleteAudio(array $fileIds, ?array $userIds=null) : bool {
		$result = $this->trackBusinessLayer->deleteTracks($fileIds, $userIds);

		if ($result) { // one or more tracks were removed
			$this->logger->debug('library updated when audio file(s) removed: '. \implode(', ', $fileIds));

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

			$this->logger->debug('removed entities: ' . \json_encode($result));
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
		$affectedAlbums = $this->albumBusinessLayer->removeCovers($fileIds, $userIds);
		$affectedArtists = $this->artistBusinessLayer->removeCovers($fileIds, $userIds);

		$anythingAffected = (\count($affectedAlbums) + \count($affectedArtists) > 0);

		if ($anythingAffected) {
			$this->logger->debug('library covers updated when image file(s) removed: '. \implode(', ', $fileIds));

			$affectedUsers = \array_merge(
				ArrayUtil::extractUserIds($affectedAlbums),
				ArrayUtil::extractUserIds($affectedArtists)
			);
			$affectedUsers = \array_unique($affectedUsers);

			$this->invalidateCacheOnDelete(
					$affectedUsers, ArrayUtil::extractIds($affectedAlbums), ArrayUtil::extractIds($affectedArtists));
		}

		return $anythingAffected;
	}

	/**
	 * Gets called by 'unshare' hook and 'delete' hook
	 *
	 * @param int $fileId ID of the deleted files
	 * @param string[]|null $userIds the IDs of the users to remove the file from; if omitted,
	 *                               the file is removed from all users (ie. owner and sharees)
	 */
	public function delete(int $fileId, ?array $userIds=null) : void {
		// The removed file may or may not be of interesting type and belong to the library. It's
		// most efficient just to try to remove it as audio or image. It will take just a few simple
		// DB queries to notice if the file had nothing to do with our library.
		if (!$this->deleteAudio([$fileId], $userIds)) {
			$this->deleteImage([$fileId], $userIds);
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

	private function getScannedFileIds(string $userId, ?string $path = null) : array {
		try {
			$folderId = $this->pathInLibToFolderId($userId, $path);
		} catch (\OCP\Files\NotFoundException $e) {
			return [];
		}
		return $this->trackBusinessLayer->findAllFileIds($userId, $folderId);
	}

	/**
	 * Find already scanned music files which have been modified since the time they were scanned
	 *
	 * @return int[]
	 */
	public function getDirtyMusicFileIds(string $userId, ?string $path = null) : array {
		try {
			$folderId = $this->pathInLibToFolderId($userId, $path);
		} catch (\OCP\Files\NotFoundException $e) {
			return [];
		}
		return $this->trackBusinessLayer->findDirtyFileIds($userId, $folderId);
	}

	/**
	 * Convert given path to a folder ID, provided that the path is within the music library.
	 * The result is null if the $path points to th root of the music library. The $path null
	 * is considered to point to the root of the lib (like in getMusicFolder).
	 */
	private function pathInLibToFolderId(string $userId, ?string $path = null) : ?int {
		$folderId = null;
		if (!empty($path)) {
			$folderId = $this->getMusicFolder($userId, $path)->getId();
			if ($folderId == $this->getMusicFolder($userId, null)->getId()) {
				// the path just pointed to the root of the library so it doesn't actually limit anything
				$folderId = null;
			}
		}
		return $folderId;
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
	 * (which defaults to user home dir).
	 * Optionally, limit the search to only the specified path. If this path doesn't
	 * point within the library path, then nothing will be found.
	 *
	 * @return int[]
	 */
	public function getAllMusicFileIds(string $userId, ?string $path = null) : array {
		try {
			$folder = $this->getMusicFolder($userId, $path);
		} catch (\OCP\Files\NotFoundException $e) {
			return [];
		}

		// Search files with mime 'audio/*' but filter out the playlist files and files under excluded folders
		$files = $folder->searchByMime('audio');

		$files = \array_filter($files, fn($f) =>
					!self::isPlaylistMime($f->getMimeType())
					&& $this->librarySettings->pathBelongsToMusicLibrary($f->getPath(), $userId)
		);

		return \array_values(ArrayUtil::extractIds($files)); // the array may be sparse before array_values
	}

	/**
	 * @return array{unscannedFiles: int[], obsoleteFiles: int[], dirtyFiles: int[], scannedCount: int}
	 */
	public function getStatusOfLibraryFiles(string $userId, ?string $path = null) : array {
		$scannedIds = $this->getScannedFileIds($userId, $path);
		$availableIds = $this->getAllMusicFileIds($userId, $path);

		return [
			'unscannedFiles' => ArrayUtil::diff($availableIds, $scannedIds),
			'obsoleteFiles' => ArrayUtil::diff($scannedIds, $availableIds),
			'dirtyFiles' => $this->getDirtyMusicFileIds($userId, $path),
			'scannedCount' => \count($scannedIds)
		];
	}

	/**
	 * @return array ['count' => int, 'anlz_time' => int, 'db_time' => int], times in milliseconds
	 */
	public function scanFiles(string $userId, array $fileIds, ?OutputInterface $debugOutput = null) : array {
		$count = \count($fileIds);
		$this->logger->debug("Scanning $count files of user $userId");

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
				$this->logger->info("File with id $fileId not found for user $userId, removing it from the library if present");
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
			$this->logger->info('The following files are no longer available within the library of the '.
				"user $userId, removing: " . (string)\json_encode($unavailableFiles));
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
		$this->logger->info("Changing music collection path of user $userId from $oldPath to $newPath");

		$userHome = $this->resolveUserFolder($userId);

		try {
			$oldFolder = FilesUtil::getFolderFromRelativePath($userHome, $oldPath);
			$newFolder = FilesUtil::getFolderFromRelativePath($userHome, $newPath);

			if ($newFolder->getPath() === $oldFolder->getPath()) {
				$this->logger->debug('New collection path is the same as the old path, nothing to do');
			} elseif ($newFolder->isSubNode($oldFolder)) {
				$this->logger->debug('New collection path is (grand) parent of old path, previous content is still valid');
			} elseif ($oldFolder->isSubNode($newFolder)) {
				$this->logger->debug('Old collection path is (grand) parent of new path, checking the validity of previous content');
				$this->removeUnavailableFiles($userId);
			} else {
				$this->logger->debug('Old and new collection paths are unrelated, erasing the previous collection content');
				$this->maintenance->resetLibrary($userId);
			}
		} catch (\OCP\Files\NotFoundException $e) {
			$this->logger->warning('One of the paths was invalid, erasing the previous collection content');
			$this->maintenance->resetLibrary($userId);
		}
	}

	/**
	 * Find external cover images for albums which do not yet have one.
	 * Target either one user or all users. 
	 * Optionally, limit the search to only the specified path. If this path doesn't point within the library path,
	 * then nothing will be found. Path is not supported when all users are targeted.
	 * 
	 * @return bool true if any albums were updated; false otherwise
	 */
	public function findAlbumCovers(?string $userId = null, ?string $path = null) : bool {
		$folderId = null;
		if ($path !== null) {
			if ($userId === null) {
				throw new \InvalidArgumentException('Argument $path is not supported without argument $userId');
			}
			try {
				$folderId = $this->pathInLibToFolderId($userId, $path);
			} catch (\OCP\Files\NotFoundException $e) {
				return false;
			}
		}

		$affectedUsers = $this->albumBusinessLayer->findCovers($userId, $folderId);
		// scratch the cache for those users whose music collection was touched
		foreach ($affectedUsers as $user) {
			$this->cache->remove($user, 'collection');
			$this->logger->debug('album cover(s) were found for user '. $user);
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
	 */
	private static function normalizeOrdinal(/*mixed*/ $ordinal) : ?int {
		if (\is_string($ordinal)) {
			// convert format '1/10' to '1'
			$ordinal = \explode('/', $ordinal)[0];
		}

		// check for numeric values - cast them to int and verify it's a natural number above 0
		if (\is_numeric($ordinal) && ((int)$ordinal) > 0) {
			$ordinal = (int)Util::limit((int)$ordinal, 0, Util::SINT32_MAX); // can't use UINT32_MAX since PostgreSQL has no unsigned types
		} else {
			$ordinal = null;
		}

		return $ordinal;
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
	 */
	private static function normalizeYear(/*mixed*/ $date) : ?int {
		$year = null;
		$matches = null;

		if (\is_numeric($date)) {
			$year = (int)$date; // the date is a valid year as-is
		} elseif (\is_string($date) && \preg_match('/^(\d\d\d\d)-\d\d-\d\d.*/', $date, $matches) === 1) {
			$year = (int)$matches[1]; // year from ISO-formatted date yyyy-mm-dd
		} else {
			$year = null;
		}

		return ($year === null) ? null : (int)Util::limit($year, Util::SINT32_MIN, Util::SINT32_MAX);
	}

	/**
	 * @param int|float|string|null $value
	 */
	private static function normalizeUnsigned(/*mixed*/ $value) : ?int {
		if (\is_numeric($value)) {
			$value = (int)\round((float)$value);
			$value = (int)Util::limit($value, 0, Util::SINT32_MAX); // can't use UINT32_MAX since PostgreSQL has no unsigned types
		} else {
			$value = null;
		}
		return $value;
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
