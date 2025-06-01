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
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;

use OCA\Music\AppFramework\BusinessLayer\BusinessLayer;
use OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use OCA\Music\AppFramework\Core\Logger;
use OCA\Music\BusinessLayer\AlbumBusinessLayer;
use OCA\Music\BusinessLayer\ArtistBusinessLayer;
use OCA\Music\BusinessLayer\TrackBusinessLayer;
use OCA\Music\Db\Album;
use OCA\Music\Db\Artist;
use OCA\Music\Db\BaseMapper;
use OCA\Music\Db\SortBy;
use OCA\Music\Db\Track;
use OCA\Music\Http\ErrorResponse;
use OCA\Music\Service\DetailsService;
use OCA\Music\Service\Scanner;
use OCA\Music\Utility\Random;

class ShivaApiController extends Controller {

	private IL10N $l10n;
	private TrackBusinessLayer $trackBusinessLayer;
	private ArtistBusinessLayer $artistBusinessLayer;
	private AlbumBusinessLayer $albumBusinessLayer;
	private DetailsService $detailsService;
	private Scanner $scanner;
	private string $userId;
	private IURLGenerator $urlGenerator;
	private Logger $logger;

	public function __construct(string $appname,
								IRequest $request,
								IURLGenerator $urlGenerator,
								TrackBusinessLayer $trackBusinessLayer,
								ArtistBusinessLayer $artistBusinessLayer,
								AlbumBusinessLayer $albumBusinessLayer,
								DetailsService $detailsService,
								Scanner $scanner,
								?string $userId, // null if this gets called after the user has logged out
								IL10N $l10n,
								Logger $logger) {
		parent::__construct($appname, $request);
		$this->l10n = $l10n;
		$this->trackBusinessLayer = $trackBusinessLayer;
		$this->artistBusinessLayer = $artistBusinessLayer;
		$this->albumBusinessLayer = $albumBusinessLayer;
		$this->detailsService = $detailsService;
		$this->scanner = $scanner;
		$this->userId = $userId ?? '';
		$this->urlGenerator = $urlGenerator;
		$this->logger = $logger;
	}

