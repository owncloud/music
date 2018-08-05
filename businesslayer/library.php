<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2018
 */

namespace OCA\Music\BusinessLayer;

use \OCA\Music\AppFramework\Core\Logger;
use \OCA\Music\Utility\CoverHelper;

use \OCP\IL10N;
use \OCP\IURLGenerator;

class Library {
	private $albumBusinessLayer;
	private $artistBusinessLayer;
	private $trackBusinessLayer;
	private $coverHelper;
	private $urlGenerator;
	private $l10n;
	private $logger;

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
		/** @var Track[] $allTracks */
		$tracks = $this->trackBusinessLayer->findAll($userId);
		/** @var Album[] $allAlbums */
		$albums = $this->albumBusinessLayer->findAll($userId);
		/** @var Artist[] $allArtists */
		$artists = $this->artistBusinessLayer->findAll($userId);

		$artistsById = [];
		foreach ($artists as &$artist) {
			$artistsById[$artist->getId()] = $artist;
		}

		$albumsById = [];
		foreach ($albums as &$album) {
			$album->setAlbumArtist($artistsById[$album->getAlbumArtistId()]);
			$albumsById[$album->getId()] = $album;
		}

		foreach ($tracks as $idx => $track) {
			$album = $albumsById[$track->getAlbumId()];
			$trackArtist = $artistsById[$track->getArtistId()];

			if (empty($album) || empty($trackArtist)) {
				$this->logger->log("DB error on track {$track->id} '{$track->title}': ".
				"album or artist missing. Skipping the track.", 'warn');
				unset($tracks[$idx]);
			}
			else {
				$track->setAlbum($album);
				$track->setArtist($trackArtist);
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
		$coverHashes = $this->coverHelper->getAllCachedCoverHashes($userId);

		// Create a multi-level dictionary of tracks where each track can be found
		// by addressing like $trackDict[artistId][albumId][ordinal]. The tracks are
		// in the dictionary in the "toCollection" format.
		$trackDict = [];
		foreach ($entities['tracks'] as $track) {
			$trackDict[$track->getAlbum()->getAlbumArtistId()][$track->getAlbumId()][]
				= $track->toCollection($this->l10n);
		}

		// Then create the actual collection by iterating over the previusly created
		// dictionary and creating artists and albums in the "toCollection" format.
		$collection = [];
		foreach ($trackDict as $artistId => $artistTracksByAlbum) {

			$artistAlbums = [];
			foreach ($artistTracksByAlbum as $albumId => $albumTracks) {
				$coverHash = isset($coverHashes[$albumId]) ? $coverHashes[$albumId] : null;
				$artistAlbums[] = $entities['albums'][$albumId]->toCollection(
						$this->urlGenerator, $this->l10n, $coverHash, $albumTracks);
			}

			$collection[] = $entities['artists'][$artistId]->toCollection($this->l10n, $artistAlbums);
		}

		return $collection;
	}

}
	