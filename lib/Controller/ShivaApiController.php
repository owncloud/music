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
 * @copyright Pauli Järvinen 2017 - 2021
 */

namespace OCA\Music\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;

use OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use OCA\Music\AppFramework\Core\Logger;
use OCA\Music\BusinessLayer\AlbumBusinessLayer;
use OCA\Music\BusinessLayer\ArtistBusinessLayer;
use OCA\Music\BusinessLayer\TrackBusinessLayer;
use OCA\Music\Db\Album;
use OCA\Music\Db\Artist;
use OCA\Music\Db\SortBy;
use OCA\Music\Db\Track;
use OCA\Music\Http\ErrorResponse;

class ShivaApiController extends Controller {

	/** @var IL10N */
	private $l10n;
	/** @var TrackBusinessLayer */
	private $trackBusinessLayer;
	/** @var ArtistBusinessLayer */
	private $artistBusinessLayer;
	/** @var AlbumBusinessLayer */
	private $albumBusinessLayer;
	/** @var string */
	private $userId;
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var Logger */
	private $logger;

	public function __construct(string $appname,
								IRequest $request,
								IURLGenerator $urlGenerator,
								TrackBusinessLayer $trackbusinesslayer,
								ArtistBusinessLayer $artistbusinesslayer,
								AlbumBusinessLayer $albumbusinesslayer,
								?string $userId, // null if this gets called after the user has logged out
								IL10N $l10n,
								Logger $logger) {
		parent::__construct($appname, $request);
		$this->l10n = $l10n;
		$this->trackBusinessLayer = $trackbusinesslayer;
		$this->artistBusinessLayer = $artistbusinesslayer;
		$this->albumBusinessLayer = $albumbusinesslayer;
		$this->userId = $userId;
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

		$artists = \array_map(function ($a) use ($fulltree, $includeAlbums) {
			return $this->artistToApi($a, $includeAlbums || $fulltree, $fulltree);
		}, $artists);

		return new JSONResponse($artists);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function artist(int $artistId, $fulltree) {
		$fulltree = \filter_var($fulltree, FILTER_VALIDATE_BOOLEAN);
		try {
			/** @var Artist $artist */
			$artist = $this->artistBusinessLayer->find($artistId, $this->userId);
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
	public function albums(?int $artist=null, $fulltree=null, ?int $page_size=null, ?int $page=null) {
		$fulltree = \filter_var($fulltree, FILTER_VALIDATE_BOOLEAN);
		list($limit, $offset) = self::shivaPageToLimits($page_size, $page);

		if ($artist !== null) {
			$albums = $this->albumBusinessLayer->findAllByArtist($artist, $this->userId, $limit, $offset);
		} else {
			$albums = $this->albumBusinessLayer->findAll($this->userId, SortBy::Name, $limit, $offset);
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
	public function album(int $albumId, $fulltree) {
		$fulltree = \filter_var($fulltree, FILTER_VALIDATE_BOOLEAN);
		try {
			$album = $this->albumBusinessLayer->find($albumId, $this->userId);
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
			$track = $track->toAPI($this->urlGenerator);
			if ($fulltree) {
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
	public function track(int $trackId) {
		try {
			/** @var Track $track */
			$track = $this->trackBusinessLayer->find($trackId, $this->userId);
			return new JSONResponse($track->toAPI($this->urlGenerator));
		} catch (BusinessLayerException $e) {
			return new ErrorResponse(Http::STATUS_NOT_FOUND);
		}
	}

}