	private static function shivaPageToLimits(?int $pageSize, ?int $page) : array {
		if (\is_int($page) && \is_int($pageSize) && $page > 0 && $pageSize > 0) {
			$limit = $pageSize;
			$offset = ($page - 1) * $pageSize;
		} else {
			$limit = $offset = null;
		}
		return [$limit, $offset];
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function artists($fulltree, $albums, ?int $page_size=null, ?int $page=null) {
		$fulltree = \filter_var($fulltree, FILTER_VALIDATE_BOOLEAN);
		$includeAlbums = \filter_var($albums, FILTER_VALIDATE_BOOLEAN);
		list($limit, $offset) = self::shivaPageToLimits($page_size, $page);

		/** @var Artist[] $artists */
		$artists = $this->artistBusinessLayer->findAll($this->userId, SortBy::Name, $limit, $offset);

		$artists = \array_map(fn($a) => $this->artistToApi($a, $includeAlbums || $fulltree, $fulltree), $artists);

		return new JSONResponse($artists);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function artist(int $id, $fulltree) {
		$fulltree = \filter_var($fulltree, FILTER_VALIDATE_BOOLEAN);
		try {
			/** @var Artist $artist */
			$artist = $this->artistBusinessLayer->find($id, $this->userId);
			$artist = $this->artistToApi($artist, $fulltree, $fulltree);
			return new JSONResponse($artist);
		} catch (BusinessLayerException $e) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Return given artist in Shia API format
	 * @param Artist $artist
	 * @param boolean $includeAlbums
	 * @param boolean $includeTracks (ignored if $includeAlbums==false)
	 * @return array
	 */
	private function artistToApi(Artist $artist, bool $includeAlbums, bool $includeTracks) : array {
		$artistInApi = $artist->toShivaApi($this->urlGenerator, $this->l10n);
		if ($includeAlbums) {
			$artistId = $artist->getId();
			$albums = $this->albumBusinessLayer->findAllByArtist($artistId, $this->userId);

			$artistInApi['albums'] = \array_map(fn($a) => $this->albumToApi($a, $includeTracks, false), $albums);
		}
		return $artistInApi;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function albums(?int $artist=null, $fulltree=null, ?int $page_size=null, ?int $page=null) {
		$fulltree = \filter_var($fulltree, FILTER_VALIDATE_BOOLEAN);
		list($limit, $offset) = self::shivaPageToLimits($page_size, $page);

		if ($artist !== null) {
			$albums = $this->albumBusinessLayer->findAllByArtist($artist, $this->userId, $limit, $offset);
		} else {
			$albums = $this->albumBusinessLayer->findAll($this->userId, SortBy::Name, $limit, $offset);
		}

		$albums = \array_map(fn($a) => $this->albumToApi($a, $fulltree, $fulltree), $albums);

		return new JSONResponse($albums);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function album(int $id, $fulltree) {
		$fulltree = \filter_var($fulltree, FILTER_VALIDATE_BOOLEAN);
		try {
			$album = $this->albumBusinessLayer->find($id, $this->userId);
			$album = $this->albumToApi($album, $fulltree, $fulltree);
			return new JSONResponse($album);
		} catch (BusinessLayerException $e) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Return given album in the Shiva API format
	 */
	private function albumToApi(Album $album, bool $includeTracks, bool $includeArtists) : array {
		$albumInApi = $album->toShivaApi($this->urlGenerator, $this->l10n);

		if ($includeTracks) {
			$albumId = $album->getId();
			$tracks = $this->trackBusinessLayer->findAllByAlbum($albumId, $this->userId);
			$albumInApi['tracks'] = \array_map(fn($t) => $t->toShivaApi($this->urlGenerator), $tracks);
		}

		if ($includeArtists) {
			$artistIds = $album->getArtistIds();
			$artists = $this->artistBusinessLayer->findById($artistIds, $this->userId);
			$albumInApi['artists'] = \array_map(fn($a) => $a->toShivaApi($this->urlGenerator, $this->l10n), $artists);
		}

		return $albumInApi;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function tracks(?int $artist=null, ?int $album=null, $fulltree=null, ?int $page_size=null, ?int $page=null) {
		$fulltree = \filter_var($fulltree, FILTER_VALIDATE_BOOLEAN);
		list($limit, $offset) = self::shivaPageToLimits($page_size, $page);

		if ($album !== null) {
			$tracks = $this->trackBusinessLayer->findAllByAlbum($album, $this->userId, $artist, $limit, $offset);
		} elseif ($artist !== null) {
			$tracks = $this->trackBusinessLayer->findAllByArtist($artist, $this->userId, $limit, $offset);
		} else {
			$tracks = $this->trackBusinessLayer->findAll($this->userId, SortBy::Name, $limit, $offset);
		}
		foreach ($tracks as &$track) {
			$artistId = $track->getArtistId();
			$albumId = $track->getAlbumId();
			$track = $track->toShivaApi($this->urlGenerator);
			if ($fulltree) {
				$artist = $this->artistBusinessLayer->find($artistId, $this->userId);
				$track['artist'] = $artist->toShivaApi($this->urlGenerator, $this->l10n);
				$album = $this->albumBusinessLayer->find($albumId, $this->userId);
				$track['album'] = $album->toShivaApi($this->urlGenerator, $this->l10n);
			}
		}
		return new JSONResponse($tracks);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function track(int $id) {
		try {
			$track = $this->trackBusinessLayer->find($id, $this->userId);
			return new JSONResponse($track->toShivaApi($this->urlGenerator));
		} catch (BusinessLayerException $e) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function trackLyrics(int $id) {
		try {
			$track = $this->trackBusinessLayer->find($id, $this->userId);
			$fileId = $track->getFileId();
			$userFolder = $this->scanner->resolveUserFolder($this->userId);
			if ($this->detailsService->hasLyrics($fileId, $userFolder)) {
				/**
				 * The Shiva API has been designed around the idea that lyrics would be scraped from an external
				 * source and never stored on the Shiva server. We, on the other hand, support only lyrics embedded
				 * in the audio file tags and this makes the Shiva lyrics API quite a poor fit. Here we anyway
				 * create a result which is compatible with the Shiva API specification.
				 */
				return new JSONResponse([
					'track' => $this->entityIdAndUri($id, 'track'),
					'source_uri' => '',
					'id' => $fileId,
					'uri' => $this->urlGenerator->linkToRoute(
						'music.musicApi.fileLyrics', ['fileId' => $fileId, 'format' => 'plaintext'])
				]);
			}
		} catch (BusinessLayerException $e) {
			// nothing
		}
		return new ErrorResponse(Http::STATUS_NOT_FOUND);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function randomArtist() {
		return $this->randomItem($this->artistBusinessLayer, 'artist');
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function randomAlbum() {
		return $this->randomItem($this->albumBusinessLayer, 'album');
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function randomTrack() {
		return $this->randomItem($this->trackBusinessLayer, 'track');
	}

	private function randomItem(BusinessLayer $businessLayer, string $type) {
		$ids = $businessLayer->findAllIds($this->userId);
		$id = Random::pickItem($ids);

		if ($id !== null) {
			return new JSONResponse($this->entityIdAndUri($id, $type));
		} else {
			return new ErrorResponse(Http::STATUS_NOT_FOUND);
		}
	}

	private function entityIdAndUri(int $id, string $type) {
		return [
			'id' => $id,
			'uri' => $this->urlGenerator->linkToRoute("music.shivaApi.$type", ['id' => $id])
		];
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function latestItems(?string $since) {
		if ($since === null) {
			$dateTime = new \DateTime('7 days ago');
			$since = $dateTime->format(BaseMapper::SQL_DATE_FORMAT);
		}

		$searchRules = [['rule' => 'added', 'operator' => 'after', 'input' => $since]];

		$artists = $this->artistBusinessLayer->findAllAdvanced('and', $searchRules, $this->userId);
		$albums = $this->albumBusinessLayer->findAllAdvanced('and', $searchRules, $this->userId);
		$tracks = $this->trackBusinessLayer->findAllAdvanced('and', $searchRules, $this->userId);

		return new JSONResponse([
			'artists' => \array_map(fn($a) => $this->entityIdAndUri($a->getId(), 'artist'), $artists),
			'albums' => \array_map(fn($a) => $this->entityIdAndUri($a->getId(), 'album'), $albums),
			'tracks' => \array_map(fn($t) => $this->entityIdAndUri($t->getId(), 'track'), $tracks)
		]);
	}
}
