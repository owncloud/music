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
 * @copyright Pauli Järvinen 2017 - 2020
 */

namespace OCA\Music\Controller;

use \OCP\AppFramework\Controller;
use \OCP\AppFramework\Http;
use \OCP\AppFramework\Http\DataDisplayResponse;
use \OCP\AppFramework\Http\JSONResponse;
use \OCP\AppFramework\Http\RedirectResponse;
use \OCP\AppFramework\Http\Response;
use \OCP\Files\Folder;
use \OCP\IL10N;
use \OCP\IRequest;
use \OCP\IURLGenerator;

use \OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use \OCA\Music\AppFramework\Core\Logger;
use \OCA\Music\BusinessLayer\AlbumBusinessLayer;
use \OCA\Music\BusinessLayer\ArtistBusinessLayer;
use \OCA\Music\BusinessLayer\GenreBusinessLayer;
use \OCA\Music\BusinessLayer\TrackBusinessLayer;
use \OCA\Music\Db\Album;
use \OCA\Music\Db\Artist;
use \OCA\Music\Db\Maintenance;
use \OCA\Music\Db\Track;
use \OCA\Music\Http\ErrorResponse;
use \OCA\Music\Http\FileResponse;
use \OCA\Music\Utility\CollectionHelper;
use \OCA\Music\Utility\CoverHelper;
use \OCA\Music\Utility\DetailsHelper;
use \OCA\Music\Utility\LastfmService;
use \OCA\Music\Utility\Scanner;
use \OCA\Music\Utility\Util;

class ApiController extends Controller {

	/** @var IL10N */
	private $l10n;
	/** @var TrackBusinessLayer */
	private $trackBusinessLayer;
	/** @var ArtistBusinessLayer */
	private $artistBusinessLayer;
	/** @var AlbumBusinessLayer */
	private $albumBusinessLayer;
	/** @var GenreBusinessLayer */
	private $genreBusinessLayer;
	/** @var Scanner */
	private $scanner;
	/** @var CollectionHelper */
	private $collectionHelper;
	/** @var CoverHelper */
	private $coverHelper;
	/** @var DetailsHelper */
	private $detailsHelper;
	/** @var LastfmService */
	private $lastfmService;
	/** @var Maintenance */
	private $maintenance;
	/** @var string */
	private $userId;
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var Folder */
	private $userFolder;
	/** @var Logger */
	private $logger;

	public function __construct(string $appname,
								IRequest $request,
								IURLGenerator $urlGenerator,
								TrackBusinessLayer $trackbusinesslayer,
								ArtistBusinessLayer $artistbusinesslayer,
								AlbumBusinessLayer $albumbusinesslayer,
								GenreBusinessLayer $genreBusinessLayer,
								Scanner $scanner,
								CollectionHelper $collectionHelper,
								CoverHelper $coverHelper,
								DetailsHelper $detailsHelper,
								LastfmService $lastfmService,
								Maintenance $maintenance,
								?string $userId, // null if this gets called after the user has logged out
								IL10N $l10n,
								?Folder $userFolder, // null if this gets called after the user has logged out
								Logger $logger) {
		parent::__construct($appname, $request);
		$this->l10n = $l10n;
		$this->trackBusinessLayer = $trackbusinesslayer;
		$this->artistBusinessLayer = $artistbusinesslayer;
		$this->albumBusinessLayer = $albumbusinesslayer;
		$this->genreBusinessLayer = $genreBusinessLayer;
		$this->scanner = $scanner;
		$this->collectionHelper = $collectionHelper;
		$this->coverHelper = $coverHelper;
		$this->detailsHelper = $detailsHelper;
		$this->lastfmService = $lastfmService;
		$this->maintenance = $maintenance;
		$this->userId = $userId;
		$this->urlGenerator = $urlGenerator;
		$this->userFolder = $userFolder;
		$this->logger = $logger;
	}

