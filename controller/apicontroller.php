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
 * @copyright Pauli Järvinen 2017
 */

namespace OCA\Music\Controller;

use \OCP\AppFramework\Controller;
use \OCP\AppFramework\Http;
use \OCP\AppFramework\Http\DataDisplayResponse;
use \OCP\AppFramework\Http\JSONResponse;
use \OCP\Files\Folder;
use \OCP\IL10N;
use \OCP\IRequest;
use \OCP\IURLGenerator;

use \Doctrine\DBAL\Exception\UniqueConstraintViolationException;

use \OCA\Music\AppFramework\Core\Logger;
use \OCA\Music\BusinessLayer\AlbumBusinessLayer;
use \OCA\Music\BusinessLayer\ArtistBusinessLayer;
use \OCA\Music\BusinessLayer\TrackBusinessLayer;
use \OCA\Music\Db\Artist;
use \OCA\Music\Db\Cache;
use \OCA\Music\Db\Track;
use \OCA\Music\Http\ErrorResponse;
use \OCA\Music\Http\FileResponse;
use \OCA\Music\Utility\CoverHelper;
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
	/** @var Cache */
	private $cache;
	/** @var Scanner */
	private $scanner;
	/** @var CoverHelper */
	private $coverHelper;
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
								Cache $cache,
								Scanner $scanner,
								CoverHelper $coverHelper,
								$userId,
								IL10N $l10n,
								Folder $userFolder,
								Logger $logger){
		parent::__construct($appname, $request);
		$this->l10n = $l10n;
		$this->trackBusinessLayer = $trackbusinesslayer;
		$this->artistBusinessLayer = $artistbusinesslayer;
		$this->albumBusinessLayer = $albumbusinesslayer;
		$this->cache = $cache;
		$this->scanner = $scanner;
		$this->coverHelper = $coverHelper;
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
	protected function getIdFromSlug($slug){
		$split = explode('-', $slug, 2);

		return $split[0];
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function collection() {
		$collectionJson = $this->cache->get($this->userId, 'collection');

		if ($collectionJson === null) {
			$collectionJson = $this->buildCollectionJson();
			try {
				$this->cache->add($this->userId, 'collection', $collectionJson);
			} catch (UniqueConstraintViolationException $ex) {
				$this->logger->log("Race condition: collection.json for user {$this->userId} ".
						"cached twice, ignoring latter.", 'warn');
			}
		}

		$response = new DataDisplayResponse($collectionJson);
		$response->addHeader('Content-Type', 'application/json; charset=utf-8');
		return $response;
	}

	private function buildCollectionJson() {
		// Get all the entities from the DB first. The order of queries is important if we are in
		// the middle of a scanning process: we don't want to get tracks which do not yet have
		// an album entry or albums which do not yet have artist entry.
		/** @var Track[] $allTracks */
		$allTracks = $this->trackBusinessLayer->findAll($this->userId);
		/** @var Album[] $allAlbums */
		$allAlbums = $this->albumBusinessLayer->findAll($this->userId);
		/** @var Artist[] $allArtists */
		$allArtists = $this->artistBusinessLayer->findAll($this->userId);

		$allArtistsByIdAsObj = array();
		$allArtistsByIdAsArr = array();
		foreach ($allArtists as &$artist) {
			$artistId = $artist->getId();
			$allArtistsByIdAsObj[$artistId] = $artist;
			$allArtistsByIdAsArr[$artistId] = $artist->toCollection($this->l10n);
		}

		$allAlbumsByIdAsObj = array();
		$allAlbumsByIdAsArr = array();
		$coverHashes = $this->coverHelper->getAllCachedCoverHashes($this->userId);
		foreach ($allAlbums as &$album) {
			$albumId = $album->getId();
			$allAlbumsByIdAsObj[$albumId] = $album;
			$coverHash = isset($coverHashes[$albumId]) ? $coverHashes[$albumId] : null;
			$allAlbumsByIdAsArr[$albumId] = $album->toCollection(
					$this->urlGenerator, $this->l10n, $coverHash);
		}

		$artists = array();
		foreach ($allTracks as $track) {
			$albumObj = $allAlbumsByIdAsObj[$track->getAlbumId()];
			$trackArtistObj = $allArtistsByIdAsObj[$track->getArtistId()];
			$albumArtist = &$allArtistsByIdAsArr[$albumObj->getAlbumArtistId()];

			if (empty($albumObj) || empty($trackArtistObj) || empty($albumArtist)) {
				$this->logger->log("DB error on track {$track->id} '{$track->title}': ".
						"album or artist missing. Skipping the track.", 'warn');
			}
			else {
				$track->setAlbum($albumObj);
				$track->setArtist($trackArtistObj);

				if (!isset($albumArtist['albums'])) {
					$albumArtist['albums'] = array();
					$artists[] = &$albumArtist;
				}
				$album = &$allAlbumsByIdAsArr[$track->getAlbumId()];
				if (!isset($album['tracks'])) {
					$album['tracks'] = array();
					$albumArtist['albums'][] = &$album;
				}
				$album['tracks'][] = $track->toCollection($this->l10n);
			}
		}
		return json_encode($artists);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function artists($fulltree, $albums) {
		$fulltree = filter_var($fulltree, FILTER_VALIDATE_BOOLEAN);
		$includeAlbums = filter_var($albums, FILTER_VALIDATE_BOOLEAN);
		/** @var Artist[] $artists */
		$artists = $this->artistBusinessLayer->findAll($this->userId);
		foreach($artists as &$artist) {
			$artist = $artist->toAPI($this->urlGenerator, $this->l10n);
			if($fulltree || $includeAlbums) {
				$artistId = $artist['id'];
				$artistAlbums = $this->albumBusinessLayer->findAllByArtist($artistId, $this->userId);
				foreach($artistAlbums as &$album) {
					$album = $album->toAPI($this->urlGenerator, $this->l10n);
					if($fulltree) {
						$albumId = $album['id'];
						$tracks = $this->trackBusinessLayer->findAllByAlbum($albumId, $this->userId, $artistId);
						foreach($tracks as &$track) {
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
		$fulltree = filter_var($fulltree, FILTER_VALIDATE_BOOLEAN);
		$artistId = $this->getIdFromSlug($artistIdOrSlug);
		/** @var Artist $artist */
		$artist = $this->artistBusinessLayer->find($artistId, $this->userId);
		$artist = $artist->toAPI($this->urlGenerator, $this->l10n);
		if($fulltree) {
			$artistId = $artist['id'];
			$albums = $this->albumBusinessLayer->findAllByArtist($artistId, $this->userId);
			foreach($albums as &$album) {
				$album = $album->toAPI($this->urlGenerator, $this->l10n);
				$albumId = $album['id'];
				$tracks = $this->trackBusinessLayer->findAllByAlbum($albumId, $this->userId, $artistId);
				foreach($tracks as &$track) {
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
	public function albums($fulltree) {
		$fulltree = filter_var($fulltree, FILTER_VALIDATE_BOOLEAN);
		$albums = $this->albumBusinessLayer->findAll($this->userId);
		foreach($albums as &$album) {
			$artistIds = $album->getArtistIds();
			$album = $album->toAPI($this->urlGenerator, $this->l10n);
			if($fulltree) {
				$albumId = $album['id'];
				$tracks = $this->trackBusinessLayer->findAllByAlbum($albumId, $this->userId);
				foreach($tracks as &$track) {
					$track = $track->toAPI($this->urlGenerator);
				}
				$album['tracks'] = $tracks;
				$artists = $this->artistBusinessLayer->findMultipleById($artistIds, $this->userId);
				foreach($artists as &$artist) {
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
		$fulltree = filter_var($fulltree, FILTER_VALIDATE_BOOLEAN);
		$albumId = $this->getIdFromSlug($albumIdOrSlug);
		$album = $this->albumBusinessLayer->find($albumId, $this->userId);

		$artistIds = $album->getArtistIds();
		$album = $album->toAPI($this->urlGenerator, $this->l10n);
		if($fulltree) {
			$albumId = $album['id'];
			$tracks = $this->trackBusinessLayer->findAllByAlbum($albumId, $this->userId);
			foreach($tracks as &$track) {
				$track = $track->toAPI($this->urlGenerator);
			}
			$album['tracks'] = $tracks;
			$artists = $this->artistBusinessLayer->findMultipleById($artistIds, $this->userId);
			foreach($artists as &$artist) {
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
		$fulltree = filter_var($fulltree, FILTER_VALIDATE_BOOLEAN);
		if($artist) {
			$tracks = $this->trackBusinessLayer->findAllByArtist($artist, $this->userId);
		} elseif($album) {
			$tracks = $this->trackBusinessLayer->findAllByAlbum($album, $this->userId);
		} else {
			$tracks = $this->trackBusinessLayer->findAll($this->userId);
		}
		foreach($tracks as &$track) {
			$artistId = $track->getArtistId();
			$albumId = $track->getAlbumId();
			$track = $track->toAPI($this->urlGenerator);
			if($fulltree) {
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
	 * @NoCSRFRequired
	 */
	public function fileWebDavUrl($fileId) {
		$nodes = $this->userFolder->getById($fileId);
		if (count($nodes) == 0) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND);
		}
		else {
			$node = $nodes[0];
			$relativePath = $this->userFolder->getRelativePath($node->getPath());
			// URL encode each part of the file path
			$relativePath = join('/', array_map('rawurlencode', explode('/', $relativePath)));
			$url = $this->urlGenerator->getAbsoluteUrl('remote.php/webdav' . $relativePath);
			return new JSONResponse(['url' => $url]);
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
	 */
	public function scan($files, $finalize) {
		// extract the parameters
		$fileIds = array_map('intval', explode(',', $files));
		$finalize = filter_var($finalize, FILTER_VALIDATE_BOOLEAN);

		$filesScanned = $this->scanner->scanFiles($this->userId, $this->userFolder, $fileIds);

		$coversUpdated = false;
		if ($finalize) {
			$coversUpdated = $this->scanner->findCovers();
			$totalCount = count($this->scanner->getScannedFiles($this->userId));
			$this->logger->log("Scanning finished, user $this->userId has $totalCount scanned tracks in total", 'info');
		}

		return new JSONResponse([
			'filesScanned' => $filesScanned,
			'coversUpdated' => $coversUpdated
		]);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function download($fileId) {
		// we no longer need the session to be kept open
		session_write_close();

		$track = $this->trackBusinessLayer->findByFileId($fileId, $this->userId);
		if ($track === null) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND, 'track not found');
		}

		$nodes = $this->userFolder->getById($track->getFileId());
		if(count($nodes) > 0 ) {
			// get the first valid node
			$node = $nodes[0];

			$mime = $node->getMimeType();
			$content = $node->getContent();
			return new FileResponse(array('mimetype' => $mime, 'content' => $content));
		}

		return new ErrorResponse(Http::STATUS_NOT_FOUND, 'file not found');
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function fileInfo($fileId) {
		// we no longer need the session to be kept open
		session_write_close();

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
	public function cover($albumIdOrSlug) {
		// we no longer need the session to be kept open
		session_write_close();

		$albumId = $this->getIdFromSlug($albumIdOrSlug);
		$coverData = $this->coverHelper->getCover($albumId, $this->userId, $this->userFolder);

		if ($coverData !== NULL) {
			return new FileResponse($coverData);
		} else {
			return new ErrorResponse(Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function cachedCover($hash) {
		// we no longer need the session to be kept open
		session_write_close();

		$coverData = $this->coverHelper->getCoverFromCache($hash, $this->userId);

		if ($coverData !== NULL) {
			$response =  new FileResponse($coverData);
			// instruct also the client-side to cache the result, this is safe
			// as the resource URI contains the image hash
			$response->cacheFor(31536000); // 1 year as seconds
			$response->addHeader('Pragma', 'cache');
			return $response;
		} else {
			return new ErrorResponse(Http::STATUS_NOT_FOUND);
		}
	}
}
