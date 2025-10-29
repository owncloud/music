<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2025
 */

namespace OCA\Music\Service;

use OCA\Music\AppFramework\Core\Logger;
use OCA\Music\Db\Track;
use OCA\Music\Db\TrackMapper;
use OCA\Music\Utility\ArrayUtil;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\FileInfo;
use OCP\Files\Folder;

class FileSystemService {

	private TrackMapper $mapper;
	private Logger $logger;

	public function __construct(TrackMapper $trackMapper, Logger $logger) {
		$this->mapper = $trackMapper;
		$this->logger = $logger;
	}

	/**
	 * Returns all folders of the user containing indexed tracks, along with the contained track IDs
	 * @return array of entries like {id: int, name: string, parent: ?int, trackIds: int[]}
	 */
	public function findAllFolders(string $userId, Folder $musicFolder) : array {
		// All tracks of the user, grouped by their parent folders. Some of the parent folders
		// may be owned by other users and are invisible to this user (in case of shared files).
		$trackIdsByFolder = $this->mapper->findTrackAndFolderIds($userId);
		$foldersLut = $this->getFoldersLut($trackIdsByFolder, $userId, $musicFolder);
		return \array_map(
			fn($id, $folderInfo) => \array_merge($folderInfo, ['id' => $id]),
			\array_keys($foldersLut), $foldersLut
		);
	}

	/**
	 * @param Track[] $tracks (in|out)
	 */
	public function injectFolderPathsToTracks(array $tracks, string $userId, Folder $musicFolder) : void {
		$folderIds = \array_map(fn($t) => $t->getFolderId(), $tracks);
		$folderIds = \array_unique($folderIds);
		$trackIdsByFolder = \array_fill_keys($folderIds, []); // track IDs are not actually used here so we can use empty arrays

		$foldersLut = $this->getFoldersLut($trackIdsByFolder, $userId, $musicFolder);

		// recursive helper to get folder's path and cache all parent paths on the way
		$getFolderPath = function(int $id, array &$foldersLut) use (&$getFolderPath) : string {
			// setup the path if not cached already
			if (!isset($foldersLut[$id]['path'])) {
				$parentId = $foldersLut[$id]['parent'];
				if ($parentId === null) {
					$foldersLut[$id]['path'] = '';
				} else {
					$foldersLut[$id]['path'] = $getFolderPath($parentId, $foldersLut) . '/' . $foldersLut[$id]['name'];
				}
			}
			return $foldersLut[$id]['path'];
		};

		foreach ($tracks as $track) {
			$track->setFolderPath($getFolderPath($track->getFolderId(), $foldersLut));
		}
	}

	/**
	 * Find all direct and indirect sub folders of the given folder. The result will include also the start folder.
	 * NOTE: This does not return the mounted or shared folders even in case the $folderId points to user home directory.
	 * @return int[]
	 */
	public function findAllDescendantFolders(int $folderId) : array {
		$descendants = [];
		$foldersToProcess = [$folderId];

		while(\count($foldersToProcess)) {
			$descendants = \array_merge($descendants, $foldersToProcess);
			$foldersToProcess = $this->mapper->findSubFolderIds($foldersToProcess);
		}

		return $descendants;
	}

	/**
	 * Get folder info lookup table, for the given tracks. The table will contain all the predecessor folders
	 * between those tracks and the root music folder (inclusive).
	 * 
	 * @param array $trackIdsByFolder Keys are folder IDs and values are arrays of track IDs
	 * @return array Keys are folder IDs and values are arrays like ['name' : string, 'parent' : int, 'trackIds' : int[]]
	 */
	private function getFoldersLut(array $trackIdsByFolder, string $userId, Folder $musicFolder) : array {
		// Get the folder names and direct parent folder IDs directly from the DB.
		// This is significantly more efficient than using the Files API because we need to
		// run only single DB query instead of one per folder.
		$folderNamesAndParents = $this->mapper->findNodeNamesAndParents(\array_keys($trackIdsByFolder));

		// Compile the look-up-table entries from our two intermediary arrays
		$lut = [];
		foreach ($trackIdsByFolder as $folderId => $trackIds) {
			// $folderId is not found from $folderNamesAndParents if it's a dummy ID created as placeholder on a malformed playlist
			$nameAndParent = $folderNamesAndParents[$folderId] ?? ['name' => '', 'parent' => null];
			$lut[$folderId] = \array_merge($nameAndParent, ['trackIds' => $trackIds]);
		}

		// the root folder should have null parent; here we also ensure it's included
		$rootFolderId = $musicFolder->getId();
		$rootTracks = $lut[$rootFolderId]['trackIds'] ?? [];
		$lut[$rootFolderId] = ['name' => '', 'parent' => null, 'trackIds' => $rootTracks];

		// External mounts and shared files/folders need some special handling. But if there are any, they should be found
		// right under the top-level folder.
		$this->addExternalMountsToFoldersLut($lut, $userId, $musicFolder);

		// Add the intermediate folders which do not directly contain any tracks
		$this->addMissingParentsToFoldersLut($lut);

		return $lut;
	}

