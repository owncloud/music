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
 * @copyright Pauli Järvinen 2017 - 2025
 */

namespace OCA\Music\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;

use OCP\Files\Folder;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IURLGenerator;

use OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use OCA\Music\AppFramework\Core\Logger;
use OCA\Music\BusinessLayer\AlbumBusinessLayer;
use OCA\Music\BusinessLayer\ArtistBusinessLayer;
use OCA\Music\BusinessLayer\GenreBusinessLayer;
use OCA\Music\BusinessLayer\PlaylistBusinessLayer;
use OCA\Music\BusinessLayer\TrackBusinessLayer;
use OCA\Music\Db\Playlist;
use OCA\Music\Http\ErrorResponse;
use OCA\Music\Http\FileResponse;
use OCA\Music\Utility\CoverHelper;
use OCA\Music\Utility\PlaylistFileService;

class PlaylistApiController extends Controller {
	private IURLGenerator $urlGenerator;
	private PlaylistBusinessLayer $playlistBusinessLayer;
	private ArtistBusinessLayer $artistBusinessLayer;
	private AlbumBusinessLayer $albumBusinessLayer;
	private TrackBusinessLayer $trackBusinessLayer;
	private GenreBusinessLayer $genreBusinessLayer;
	private CoverHelper $coverHelper;
	private PlaylistFileService $playlistFileService;
	private string $userId;
	private Folder $userFolder;
	private IConfig $configManager;
	private Logger $logger;

	public function __construct(string $appname,
								IRequest $request,
								IURLGenerator $urlGenerator,
								PlaylistBusinessLayer $playlistBusinessLayer,
								ArtistBusinessLayer $artistBusinessLayer,
								AlbumBusinessLayer $albumBusinessLayer,
								TrackBusinessLayer $trackBusinessLayer,
								GenreBusinessLayer $genreBusinessLayer,
								CoverHelper $coverHelper,
								PlaylistFileService $playlistFileService,
								string $userId,
								Folder $userFolder,
								IConfig $configManager,
								Logger $logger) {
		parent::__construct($appname, $request);
		$this->urlGenerator = $urlGenerator;
		$this->playlistBusinessLayer = $playlistBusinessLayer;
		$this->artistBusinessLayer = $artistBusinessLayer;
		$this->albumBusinessLayer = $albumBusinessLayer;
		$this->trackBusinessLayer = $trackBusinessLayer;
		$this->genreBusinessLayer = $genreBusinessLayer;
		$this->coverHelper = $coverHelper;
		$this->playlistFileService = $playlistFileService;
		$this->userId = $userId;
		$this->userFolder = $userFolder;
		$this->configManager = $configManager;
		$this->logger = $logger;
	}

