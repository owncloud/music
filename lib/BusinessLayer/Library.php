<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2018 - 2024
 */

namespace OCA\Music\BusinessLayer;

use OCA\Music\AppFramework\Core\Logger;
use OCA\Music\Db\Album;
use OCA\Music\Db\Artist;
use OCA\Music\Utility\CoverHelper;
use OCA\Music\Utility\Util;

use OCP\IL10N;
use OCP\IURLGenerator;

class Library {
	private AlbumBusinessLayer $albumBusinessLayer;
	private ArtistBusinessLayer $artistBusinessLayer;
	private TrackBusinessLayer $trackBusinessLayer;
	private CoverHelper $coverHelper;
	private IURLGenerator $urlGenerator;
	private IL10N $l10n;
	private Logger $logger;

	public function __construct(
			AlbumBusinessLayer $albumBusinessLayer,
			ArtistBusinessLayer $artistBusinessLayer,
			TrackBusinessLayer $trackBusinessLayer,
			CoverHelper $coverHelper,
			IURLGenerator $urlGenerator,
			IL10N $l10n,
			Logger $logger) {
		$this->albumBusinessLayer = $albumBusinessLayer;
		$this->artistBusinessLayer = $artistBusinessLayer;
		$this->trackBusinessLayer = $trackBusinessLayer;
		$this->coverHelper = $coverHelper;
		$this->urlGenerator = $urlGenerator;
		$this->l10n = $l10n;
		$this->logger = $logger;
	}

	public function getTracksAlbumsAndArtists($userId) {
		// Get all the entities from the DB first. The order of queries is important if we are in
		// the middle of a scanning process: we don't want to get tracks which do not yet have
		// an album entry or albums which do not yet have artist entry.
		$tracks = $this->trackBusinessLayer->findAll($userId);
		$albums = $this->albumBusinessLayer->findAll($userId);
		$artists = $this->artistBusinessLayer->findAll($userId);

		$artistsById = Util::createIdLookupTable($artists);
		$albumsById = Util::createIdLookupTable($albums);

		foreach ($tracks as $idx => $track) {
			$album = $albumsById[$track->getAlbumId()];

			if (empty($album)) {
				$this->logger->log("DB error on track {$track->id} '{$track->title}': ".
				"album with ID {$track->albumId} not found. Skipping the track.", 'warn');
				unset($tracks[$idx]);
			} else {
				$track->setAlbum($album);
			}
		}

		return [
			'tracks' => $tracks,
			'albums' => $albumsById,
			'artists' => $artistsById
		];
	}

	public function toCollection($userId) {
		$entities = $this->getTracksAlbumsAndArtists($userId);
		$coverHashes = $this->coverHelper->getAllCachedAlbumCoverHashes($userId);

		// Create a multi-level dictionary of tracks where each track can be found
		// by addressing like $trackDict[artistId][albumId][ordinal]. The tracks are
		// in the dictionary in the "toCollection" format.
		$trackDict = [];
		foreach ($entities['tracks'] as $track) {
			$trackDict[$track->getAlbum()->getAlbumArtistId()][$track->getAlbumId()][]
				= $track->toCollection();
		}

		// Then create the actual collection by iterating over the previously created
		// dictionary and creating artists and albums in the "toCollection" format.
		$collection = [];
		foreach ($trackDict as $artistId => $artistTracksByAlbum) {
			$artistAlbums = [];
			foreach ($artistTracksByAlbum as $albumId => $albumTracks) {
				$coverHash = $coverHashes[$albumId] ?? null;
				$artistAlbums[] = $entities['albums'][$albumId]->toCollection(
						$this->urlGenerator, $this->l10n, $coverHash, $albumTracks);
			}

			$collection[] = $entities['artists'][$artistId]->toCollection($this->urlGenerator, $this->l10n, $artistAlbums);
		}

		// Add the artists with no own albums to the collection
		foreach ($entities['artists'] as $artist) {
			if (!isset($trackDict[$artist->getId()])) {
				$collection[] = $artist->toCollection($this->urlGenerator, $this->l10n, []);
			}
		}

		return $collection;
	}

	/**
	 * Get the timestamp of the latest insert operation on the library
	 */
	public function latestInsertTime(string $userId) : \DateTime {
		return \max(
			$this->artistBusinessLayer->latestInsertTime($userId),
			$this->albumBusinessLayer->latestInsertTime($userId),
			$this->trackBusinessLayer->latestInsertTime($userId)
		);
	}

	/**
	 * Get the timestamp of the latest update operation on the library
	 */
	public function latestUpdateTime(string $userId) : \DateTime {
		return \max(
			$this->artistBusinessLayer->latestUpdateTime($userId),
			$this->albumBusinessLayer->latestUpdateTime($userId),
			$this->trackBusinessLayer->latestUpdateTime($userId)
		);
	}

	/**
	 * Inject tracks to the given albums. Sets also the album reference of each injected track.
	 * @param Album[] $albums input/output
	 */
	public function injectTracksToAlbums(array &$albums, string $userId) : void {
		$alBussLayer = $this->albumBusinessLayer;
		$trBussLayer = $this->trackBusinessLayer;

		if (\count($albums) < $alBussLayer::MAX_SQL_ARGS && \count($albums) < $alBussLayer->count($userId)) {
			$tracks = $trBussLayer->findAllByAlbum(Util::extractIds($albums), $userId);
		} else {
			$tracks = $trBussLayer->findAll($userId);
		}

		$tracksPerAlbum = Util::arrayGroupBy($tracks, 'getAlbumId');

		foreach ($albums as &$album) {
			$albumTracks = $tracksPerAlbum[$album->getId()] ?? [];
			$album->setTracks($albumTracks);
			foreach ($albumTracks as &$track) {
				$track->setAlbum($album);
			}
		}
	}

	/**
	 * Inject tracks to the given artists
	 * @param Artist[] $artists input/output
	 */
	public function injectTracksToArtists(array &$artists, string $userId) : void {
		$arBussLayer = $this->artistBusinessLayer;
		$trBussLayer = $this->trackBusinessLayer;

		if (\count($artists) < $arBussLayer::MAX_SQL_ARGS && \count($artists) < $arBussLayer->count($userId)) {
			$tracks = $trBussLayer->findAllByArtist(Util::extractIds($artists), $userId);
		} else {
			$tracks = $trBussLayer->findAll($userId);
		}

		$tracksPerArtist = Util::arrayGroupBy($tracks, 'getArtistId');

		foreach ($artists as &$artist) {
			$artistTracks = $tracksPerArtist[$artist->getId()] ?? [];
			$artist->setTracks($artistTracks);
		}
	}

	/**
	 * Inject albums to the given artists
	 * @param Artist[] $artists input/output
	 */
	public function injectAlbumsToArtists(array &$artists, string $userId) : void {
		$arBussLayer = $this->artistBusinessLayer;
		$alBussLayer = $this->albumBusinessLayer;

		if (\count($artists) < $arBussLayer::MAX_SQL_ARGS && \count($artists) < $arBussLayer->count($userId)) {
			$albums = $alBussLayer->findAllByAlbumArtist(Util::extractIds($artists), $userId);
		} else {
			$albums = $alBussLayer->findAll($userId);
		}

		$albumsPerArtist = Util::arrayGroupBy($albums, 'getAlbumArtistId');

		foreach ($artists as &$artist) {
			$artistAlbums = $albumsPerArtist[$artist->getId()] ?? [];
			$artist->setAlbums($artistAlbums);
		}
	}
}
