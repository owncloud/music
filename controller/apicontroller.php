<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2013, 2014
 * @copyright Pauli Järvinen 2017, 2018
 */

namespace OCA\Music\Controller;

use \OCP\AppFramework\Controller;
use \OCP\AppFramework\Http;
use \OCP\AppFramework\Http\DataDisplayResponse;
use \OCP\AppFramework\Http\JSONResponse;
use \OCP\AppFramework\Http\RedirectResponse;
use \OCP\Files\Folder;
use \OCP\IL10N;
use \OCP\IRequest;
use \OCP\IURLGenerator;

use \OCA\Music\AppFramework\Core\Logger;
use \OCA\Music\BusinessLayer\AlbumBusinessLayer;
use \OCA\Music\BusinessLayer\ArtistBusinessLayer;
use \OCA\Music\BusinessLayer\TrackBusinessLayer;
use \OCA\Music\Db\Artist;
use \OCA\Music\Db\Maintenance;
use \OCA\Music\Db\Track;
use \OCA\Music\Http\ErrorResponse;
use \OCA\Music\Http\FileResponse;
use \OCA\Music\Utility\CollectionHelper;
use \OCA\Music\Utility\CoverHelper;
use \OCA\Music\Utility\DetailsHelper;
use \OCA\Music\Utility\Scanner;

class ApiController extends Controller {

	/** @var IL10N */
	private $l10n;
	/** @var TrackBusinessLayer */
	private $trackBusinessLayer;
	/** @var ArtistBusinessLayer */
	private $artistBusinessLayer;
	/** @var AlbumBusinessLayer */
	private $albumBusinessLayer;
	/** @var Scanner */
	private $scanner;
	/** @var CollectionHelper */
	private $collectionHelper;
	/** @var CoverHelper */
	private $coverHelper;
	/** @var DetailsHelper */
	private $detailsHelper;
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

	public function __construct($appname,
								IRequest $request,
								IURLGenerator $urlGenerator,
								TrackBusinessLayer $trackbusinesslayer,
								ArtistBusinessLayer $artistbusinesslayer,
								AlbumBusinessLayer $albumbusinesslayer,
								Scanner $scanner,
								CollectionHelper $collectionHelper,
								CoverHelper $coverHelper,
								DetailsHelper $detailsHelper,
								Maintenance $maintenance,
								$userId,
								IL10N $l10n,
								Folder $userFolder,
								Logger $logger) {
		parent::__construct($appname, $request);
		$this->l10n = $l10n;
		$this->trackBusinessLayer = $trackbusinesslayer;
		$this->artistBusinessLayer = $artistbusinesslayer;
		$this->albumBusinessLayer = $albumbusinesslayer;
		$this->scanner = $scanner;
		$this->collectionHelper = $collectionHelper;
		$this->coverHelper = $coverHelper;
		$this->detailsHelper = $detailsHelper;
		$this->maintenance = $maintenance;
		$this->userId = $userId;
		$this->urlGenerator = $urlGenerator;
		$this->userFolder = $userFolder;
		$this->logger = $logger;
	}