	/**
	 * Add externally mounted folders and shared files and folders to the folder LUT if there are any under the $musicFolder
	 * 
	 * @param array $lut (in|out) Keys are folder IDs and values are arrays like ['name' : string, 'parent' : int, 'trackIds' : int[]]
	 */
	private function addExternalMountsToFoldersLut(array &$lut, string $userId, Folder $musicFolder) : void {
		$nodesUnderRoot = $musicFolder->getDirectoryListing();
		$homeStorageId = $musicFolder->getStorage()->getId();
		$rootFolderId = $musicFolder->getId();

		foreach ($nodesUnderRoot as $node) {
			if ($node->getStorage()->getId() != $homeStorageId) {
				// shared file/folder or external mount
				if ($node->getType() == FileInfo::TYPE_FOLDER) {
					// The mount point folders are always included in the result. At this time, we don't know if
					// they actually contain any tracks, unless they have direct track children. If there are direct tracks,
					// then the parent ID is incorrectly set and needs to be overridden.
					$trackIds = $lut[$node->getId()]['trackIds'] ?? [];
					$lut[$node->getId()] = ['name' => $node->getName(), 'parent' => $rootFolderId, 'trackIds' => $trackIds];

				} else if ($node->getMimePart() == 'audio') {
					// shared audio file, check if it's actually a scanned file in our library
					try {
						$sharedTrack = $this->mapper->findByFileId($node->getId(), $userId);
						$trackId = $sharedTrack->getId();
						foreach ($lut as $folderId => &$entry) {
							$trackIdIdx = \array_search($trackId, $entry['trackIds']);
							if ($trackIdIdx !== false) {
								// move the track from it's actual parent (in other user's storage) to our root
								unset($entry['trackIds'][$trackIdIdx]);
								$lut[$rootFolderId]['trackIds'][] = $trackId;

								// remove the former parent folder if it has no more tracks and it's not one of the mount point folders
								if (\count($entry['trackIds']) == 0 && empty(\array_filter($nodesUnderRoot, fn($n) => $n->getId() == $folderId))) {
									unset($lut[$folderId]);
								}
								break;
							}
						}
					} catch (DoesNotExistException $e) {
						// ignore
					}
				}
			}
		}
	}

	/**
	 * Add any missing intermediary folder to the LUT. For this function to work correctly, the pre-condition is that the LUT contains
	 * a root node which is predecessor of all other contained nodes and has 'parent' set as null.
	 * 
	 * @param array $lut (in|out) Keys are folder IDs and values are arrays like ['name' : string, 'parent' : int, 'trackIds' : int[]]
	 */
	private function addMissingParentsToFoldersLut(array &$lut) : void {
		$foldersToProcess = $lut;

		while (\count($foldersToProcess)) {
			$parentIds = \array_unique(\array_column($foldersToProcess, 'parent'));
			// do not process root even if it's included in $foldersToProcess
			$parentIds = \array_filter($parentIds, fn($i) => $i !== null);
			$parentIds = ArrayUtil::diff($parentIds, \array_keys($lut));
			$parentFolders = $this->mapper->findNodeNamesAndParents($parentIds);

			$foldersToProcess = [];
			foreach ($parentFolders as $folderId => $nameAndParent) {
				$foldersToProcess[] = $lut[$folderId] = \array_merge($nameAndParent, ['trackIds' => []]);
			}
		}
	}

}