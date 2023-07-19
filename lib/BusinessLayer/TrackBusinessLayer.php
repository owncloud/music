<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2013
 * @copyright Pauli Järvinen 2016 - 2023
 */

namespace OCA\Music\BusinessLayer;

use OCA\Music\AppFramework\BusinessLayer\BusinessLayer;
use OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use OCA\Music\AppFramework\Core\Logger;

use OCA\Music\Db\TrackMapper;
use OCA\Music\Db\Track;

use OCA\Music\Utility\Util;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\Folder;

/**
 * Base class functions with the actually used inherited types to help IDE and Scrutinizer:
 * @method Track find(int $trackId, string $userId)
 * @method Track[] findAll(string $userId, int $sortBy=SortBy::None, int $limit=null, int $offset=null)
 * @method Track[] findAllByName(string $name, string $userId, int $matchMode=MatchMode::Exact, int $limit=null, int $offset=null)
 * @phpstan-extends BusinessLayer<Track>
 */
class TrackBusinessLayer extends BusinessLayer {
	protected $mapper; // eclipse the definition from the base class, to help IDE and Scrutinizer to know the actual type
	private $logger;

	public function __construct(TrackMapper $trackMapper, Logger $logger) {
		parent::__construct($trackMapper);
		$this->mapper = $trackMapper;
		$this->logger = $logger;
	}

	/**
	 * Returns all tracks filtered by artist (both album and track artists are considered)
	 * @return Track[]
	 */
	public function findAllByArtist(int $artistId, string $userId, ?int $limit=null, ?int $offset=null) : array {
		return $this->mapper->findAllByArtist($artistId, $userId, $limit, $offset);
	}

	/**
	 * Returns all tracks filtered by album. Optionally, filter also by the performing artist.
	 * @return Track[]
	 */
	public function findAllByAlbum(int $albumId, string $userId, ?int $artistId=null, ?int $limit=null, ?int $offset=null) : array {
		return $this->mapper->findAllByAlbum($albumId, $userId, $artistId, $limit, $offset);
	}

	/**
	 * Returns all tracks filtered by parent folder
	 * @return Track[]
	 */
	public function findAllByFolder(int $folderId, string $userId, ?int $limit=null, ?int $offset=null) : array {
		return $this->mapper->findAllByFolder($folderId, $userId, $limit, $offset);
	}

	/**
	 * Returns all tracks filtered by genre
	 * @return Track[]
	 */
	public function findAllByGenre(int $genreId, string $userId, ?int $limit=null, ?int $offset=null) : array {
		return $this->mapper->findAllByGenre($genreId, $userId, $limit, $offset);
	}

	/**
	 * Returns all tracks filtered by name (of track/album/artist)
	 * @param string $name the name of the track/album/artist
	 * @param string $userId the name of the user
	 * @return Track[]
	 */
	public function findAllByNameRecursive(string $name, string $userId, ?int $limit=null, ?int $offset=null) : array {
		$name = \trim($name);
		return $this->mapper->findAllByNameRecursive($name, $userId, $limit, $offset);
	}

	/**
	 * Returns all tracks specified by name and/or artist name
	 * @return Track[] Tracks matching the criteria
	 */
	public function findAllByNameAndArtistName(?string $name, ?string $artistName, string $userId) : array {
		if ($name !== null) {
			$name = \trim($name);
		}
		if ($artistName !== null) {
			$artistName = \trim($artistName);
		}

		return $this->mapper->findAllByNameAndArtistName($name, $artistName, $userId);
	}

	/**
	 * Returns all tracks where the 'modified' time in the file system (actually in the cloud's file cache)
	 * is later than the 'updated' field of the entity in the database.
	 * @return Track[]
	 */
	public function findAllDirty(string $userId) : array {
		$tracks = $this->findAll($userId);
		return \array_filter($tracks, function (Track $track) {
			$dbModTime = new \DateTime($track->getUpdated());
			return ($dbModTime->getTimestamp() < $track->getFileModTime());
		});
	}

	/**
	 * Find most frequently played tracks
	 * @return Track[]
	 */
	public function findFrequentPlay(string $userId, ?int $limit=null, ?int $offset=null) : array {
		return $this->mapper->findFrequentPlay($userId, $limit, $offset);
	}

	/**
	 * Find most recently played tracks
	 * @return Track[]
	 */
	public function findRecentPlay(string $userId, ?int $limit=null, ?int $offset=null) : array {
		return $this->mapper->findRecentPlay($userId, $limit, $offset);
	}

	/**
	 * Find least recently played tracks
	 * @return Track[]
	 */
	public function findNotRecentPlay(string $userId, ?int $limit=null, ?int $offset=null) : array {
		return $this->mapper->findNotRecentPlay($userId, $limit, $offset);
	}

	/**
	 * Returns the track for a file id
	 * @return Track|null
	 */
	public function findByFileId(int $fileId, string $userId) : ?Track {
		try {
			return $this->mapper->findByFileId($fileId, $userId);
		} catch (DoesNotExistException $e) {
			return null;
		}
	}