	/**
	 * lists all playlists
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function getAll(string $type = 'shiva') {
		$playlists = $this->playlistBusinessLayer->findAll($this->userId);
		if ($type === 'shiva') {
			return \array_map(fn($p) => $p->toShivaApi($this->urlGenerator), $playlists);
		} else {
			return \array_map(fn($p) => $p->toApi($this->urlGenerator), $playlists);
		}
	}

	/**
	 * creates a playlist
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function create(string $name, /*mixed*/ $trackIds, ?string $comment=null) {
		$playlist = $this->playlistBusinessLayer->create($name, $this->userId);

		// add trackIds and comment to the newly created playlist if provided
		if (!empty($trackIds)) {
			$playlist = $this->playlistBusinessLayer->addTracks(
					self::toIntArray($trackIds), $playlist->getId(), $this->userId);
		}
		if ($comment !== null) {
			$playlist = $this->playlistBusinessLayer->setComment($comment, $playlist->getId(), $this->userId);
		}

		return $playlist->toApi($this->urlGenerator);
	}

	/**
	 * deletes a playlist
	 * @param  int $id playlist ID
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function delete(int $id) {
		$this->playlistBusinessLayer->delete($id, $this->userId);
		return [];
	}

	/**
	 * lists a single playlist
	 * @param  int $id playlist ID
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function get(int $id, string $type = 'shiva', $fulltree = 'false') {
		try {
			$playlist = $this->playlistBusinessLayer->find($id, $this->userId);

			if ($type === 'shiva') {
				$result = $playlist->toShivaApi($this->urlGenerator);
			} else {
				$result = $playlist->toApi($this->urlGenerator);
			}

			$fulltree = \filter_var($fulltree, FILTER_VALIDATE_BOOLEAN);
			if ($fulltree) {
				unset($result['trackIds']);
				$result['tracks'] = $this->getTracksFulltree($playlist);
			}

			return $result;
		} catch (BusinessLayerException $ex) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, $ex->getMessage());
		}
	}

	private function getTracksFulltree(Playlist $playlist) : array {
		$trackIds = $playlist->getTrackIdsAsArray();
		$tracks = $this->trackBusinessLayer->findById($trackIds, $this->userId);
		$this->albumBusinessLayer->injectAlbumsToTracks($tracks, $this->userId);

		return \array_map(
			fn($track, $index) => \array_merge($track->toShivaApi($this->urlGenerator), ['index' => $index]),
			$tracks, \array_keys($tracks)
		);
	}

	/**
	 * generate a smart playlist according to the given rules
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function generate(
			?bool $useLatestParams, ?string $history, ?string $genres, ?string $artists,
			?int $fromYear, ?int $toYear, ?string $favorite=null, int $size=100, string $historyStrict='false') {

		if ($useLatestParams) {
			$history = $this->configManager->getUserValue($this->userId, $this->appName, 'smartlist_history') ?: null;
			$genres = $this->configManager->getUserValue($this->userId, $this->appName, 'smartlist_genres') ?: null;
			$artists = $this->configManager->getUserValue($this->userId, $this->appName, 'smartlist_artists') ?: null;
			$fromYear = (int)$this->configManager->getUserValue($this->userId, $this->appName, 'smartlist_from_year') ?: null;
			$toYear = (int)$this->configManager->getUserValue($this->userId, $this->appName, 'smartlist_to_year') ?: null;
			$favorite = $this->configManager->getUserValue($this->userId, $this->appName, 'smartlist_favorite') ?: null;
			$size = (int)$this->configManager->getUserValue($this->userId, $this->appName, 'smartlist_size', 100);
			$historyStrict = $this->configManager->getUserValue($this->userId, $this->appName, 'smartlist_history_strict', 'false');
		} else {
			$this->configManager->setUserValue($this->userId, $this->appName, 'smartlist_history', $history ?? '');
			$this->configManager->setUserValue($this->userId, $this->appName, 'smartlist_genres', $genres ?? '');
			$this->configManager->setUserValue($this->userId, $this->appName, 'smartlist_artists', $artists ?? '');
			$this->configManager->setUserValue($this->userId, $this->appName, 'smartlist_from_year', (string)$fromYear);
			$this->configManager->setUserValue($this->userId, $this->appName, 'smartlist_to_year', (string)$toYear);
			$this->configManager->setUserValue($this->userId, $this->appName, 'smartlist_favorite', $favorite ?? '');
			$this->configManager->setUserValue($this->userId, $this->appName, 'smartlist_size', (string)$size);
			$this->configManager->setUserValue($this->userId, $this->appName, 'smartlist_history_strict', $historyStrict);
		}
		$historyStrict = \filter_var($historyStrict, FILTER_VALIDATE_BOOLEAN);

		// ensure the artists and genres contain only valid IDs
		$genres = $this->genreBusinessLayer->findAllIds($this->userId, self::toIntArray($genres));
		$artists = $this->artistBusinessLayer->findAllIds($this->userId, self::toIntArray($artists));

		$playlist = $this->playlistBusinessLayer->generate(
				$history, $historyStrict, $genres, $artists, $fromYear, $toYear, $favorite, $size, $this->userId);
		$result = $playlist->toApi($this->urlGenerator);

		$result['params'] = [
			'history' => $history ?: null,
			'historyStrict' => $historyStrict,
			'genres' => \implode(',', $genres) ?: null,
			'artists' => \implode(',', $artists) ?: null,
			'fromYear' => $fromYear ?: null,
			'toYear' => $toYear ?: null,
			'favorite' => $favorite ?: null,
			'size' => $size
		];

		return $result;
	}

	/**
	 * get cover image for a playlist
	 * @param int $id playlist ID
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function getCover(int $id) {
		try {
			$playlist = $this->playlistBusinessLayer->find($id, $this->userId);
			$cover = $this->coverHelper->getCover($playlist, $this->userId, $this->userFolder);

			if ($cover !== null) {
				return new FileResponse($cover);
			} else {
				return new ErrorResponse(Http::STATUS_NOT_FOUND, 'The playlist has no cover art');
			}
		} catch (BusinessLayerException $ex) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, $ex->getMessage());
		}
	}

	/**
	 * update a playlist
	 * @param int $id playlist ID
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function update(int $id, ?string $name = null, ?string $comment = null, ?string $trackIds = null) {
		$result = null;
		if ($name !== null) {
			$result = $this->modifyPlaylist('rename', [$name, $id, $this->userId]);
		}
		if ($comment !== null) {
			$result = $this->modifyPlaylist('setComment', [$comment, $id, $this->userId]);
		}
		if ($trackIds!== null) {
			$result = $this->modifyPlaylist('setTracks', [self::toIntArray($trackIds), $id, $this->userId]);
		}
		if ($result === null) {
			$result = new ErrorResponse(Http::STATUS_BAD_REQUEST, "at least one of the args ['name', 'comment', 'trackIds'] must be given");
		}
		return $result;
	}

	/**
	 * insert or append tracks to a playlist
	 * @param int $id playlist ID
	 * @param string|int $track Comma-separated list of track IDs
	 * @param ?int $index Insertion position within the playlist, or null to append
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function addTracks(int $id, $track, ?int $index = null) {
		return $this->modifyPlaylist('addTracks', [self::toIntArray($track), $id, $this->userId, $index]);
	}

	/**
	 * removes tracks from a playlist
	 * @param int $id playlist ID
	 * @param string|int $index Comma-separated list of track indices within the playlist
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function removeTracks(int $id, $index) {
		return $this->modifyPlaylist('removeTracks', [self::toIntArray($index), $id, $this->userId]);
	}

	/**
	 * moves single track on playlist to a new position
	 * @param int $id playlist ID
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function reorder(int $id, $fromIndex, $toIndex) {
		return $this->modifyPlaylist('moveTrack',
				[$fromIndex, $toIndex, $id, $this->userId]);
	}

	/**
	 * export the playlist to a file
	 * @param int $id playlist ID
	 * @param string $path parent folder path
	 * @param string $oncollision action to take on file name collision,
	 *								supported values:
	 *								- 'overwrite' The existing file will be overwritten
	 *								- 'keepboth' The new file is named with a suffix to make it unique
	 *								- 'abort' (default) The operation will fail
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function exportToFile(int $id, string $path, string $oncollision) {
		try {
			$exportedFilePath = $this->playlistFileService->exportToFile(
					$id, $this->userId, $this->userFolder, $path, $oncollision);
			return new JSONResponse(['wrote_to_file' => $exportedFilePath]);
		} catch (BusinessLayerException $ex) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, 'playlist not found');
		} catch (\OCP\Files\NotFoundException $ex) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, 'folder not found');
		} catch (\RuntimeException $ex) {
			return new ErrorResponse(Http::STATUS_CONFLICT, $ex->getMessage());
		} catch (\OCP\Files\NotPermittedException $ex) {
			return new ErrorResponse(Http::STATUS_FORBIDDEN, 'user is not allowed to write to the target file');
		}
	}

	/**
	 * import playlist contents from a file
	 * @param int $id playlist ID
	 * @param string $filePath path of the file to import
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function importFromFile(int $id, string $filePath) {
		try {
			$result = $this->playlistFileService->importFromFile($id, $this->userId, $this->userFolder, $filePath);
			$result['playlist'] = $result['playlist']->toApi($this->urlGenerator);
			return $result;
		} catch (BusinessLayerException $ex) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, 'playlist not found');
		} catch (\OCP\Files\NotFoundException $ex) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, 'playlist file not found');
		} catch (\UnexpectedValueException $ex) {
			return new ErrorResponse(Http::STATUS_UNSUPPORTED_MEDIA_TYPE, $ex->getMessage());
		}
	}

	/**
	 * read and parse a playlist file
	 * @param int $fileId ID of the file to parse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function parseFile(int $fileId) {
		try {
			$result = $this->playlistFileService->parseFile($fileId, $this->userFolder);

			// Make a lookup table of all the file IDs in the user library to avoid having to run
			// a DB query for each track in the playlist to check if it is in the library. This
			// could make a difference in case of a huge playlist.
			$libFileIds = $this->trackBusinessLayer->findAllFileIds($this->userId);
			$libFileIds = \array_flip($libFileIds);

			$bogusUrlId = -1;

			// compose the final result
			$result['files'] = \array_map(function ($fileInfo) use ($libFileIds, &$bogusUrlId) {
				if (isset($fileInfo['url'])) {
					$fileInfo['id'] = $bogusUrlId--;
					$fileInfo['mimetype'] = null;
					$fileInfo['external'] = true;
					return $fileInfo;
				} else {
					$file = $fileInfo['file'];
					return [
						'id' => $file->getId(),
						'name' => $file->getName(),
						'path' => $this->userFolder->getRelativePath($file->getParent()->getPath()),
						'mimetype' => $file->getMimeType(),
						'caption' => $fileInfo['caption'],
						'in_library' => isset($libFileIds[$file->getId()]),
						'external' => false
					];
				}
			}, $result['files']);
			return new JSONResponse($result);
		} catch (\OCP\Files\NotFoundException $ex) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, 'playlist file not found');
		} catch (\UnexpectedValueException $ex) {
			return new ErrorResponse(Http::STATUS_UNSUPPORTED_MEDIA_TYPE, $ex->getMessage());
		}
	}

	/**
	 * Modify playlist by calling a supplied method from PlaylistBusinessLayer
	 * @param string $funcName  Name of a function to call from PlaylistBusinessLayer
	 * @param array $funcParams Parameters to pass to the function 'funcName'
	 * @return JSONResponse JSON representation of the modified playlist
	 */
	private function modifyPlaylist(string $funcName, array $funcParams) : JSONResponse {
		try {
			$playlist = \call_user_func_array([$this->playlistBusinessLayer, $funcName], $funcParams);
			return new JSONResponse($playlist->toApi($this->urlGenerator));
		} catch (BusinessLayerException $ex) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, $ex->getMessage());
		}
	}

	/**
	 * Get integer array passed as parameter to the Playlist API
	 * @param string|int|null $listAsString Comma-separated integer values in string, or a single integer
	 * @return int[]
	 */
	private static function toIntArray(/*mixed*/ $listAsString) : array {
		if ($listAsString === null || $listAsString === '') {
			return [];
		} else {
			return \array_map('intval', \explode(',', (string)$listAsString));
		}
	}
}