	/**
	 * Extracts the id from an unique slug (id-slug)
	 * @param string $slug the slug
	 * @return string the id
	 */
	protected function getIdFromSlug($slug) {
		$split = \explode('-', $slug, 2);

		return $split[0];
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
		return new JSONResponse(['hash' => $hash]);
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
	public function artists($fulltree, $albums) {
		$fulltree = \filter_var($fulltree, FILTER_VALIDATE_BOOLEAN);
		$includeAlbums = \filter_var($albums, FILTER_VALIDATE_BOOLEAN);
		/** @var Artist[] $artists */
		$artists = $this->artistBusinessLayer->findAll($this->userId);
		foreach ($artists as &$artist) {
			$artist = $artist->toAPI($this->urlGenerator, $this->l10n);
			if ($fulltree || $includeAlbums) {
				$artistId = $artist['id'];
				$artistAlbums = $this->albumBusinessLayer->findAllByArtist($artistId, $this->userId);
				foreach ($artistAlbums as &$album) {
					$album = $album->toAPI($this->urlGenerator, $this->l10n);
					if ($fulltree) {
						$albumId = $album['id'];
						$tracks = $this->trackBusinessLayer->findAllByAlbum($albumId, $this->userId, $artistId);
						foreach ($tracks as &$track) {
							$track = $track->toAPI($this->urlGenerator);
						}
						$album['tracks'] = $tracks;
					}
				}
				$artist['albums'] = $artistAlbums;
			}
		}
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
		$artist = $artist->toAPI($this->urlGenerator, $this->l10n);
		if ($fulltree) {
			$artistId = $artist['id'];
			$albums = $this->albumBusinessLayer->findAllByArtist($artistId, $this->userId);
			foreach ($albums as &$album) {
				$album = $album->toAPI($this->urlGenerator, $this->l10n);
				$albumId = $album['id'];
				$tracks = $this->trackBusinessLayer->findAllByAlbum($albumId, $this->userId, $artistId);
				foreach ($tracks as &$track) {
					$track = $track->toAPI($this->urlGenerator);
				}
				$album['tracks'] = $tracks;
			}
			$artist['albums'] = $albums;
		}
		return new JSONResponse($artist);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function albums($artist, $fulltree) {
		$fulltree = \filter_var($fulltree, FILTER_VALIDATE_BOOLEAN);
		if ($artist) {
			$albums = $this->albumBusinessLayer->findAllByArtist($artist, $this->userId);
		} else {
			$albums = $this->albumBusinessLayer->findAll($this->userId);
		}
		foreach ($albums as &$album) {
			$artistIds = $album->getArtistIds();
			$album = $album->toAPI($this->urlGenerator, $this->l10n);
			if ($fulltree) {
				$albumId = $album['id'];
				$tracks = $this->trackBusinessLayer->findAllByAlbum($albumId, $this->userId);
				foreach ($tracks as &$track) {
					$track = $track->toAPI($this->urlGenerator);
				}
				$album['tracks'] = $tracks;
				$artists = $this->artistBusinessLayer->findMultipleById($artistIds, $this->userId);
				foreach ($artists as &$artist) {
					$artist = $artist->toAPI($this->urlGenerator, $this->l10n);
				}
				$album['artists'] = $artists;
			}
		}
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

		$artistIds = $album->getArtistIds();
		$album = $album->toAPI($this->urlGenerator, $this->l10n);
		if ($fulltree) {
			$albumId = $album['id'];
			$tracks = $this->trackBusinessLayer->findAllByAlbum($albumId, $this->userId);
			foreach ($tracks as &$track) {
				$track = $track->toAPI($this->urlGenerator);
			}
			$album['tracks'] = $tracks;
			$artists = $this->artistBusinessLayer->findMultipleById($artistIds, $this->userId);
			foreach ($artists as &$artist) {
				$artist = $artist->toAPI($this->urlGenerator, $this->l10n);
			}
			$album['artists'] = $artists;
		}

		return new JSONResponse($album);
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
	public function trackByFileId($fileId) {
		$track = $this->trackBusinessLayer->findByFileId($fileId, $this->userId);
		if ($track !== null) {
			$track->setAlbum($this->albumBusinessLayer->find($track->getAlbumId(), $this->userId));
			$track->setArtist($this->artistBusinessLayer->find($track->getArtistId(), $this->userId));
			return new JSONResponse($track->toCollection($this->l10n));
		} else {
			return new ErrorResponse(Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function getScanState() {
		return new JSONResponse([
			'unscannedFiles' => $this->scanner->getUnscannedMusicFileIds($this->userId, $this->userFolder),
			'scannedCount' => $this->trackBusinessLayer->count($this->userId)
		]);
	}

	/**
	 * @NoAdminRequired
	 * @UseSession to keep the session reserved while execution in progress
	 */
	public function scan($files, $finalize) {
		// extract the parameters
		$fileIds = \array_map('intval', \explode(',', $files));
		$finalize = \filter_var($finalize, FILTER_VALIDATE_BOOLEAN);

		$filesScanned = $this->scanner->scanFiles($this->userId, $this->userFolder, $fileIds);

		$coversUpdated = false;
		if ($finalize) {
			$coversUpdated = $this->scanner->findCovers();
			$totalCount = \count($this->scanner->getScannedFiles($this->userId));
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
	public function download($fileId) {
		$track = $this->trackBusinessLayer->findByFileId($fileId, $this->userId);
		if ($track === null) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, 'track not found');
		}

		$nodes = $this->userFolder->getById($track->getFileId());
		if (\count($nodes) > 0) {
			// get the first valid node
			$node = $nodes[0];

			$mime = $node->getMimeType();
			$content = $node->getContent();
			return new FileResponse(['mimetype' => $mime, 'content' => $content]);
		}

		return new ErrorResponse(Http::STATUS_NOT_FOUND, 'file not found');
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function filePath($fileId) {
		$nodes = $this->userFolder->getById($fileId);
		if (\count($nodes) == 0) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND);
		} else {
			$node = $nodes[0];
			$path = $this->userFolder->getRelativePath($node->getPath());
			// URL encode each part of the file path
			$path = \join('/', \array_map('rawurlencode', \explode('/', $path)));
			return new JSONResponse(['path' => $path]);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function fileInfo($fileId) {
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
	public function fileDetails($fileId) {
		$details = $this->detailsHelper->getDetails($fileId, $this->userFolder);
		if ($details) {
			return new JSONResponse($details);
		} else {
			return new ErrorResponse(Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function cover($albumIdOrSlug) {
		$albumId = $this->getIdFromSlug($albumIdOrSlug);
		$coverAndHash = $this->coverHelper->getCoverAndHash($albumId, $this->userId, $this->userFolder);

		if ($coverAndHash['hash'] !== null) {
			// Cover is in cache. Return a redirection response so that the client
			// will fetch the content through a cacheable route.
			$link = $this->urlGenerator->linkToRoute('music.api.cachedCover', ['hash' => $coverAndHash['hash']]);
			return new RedirectResponse($link);
		} else if ($coverData !== null) {
			return new FileResponse($coverAndHash['data']);
		} else {
			return new ErrorResponse(Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function cachedCover($hash) {
		$coverData = $this->coverHelper->getCoverFromCache($hash, $this->userId);

		if ($coverData !== null) {
			$response =  new FileResponse($coverData);
			// instruct also the client-side to cache the result, this is safe
			// as the resource URI contains the image hash
			self::setClientCaching($response);
			return $response;
		} else {
			return new ErrorResponse(Http::STATUS_NOT_FOUND);
		}
	}

	private static function setClientCaching(&$httpResponse, $days=365) {
		$httpResponse->cacheFor($days * 24 * 60 * 60);
		$httpResponse->addHeader('Pragma', 'cache');
	}
}