	/**
	 * Extracts the id from an unique slug (id-slug)
	 * @param string|int $slug the slug
	 * @return int the id
	 */
	protected static function getIdFromSlug($slug) : int {
		if (\is_string($slug)) {
			$split = \explode('-', $slug, 2);
			return (int)$split[0];
		} elseif (\is_int($slug)) {
			return $slug;
		} else {
			throw new \InvalidArgumentException();
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function prepareCollection() {
		$hash = $this->collectionHelper->getCachedJsonHash();
		if ($hash === null) {
			// build the collection but ignore the data for now
			$this->collectionHelper->getJson();
			$hash = $this->collectionHelper->getCachedJsonHash();
		}
		$coverToken = $this->coverHelper->createAccessToken($this->userId);

		return new JSONResponse([
			'hash' => $hash,
			'cover_token' => $coverToken
		]);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function collection() {
		$collectionJson = $this->collectionHelper->getJson();
		$response = new DataDisplayResponse($collectionJson);
		$response->addHeader('Content-Type', 'application/json; charset=utf-8');

		// Instruct the client to cache the result in case it requested the collection with
		// the correct hash. The hash could be incorrect if the collection would have changed
		// between calls to prepareCollection() and colletion().
		$requestHash = $this->request->getParam('hash');
		$actualHash = $this->collectionHelper->getCachedJsonHash();
		if (!empty($actualHash) && $requestHash === $actualHash) {
			self::setClientCaching($response, 90); // cache for 3 months
		}

		return $response;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function folders() {
		$folders = $this->trackBusinessLayer->findAllFolders($this->userId, $this->userFolder);
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
			'genres' => \array_map(function ($g) {
				return $g->toApi();
			}, $genres),
			'unscanned' => $unscanned
		]);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function artists($fulltree, $albums) {
		$fulltree = \filter_var($fulltree, FILTER_VALIDATE_BOOLEAN);
		$includeAlbums = \filter_var($albums, FILTER_VALIDATE_BOOLEAN);
		/** @var Artist[] $artists */
		$artists = $this->artistBusinessLayer->findAll($this->userId);

		$artists = \array_map(function ($a) use ($fulltree, $includeAlbums) {
			return $this->artistToApi($a, $includeAlbums || $fulltree, $fulltree);
		}, $artists);

		return new JSONResponse($artists);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function artist($artistIdOrSlug, $fulltree) {
		$fulltree = \filter_var($fulltree, FILTER_VALIDATE_BOOLEAN);
		$artistId = $this->getIdFromSlug($artistIdOrSlug);
		/** @var Artist $artist */
		$artist = $this->artistBusinessLayer->find($artistId, $this->userId);
		$artist = $this->artistToApi($artist, $fulltree, $fulltree);
		return new JSONResponse($artist);
	}

	/**
	 * Return given artist in Shia API format
	 * @param Artist $artist
	 * @param boolean $includeAlbums
	 * @param boolean $includeTracks (ignored if $includeAlbums==false)
	 * @return array
	 */
	private function artistToApi(Artist $artist, bool $includeAlbums, bool $includeTracks) : array {
		$artistInApi = $artist->toAPI($this->urlGenerator, $this->l10n);
		if ($includeAlbums) {
			$artistId = $artist->getId();
			$albums = $this->albumBusinessLayer->findAllByArtist($artistId, $this->userId);

			$artistInApi['albums'] = \array_map(function ($a) use ($includeTracks) {
				return $this->albumToApi($a, $includeTracks, false);
			}, $albums);
		}
		return $artistInApi;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function albums(?int $artist, $fulltree) {
		$fulltree = \filter_var($fulltree, FILTER_VALIDATE_BOOLEAN);
		if ($artist !== null) {
			$albums = $this->albumBusinessLayer->findAllByArtist($artist, $this->userId);
		} else {
			$albums = $this->albumBusinessLayer->findAll($this->userId);
		}

		$albums = \array_map(function ($a) use ($fulltree) {
			return $this->albumToApi($a, $fulltree, $fulltree);
		}, $albums);

		return new JSONResponse($albums);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function album($albumIdOrSlug, $fulltree) {
		$fulltree = \filter_var($fulltree, FILTER_VALIDATE_BOOLEAN);
		$albumId = $this->getIdFromSlug($albumIdOrSlug);
		$album = $this->albumBusinessLayer->find($albumId, $this->userId);
		$album = $this->albumToApi($album, $fulltree, $fulltree);
		return new JSONResponse($album);
	}

	/**
	 * Return given album in the Shiva API format
	 * @param Album $album
	 * @param boolean $includeTracks
	 * @param boolean $includeAlbums
	 * @return array
	 */
	private function albumToApi(Album $album, bool $includeTracks, bool $includeArtists) : array {
		$albumInApi = $album->toAPI($this->urlGenerator, $this->l10n);

		if ($includeTracks) {
			$albumId = $album->getId();
			$tracks = $this->trackBusinessLayer->findAllByAlbum($albumId, $this->userId);
			$albumInApi['tracks'] = \array_map(function ($t) {
				return $t->toAPI($this->urlGenerator);
			}, $tracks);
		}

		if ($includeArtists) {
			$artistIds = $album->getArtistIds();
			$artists = $this->artistBusinessLayer->findById($artistIds, $this->userId);
			$albumInApi['artists'] = \array_map(function ($a) {
				return $a->toAPI($this->urlGenerator, $this->l10n);
			}, $artists);
		}

		return $albumInApi;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function tracks($artist, $album, $fulltree) {
		$fulltree = \filter_var($fulltree, FILTER_VALIDATE_BOOLEAN);
		if ($artist) {
			$tracks = $this->trackBusinessLayer->findAllByArtist($artist, $this->userId);
		} elseif ($album) {
			$tracks = $this->trackBusinessLayer->findAllByAlbum($album, $this->userId);
		} else {
			$tracks = $this->trackBusinessLayer->findAll($this->userId);
		}
		foreach ($tracks as &$track) {
			$artistId = $track->getArtistId();
			$albumId = $track->getAlbumId();
			$track = $track->toAPI($this->urlGenerator);
			if ($fulltree) {
				/** @var Artist $artist */
				$artist = $this->artistBusinessLayer->find($artistId, $this->userId);
				$track['artist'] = $artist->toAPI($this->urlGenerator, $this->l10n);
				$album = $this->albumBusinessLayer->find($albumId, $this->userId);
				$track['album'] = $album->toAPI($this->urlGenerator, $this->l10n);
			}
		}
		return new JSONResponse($tracks);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function track($trackIdOrSlug) {
		$trackId = $this->getIdFromSlug($trackIdOrSlug);
		/** @var Track $track */
		$track = $this->trackBusinessLayer->find($trackId, $this->userId);
		return new JSONResponse($track->toAPI($this->urlGenerator));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function trackByFileId(int $fileId) {
		$track = $this->trackBusinessLayer->findByFileId($fileId, $this->userId);
		if ($track !== null) {
			return new JSONResponse($track->toCollection($this->l10n));
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
			'scannedCount' => $this->trackBusinessLayer->count($this->userId)
		]);
	}

	/**
	 * @NoAdminRequired
	 * @UseSession to keep the session reserved while execution in progress
	 */
	public function scan(string $files, $finalize) {
		// extract the parameters
		$fileIds = \array_map('intval', \explode(',', $files));
		$finalize = \filter_var($finalize, FILTER_VALIDATE_BOOLEAN);

		$filesScanned = $this->scanner->scanFiles($this->userId, $this->userFolder, $fileIds);

		$coversUpdated = false;
		if ($finalize) {
			$coversUpdated = $this->scanner->findAlbumCovers($this->userId)
							|| $this->scanner->findArtistCovers($this->userId);
			$totalCount = $this->trackBusinessLayer->count($this->userId);
			$this->logger->log("Scanning finished, user $this->userId has $totalCount scanned tracks in total", 'info');
		}

		return new JSONResponse([
			'filesScanned' => $filesScanned,
			'coversUpdated' => $coversUpdated
		]);
	}

	/**
	 * @NoAdminRequired
	 * @UseSession to keep the session reserved while execution in progress
	 */
	public function resetScanned() {
		$this->maintenance->resetDb($this->userId);
		return new JSONResponse(['success' => true]);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function download(int $fileId) {
		$nodes = $this->userFolder->getById($fileId);
		$node = $nodes[0] ?? null;
		if ($node instanceof \OCP\Files\File) {
			return new FileResponse($node);
		}

		return new ErrorResponse(Http::STATUS_NOT_FOUND, 'file not found');
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function filePath(int $fileId) {
		$nodes = $this->userFolder->getById($fileId);
		if (\count($nodes) == 0) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND);
		} else {
			$node = $nodes[0];
			$path = $this->userFolder->getRelativePath($node->getPath());
			return new JSONResponse(['path' => Util::urlEncodePath($path)]);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function fileInfo(int $fileId) {
		$info = $this->scanner->getFileInfo($fileId, $this->userId, $this->userFolder);
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
		$details = $this->detailsHelper->getDetails($fileId, $this->userFolder);
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
	public function albumDetails($albumIdOrSlug) {
		try {
			$albumId = $this->getIdFromSlug($albumIdOrSlug);
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
	public function artistDetails($artistIdOrSlug) {
		try {
			$artistId = $this->getIdFromSlug($artistIdOrSlug);
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
	public function similarArtists($artistIdOrSlug) {
		try {
			$artistId = $this->getIdFromSlug($artistIdOrSlug);
			$similar = $this->lastfmService->getSimilarArtists($artistId, $this->userId, /*includeNotPresent=*/true);
			return new JSONResponse(\array_map(function ($artist) {
				return [
					'id' => $artist->getId(),
					'name' => $artist->getName(),
					'url' => $artist->getLastfmUrl()
				];
			}, $similar));
		} catch (BusinessLayerException $e) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 */
	public function albumCover($albumIdOrSlug, $originalSize, $coverToken) {
		try {
			$userId = $this->userId ?? $this->coverHelper->getUserForAccessToken($coverToken);
			$albumId = $this->getIdFromSlug($albumIdOrSlug);
			$album = $this->albumBusinessLayer->find($albumId, $userId);
			return $this->cover($album, $userId, $originalSize);
		} catch (BusinessLayerException | \OutOfBoundsException $ex) {
			$this->logger->log("Failed to get the requested cover: $ex", 'debug');
			return new ErrorResponse(Http::STATUS_NOT_FOUND);
		} 
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 */
	public function artistCover($artistIdOrSlug, $originalSize, $coverToken) {
		try {
			$userId = $this->userId ?? $this->coverHelper->getUserForAccessToken($coverToken);
			$artistId = $this->getIdFromSlug($artistIdOrSlug);
			$artist = $this->artistBusinessLayer->find($artistId, $userId);
			return $this->cover($artist, $userId, $originalSize);
		} catch (BusinessLayerException | \OutOfBoundsException $ex) {
			$this->logger->log("Failed to get the requested cover: $ex", 'debug');
			return new ErrorResponse(Http::STATUS_NOT_FOUND);
		}
	}

	private function cover($entity, $userId, $originalSize) {
		$originalSize = \filter_var($originalSize, FILTER_VALIDATE_BOOLEAN);
		$userFolder = $this->userFolder ?? $this->scanner->resolveUserFolder($userId);

		if ($originalSize) {
			// cover requested in original size, without scaling or cropping
			$cover = $this->coverHelper->getCover($entity, $userId, $userFolder, CoverHelper::DO_NOT_CROP_OR_SCALE);
			if ($cover !== null) {
				return new FileResponse($cover);
			} else {
				return new ErrorResponse(Http::STATUS_NOT_FOUND);
			}
		} else {
			$coverAndHash = $this->coverHelper->getCoverAndHash($entity, $userId, $userFolder);

			if ($coverAndHash['hash'] !== null && $this->userId !== null) {
				// Cover is in cache. Return a redirection response so that the client
				// will fetch the content through a cacheable route.
				// The redirection is not used in case this is a call from the Firefox mediaSession API with not
				// logged in user.
				$link = $this->urlGenerator->linkToRoute('music.api.cachedCover', ['hash' => $coverAndHash['hash']]);
				return new RedirectResponse($link);
			} elseif ($coverAndHash['data'] !== null) {
				return new FileResponse($coverAndHash['data']);
			} else {
				return new ErrorResponse(Http::STATUS_NOT_FOUND);
			}
		}
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 */
	public function cachedCover(string $hash, ?string $coverToken) {
		try {
			$userId = $this->userId ?? $this->coverHelper->getUserForAccessToken($coverToken);
			$coverData = $this->coverHelper->getCoverFromCache($hash, $userId);
			if ($coverData === null) {
				throw new \OutOfBoundsException("Cover with hash $hash not found");
			}
			$response =  new FileResponse($coverData);
			// instruct also the client-side to cache the result, this is safe
			// as the resource URI contains the image hash
			self::setClientCaching($response);
			return $response;
		} catch (\OutOfBoundsException $ex) {
			$this->logger->log("Failed to get the requested cover: $ex", 'debug');
			return new ErrorResponse(Http::STATUS_NOT_FOUND);
		}
	}

	private static function setClientCaching(Response &$httpResponse, int $days=365) : void {
		$httpResponse->cacheFor($days * 24 * 60 * 60);
		$httpResponse->addHeader('Pragma', 'cache');
	}
}
