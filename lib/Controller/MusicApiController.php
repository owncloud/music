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
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

use OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use OCA\Music\AppFramework\Core\Logger;
use OCA\Music\BusinessLayer\GenreBusinessLayer;
use OCA\Music\BusinessLayer\TrackBusinessLayer;
use OCA\Music\Db\Maintenance;
use OCA\Music\Http\ErrorResponse;
use OCA\Music\Http\FileStreamResponse;
use OCA\Music\Service\CollectionService;
use OCA\Music\Service\CoverService;
use OCA\Music\Service\DetailsService;
use OCA\Music\Service\LastfmService;
use OCA\Music\Service\LibrarySettings;
use OCA\Music\Service\Scanner;
use OCA\Music\Utility\HttpUtil;
use OCA\Music\Utility\Util;

class MusicApiController extends Controller {

	private TrackBusinessLayer $trackBusinessLayer;
	private GenreBusinessLayer $genreBusinessLayer;
	private Scanner $scanner;
	private CollectionService $collectionService;
	private CoverService $coverService;
	private DetailsService $detailsService;
	private LastfmService $lastfmService;
	private Maintenance $maintenance;
	private LibrarySettings $librarySettings;
	private string $userId;
	private Logger $logger;

	public function __construct(string $appname,
								IRequest $request,
								TrackBusinessLayer $trackBusinessLayer,
								GenreBusinessLayer $genreBusinessLayer,
								Scanner $scanner,
								CollectionService $collectionService,
								CoverService $coverService,
								DetailsService $detailsService,
								LastfmService $lastfmService,
								Maintenance $maintenance,
								LibrarySettings $librarySettings,
								?string $userId,
								Logger $logger) {
		parent::__construct($appname, $request);
		$this->trackBusinessLayer = $trackBusinessLayer;
		$this->genreBusinessLayer = $genreBusinessLayer;
		$this->scanner = $scanner;
		$this->collectionService = $collectionService;
		$this->coverService = $coverService;
		$this->detailsService = $detailsService;
		$this->lastfmService = $lastfmService;
		$this->maintenance = $maintenance;
		$this->librarySettings = $librarySettings;
		$this->userId = $userId ?? ''; // null case should happen only when the user has already logged out
		$this->logger = $logger;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function prepareCollection() {
		$hash = $this->collectionService->getCachedJsonHash();
		if ($hash === null) {
			// build the collection but ignore the data for now
			$this->collectionService->getJson();
			$hash = $this->collectionService->getCachedJsonHash();
		}
		$coverToken = $this->coverService->createAccessToken($this->userId);

		return new JSONResponse([
			'hash' => $hash,
			'cover_token' => $coverToken,
			'ignored_articles' => $this->librarySettings->getIgnoredArticles($this->userId)
		]);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function collection() {
		$collectionJson = $this->collectionService->getJson();
		$response = new DataDisplayResponse($collectionJson);
		$response->addHeader('Content-Type', 'application/json; charset=utf-8');

		// Instruct the client to cache the result in case it requested the collection with
		// the correct hash. The hash could be incorrect if the collection would have changed
		// between calls to prepareCollection() and collection().
		$requestHash = $this->request->getParam('hash');
		$actualHash = $this->collectionService->getCachedJsonHash();
		if (!empty($actualHash) && $requestHash === $actualHash) {
			HttpUtil::setClientCachingDays($response, 90);
		}

		return $response;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function folders() {
		$musicFolder = $this->librarySettings->getFolder($this->userId);
		$folders = $this->trackBusinessLayer->findAllFolders($this->userId, $musicFolder);
		return new JSONResponse($folders);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function genres() {
		$genres = $this->genreBusinessLayer->findAllWithTrackIds($this->userId);
		$unscanned =  $this->trackBusinessLayer->findFilesWithoutScannedGenre($this->userId);
		return new JSONResponse([
			'genres' => \array_map(fn($g) => $g->toApi(), $genres),
			'unscanned' => $unscanned
		]);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function trackByFileId(int $fileId) {
		$track = $this->trackBusinessLayer->findByFileId($fileId, $this->userId);
		if ($track !== null) {
			return new JSONResponse($track->toCollection());
		} else {
			return new ErrorResponse(Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function getScanState() {
		return new JSONResponse([
			'unscannedFiles' => $this->scanner->getUnscannedMusicFileIds($this->userId),
			'dirtyFiles' => $this->scanner->getDirtyMusicFileIds($this->userId),
			'scannedCount' => $this->trackBusinessLayer->count($this->userId)
		]);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @UseSession to keep the session reserved while execution in progress
	 */
	public function scan(string $files, $finalize) {
		// extract the parameters
		$fileIds = \array_map('intval', \explode(',', $files));
		$finalize = \filter_var($finalize, FILTER_VALIDATE_BOOLEAN);

		list('count' => $filesScanned) = $this->scanner->scanFiles($this->userId, $fileIds);

		$albumCoversUpdated = false;
		if ($finalize) {
			$albumCoversUpdated = $this->scanner->findAlbumCovers($this->userId);
			$this->scanner->findArtistCovers($this->userId);
			$totalCount = $this->trackBusinessLayer->count($this->userId);
			$this->logger->log("Scanning finished, user $this->userId has $totalCount scanned tracks in total", 'info');
		}

		return new JSONResponse([
			'filesScanned' => $filesScanned,
			'albumCoversUpdated' => $albumCoversUpdated
		]);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @UseSession to keep the session reserved while execution in progress
	 */
	public function resetScanned() {
		$this->maintenance->resetLibrary($this->userId);
		return new JSONResponse(['success' => true]);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function download(int $fileId) {
		$nodes = $this->scanner->resolveUserFolder($this->userId)->getById($fileId);
		$node = $nodes[0] ?? null;
		if ($node instanceof \OCP\Files\File) {
			return new FileStreamResponse($node);
		}

		return new ErrorResponse(Http::STATUS_NOT_FOUND, 'file not found');
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function filePath(int $fileId) {
		$userFolder = $this->scanner->resolveUserFolder($this->userId);
		$nodes = $userFolder->getById($fileId);
		if (\count($nodes) == 0) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND);
		} else {
			$node = $nodes[0];
			$path = $userFolder->getRelativePath($node->getPath());
			return new JSONResponse(['path' => Util::urlEncodePath($path)]);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function fileInfo(int $fileId) {
		$userFolder = $this->scanner->resolveUserFolder($this->userId);
		$info = $this->scanner->getFileInfo($fileId, $this->userId, $userFolder);
		if ($info) {
			return new JSONResponse($info);
		} else {
			return new ErrorResponse(Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function fileDetails(int $fileId) {
		$userFolder = $this->scanner->resolveUserFolder($this->userId);
		$details = $this->detailsService->getDetails($fileId, $userFolder);
		if ($details) {
			// metadata extracted, attempt to include also the data from Last.fm
			$track = $this->trackBusinessLayer->findByFileId($fileId, $this->userId);
			if ($track) {
				$details['lastfm'] = $this->lastfmService->getTrackInfo($track->getId(), $this->userId);
			} else {
				$this->logger->log("Track with file ID $fileId was not found => can't fetch info from Last.fm", 'warn');
			}

			return new JSONResponse($details);
		} else {
			return new ErrorResponse(Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function fileLyrics(int $fileId, ?string $format) {
		$userFolder = $this->scanner->resolveUserFolder($this->userId);
		if ($format == 'plaintext') {
			$lyrics = $this->detailsService->getLyricsAsPlainText($fileId, $userFolder);
			if (!empty($lyrics)) {
				return new DataDisplayResponse($lyrics, Http::STATUS_OK, ['Content-Type' => 'text/plain; charset=utf-8']);
			}
		} else {
			$lyrics = $this->detailsService->getLyricsAsStructured($fileId, $userFolder);
			if (!empty($lyrics)) {
				return new JSONResponse($lyrics);
			}
		}
		return new ErrorResponse(Http::STATUS_NOT_FOUND);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function scrobble(int $trackId) {
		try {
			$this->trackBusinessLayer->recordTrackPlayed($trackId, $this->userId);
			return new JSONResponse(['success' => true]);
		} catch (BusinessLayerException $e) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function albumDetails(int $albumId) {
		try {
			$info = $this->lastfmService->getAlbumInfo($albumId, $this->userId);
			return new JSONResponse($info);
		} catch (BusinessLayerException $e) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function artistDetails(int $artistId) {
		try {
			$info = $this->lastfmService->getArtistInfo($artistId, $this->userId);
			return new JSONResponse($info);
		} catch (BusinessLayerException $e) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function similarArtists(int $artistId) {
		try {
			$similar = $this->lastfmService->getSimilarArtists($artistId, $this->userId, /*includeNotPresent=*/true);
			return new JSONResponse(\array_map(fn($artist) => [
				'id' => $artist->getId(),
				'name' => $artist->getName(),
				'url' => $artist->getLastfmUrl()
			], $similar));
		} catch (BusinessLayerException $e) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND);
		}
	}
}