	/**
	 * Returns file IDs of all indexed tracks of the user
	 * @return int[]
	 */
	public function findAllFileIds(string $userId) : array {
		return $this->mapper->findAllFileIds($userId);
	}

	/**
	 * Returns all folders of the user containing indexed tracks, along with the contained track IDs
	 * @return array of entries like {id: int, name: string, path: string, parent: ?int, trackIds: int[]}
	 */
	public function findAllFolders(string $userId, Folder $musicFolder) : array {
		// All tracks of the user, grouped by their parent folders. Some of the parent folders
		// may be owned by other users and are invisible to this user (in case of shared files).
		$tracksByFolder = $this->mapper->findTrackAndFolderIds($userId);

		// Get the folder names and paths for ordinary local folders directly from the DB.
		// This is significantly more efficient than using the Files API because we need to
		// run only single DB query instead of one per folder.
		$folderNamesAndParents = $this->mapper->findNodeNamesAndParents(
				\array_keys($tracksByFolder), $musicFolder->getStorage()->getId());

		// root folder has to be handled as a special case because shared files from
		// many folders may be shown to this user mapped under the root folder
		$rootFolderTracks = [];

		// Build the final results. Use the previously fetched data for the ordinary
		// local folders and query the data through the Files API for the more special cases.
		$result = [];
		foreach ($tracksByFolder as $folderId => $trackIds) {
			$entry = self::getFolderEntry($folderNamesAndParents, $folderId, $trackIds, $musicFolder);

			if ($entry) {
				$result[] = $entry;
			} else {
				$rootFolderTracks = \array_merge($rootFolderTracks, $trackIds);
			}
		}

		// add the library root folder
		$result[] = [
			'name' => '',
			'parent' => null,
			'trackIds' => $rootFolderTracks,
			'id' => $musicFolder->getId()
		];

		// add the intermediate folders which do not directly contain any tracks
		$this->recursivelyAddMissingParentFolders($result, $result, $musicFolder);

		return $result;
	}

	private function recursivelyAddMissingParentFolders(array $foldersToProcess, array &$alreadyFoundFolders, Folder $musicFolder) : void {

		$parentIds = \array_unique(\array_column($foldersToProcess, 'parent'));
		$parentIds = Util::arrayDiff($parentIds, \array_column($alreadyFoundFolders, 'id'));
		$parentInfo = $this->mapper->findNodeNamesAndParents($parentIds, $musicFolder->getStorage()->getId());

		$newParents = [];
		foreach ($parentIds as $parentId) {
			if ($parentId !== null) {
				$parentEntry = self::getFolderEntry($parentInfo, $parentId, [], $musicFolder);
				if ($parentEntry !== null) {
					$newParents[] = $parentEntry;
				}
			}
		}

		$alreadyFoundFolders = \array_merge($alreadyFoundFolders, $newParents);

		if (\count($newParents)) {
			$this->recursivelyAddMissingParentFolders($newParents, $alreadyFoundFolders, $musicFolder);
		}
	}

	private static function getFolderEntry(array $folderNamesAndParents, int $folderId, array $trackIds, Folder $musicFolder) : ?array {
		if (isset($folderNamesAndParents[$folderId])) {
			// normal folder within the user home storage
			$entry = $folderNamesAndParents[$folderId];
			// special handling for the root folder
			if ($folderId === $musicFolder->getId()) {
				$entry = null;
			}
		} else {
			// shared folder or parent folder of a shared file or an externally mounted folder
			$folderNode = $musicFolder->getById($folderId)[0] ?? null;
			if ($folderNode === null) {
				// other user's folder with files shared with this user (mapped under root)
				$entry = null;
			} else {
				$entry = [
					'name' => $folderNode->getName(),
					'parent' => $folderNode->getParent()->getId()
				];
			}
		}

		if ($entry) {
			$entry['trackIds'] = $trackIds;
			$entry['id'] = $folderId;

			if ($entry['id'] == $musicFolder->getId()) {
				// the library root should be reported without a parent folder as that parent does not belong to the library
				$entry['parent'] = null;
			}
		}

		return $entry;
	}

	/**
	 * Returns all genre IDs associated with the given artist
	 * @return int[]
	 */
	public function getGenresByArtistId(int $artistId, string $userId) : array {
		return $this->mapper->getGenresByArtistId($artistId, $userId);
	}

	/**
	 * Returns file IDs of the tracks which do not have genre scanned. This is not the same
	 * thing as unknown genre, which is stored as empty string and means that the genre has
	 * been scanned but was not found from the track metadata.
	 * @return int[]
	 */
	public function findFilesWithoutScannedGenre(string $userId) : array {
		return $this->mapper->findFilesWithoutScannedGenre($userId);
	}

	public function countByArtist(int $artistId) : int {
		return $this->mapper->countByArtist($artistId);
	}

	public function countByAlbum(int $albumId) : int {
		return $this->mapper->countByAlbum($albumId);
	}

	/**
	 * @return integer Duration in seconds
	 */
	public function totalDurationOfAlbum(int $albumId) : int {
		return $this->mapper->totalDurationOfAlbum($albumId);
	}

	/**
	 * @return integer Duration in seconds
	 */
	public function totalDurationByArtist(int $artistId) : int {
		return $this->mapper->totalDurationByArtist($artistId);
	}

	/**
	 * Update "last played" timestamp and increment the total play count of the track.
	 */
	public function recordTrackPlayed(int $trackId, string $userId, ?\DateTime $timeOfPlay = null) : void {
		$timeOfPlay = $timeOfPlay ?? new \DateTime();

		if (!$this->mapper->recordTrackPlayed($trackId, $userId, $timeOfPlay)) {
			throw new BusinessLayerException("Track with ID $trackId was not found");
		}
	}

	/**
	 * Adds a track if it does not exist already or updates an existing track
	 * @param string $title the title of the track
	 * @param int|null $number the number of the track
	 * @param int|null $discNumber the number of the disc
	 * @param int|null $year the year of the release
	 * @param int $genreId the genre id of the track
	 * @param int $artistId the artist id of the track
	 * @param int $albumId the album id of the track
	 * @param int $fileId the file id of the track
	 * @param string $mimetype the mimetype of the track
	 * @param string $userId the name of the user
	 * @param int $length track length in seconds
	 * @param int $bitrate track bitrate in bits (not kbits)
	 * @return Track The added/updated track
	 */
	public function addOrUpdateTrack(
			$title, $number, $discNumber, $year, $genreId, $artistId, $albumId,
			$fileId, $mimetype, $userId, $length=null, $bitrate=null) {
		$track = new Track();
		$track->setTitle(Util::truncate($title, 256)); // some DB setups can't truncate automatically to column max size
		$track->setNumber($number);
		$track->setDisk($discNumber);
		$track->setYear($year);
		$track->setGenreId($genreId);
		$track->setArtistId($artistId);
		$track->setAlbumId($albumId);
		$track->setFileId($fileId);
		$track->setMimetype($mimetype);
		$track->setUserId($userId);
		$track->setLength($length);
		$track->setBitrate($bitrate);
		return $this->mapper->insertOrUpdate($track);
	}

	/**
	 * Deletes a track
	 * @param int[] $fileIds file IDs of the tracks to delete
	 * @param string[]|null $userIds the target users; if omitted, the tracks matching the
	 *                      $fileIds are deleted from all users
	 * @return array|false  False is returned if no such track was found; otherwise array of six arrays
	 *         (named 'deletedTracks', 'remainingAlbums', 'remainingArtists', 'obsoleteAlbums',
	 *         'obsoleteArtists', and 'affectedUsers'). These contain the track, album, artist, and
	 *         user IDs of the deleted tracks. The 'obsolete' entities are such which no longer
	 *         have any tracks while 'remaining' entities have some left.
	 */
	public function deleteTracks(array $fileIds, ?array $userIds=null) {
		$tracks = ($userIds !== null)
			? $this->mapper->findByFileIds($fileIds, $userIds)
			: $this->mapper->findAllByFileIds($fileIds);

		if (\count($tracks) === 0) {
			$result = false;
		} else {
			// delete all the matching tracks
			$trackIds = Util::extractIds($tracks);
			$this->deleteById($trackIds);

			// find all distinct albums, artists, and users of the deleted tracks
			$artists = [];
			$albums = [];
			$users = [];
			foreach ($tracks as $track) {
				$artists[$track->getArtistId()] = 1;
				$albums[$track->getAlbumId()] = 1;
				$users[$track->getUserId()] = 1;
			}
			$artists = \array_keys($artists);
			$albums = \array_keys($albums);
			$users = \array_keys($users);

			// categorize each artist as 'remaining' or 'obsolete'
			$remainingArtists = [];
			$obsoleteArtists = [];
			foreach ($artists as $artistId) {
				if ($this->mapper->countByArtist($artistId) === 0) {
					$obsoleteArtists[] = $artistId;
				} else {
					$remainingArtists[] = $artistId;
				}
			}

			// categorize each album as 'remaining' or 'obsolete'
			$remainingAlbums = [];
			$obsoleteAlbums = [];
			foreach ($albums as $albumId) {
				if ($this->mapper->countByAlbum($albumId) === 0) {
					$obsoleteAlbums[] = $albumId;
				} else {
					$remainingAlbums[] = $albumId;
				}
			}

			$result = [
				'deletedTracks'    => $trackIds,
				'remainingAlbums'  => $remainingAlbums,
				'remainingArtists' => $remainingArtists,
				'obsoleteAlbums'   => $obsoleteAlbums,
				'obsoleteArtists'  => $obsoleteArtists,
				'affectedUsers'    => $users
			];
		}

		return $result;
	}
}
