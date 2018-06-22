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

namespace OCA\Music\Utility;

use \OCA\Music\AppFramework\Core\Logger;
use \OCA\Music\BusinessLayer\AlbumBusinessLayer;
use \OCA\Music\BusinessLayer\ArtistBusinessLayer;
use \OCA\Music\BusinessLayer\TrackBusinessLayer;
use \OCA\Music\Db\Cache;

use \OCP\IL10N;
use \OCP\IURLGenerator;

use \Doctrine\DBAL\Exception\UniqueConstraintViolationException;

/**
 * utility to get the monolithic json data describing the whole music library
 */
class CollectionHelper {
	private $albumBusinessLayer;
	private $artistBusinessLayer;
	private $trackBusinessLayer;
	private $coverHelper;
	private $urlGenerator;
	private $l10n;
	private $cache;
	private $logger;

	public function __construct(
			AlbumBusinessLayer $albumBusinessLayer,
			ArtistBusinessLayer $artistBusinessLayer,
			TrackBusinessLayer $trackBusinessLayer,
			CoverHelper $coverHelper,
			IURLGenerator $urlGenerator,
			IL10N $l10n,
			Cache $cache,
			Logger $logger) {
		$this->albumBusinessLayer = $albumBusinessLayer;
		$this->artistBusinessLayer = $artistBusinessLayer;
		$this->trackBusinessLayer = $trackBusinessLayer;
		$this->coverHelper = $coverHelper;
		$this->urlGenerator = $urlGenerator;
		$this->l10n = $l10n;
		$this->cache = $cache;
		$this->logger = $logger;
	}

	public function getJson($userId) {
		$collectionJson = $this->cache->get($userId, 'collection');

		if ($collectionJson === null) {
			$collectionJson = $this->buildJson($userId);
			try {
				$this->cache->add($userId, 'collection', $collectionJson);
			} catch (UniqueConstraintViolationException $ex) {
				$this->logger->log("Race condition: collection.json for user $userId ".
						"cached twice, ignoring latter.", 'warn');
			}
		}

		return $collectionJson;
	}

	private function buildJson($userId) {
		// Get all the entities from the DB first. The order of queries is important if we are in
		// the middle of a scanning process: we don't want to get tracks which do not yet have
		// an album entry or albums which do not yet have artist entry.
		/** @var Track[] $allTracks */
		$allTracks = $this->trackBusinessLayer->findAll($userId);
		/** @var Album[] $allAlbums */
		$allAlbums = $this->albumBusinessLayer->findAll($userId);
		/** @var Artist[] $allArtists */
		$allArtists = $this->artistBusinessLayer->findAll($userId);

		$allArtistsByIdAsObj = [];
		$allArtistsByIdAsArr = [];
		foreach ($allArtists as &$artist) {
			$artistId = $artist->getId();
			$allArtistsByIdAsObj[$artistId] = $artist;
			$allArtistsByIdAsArr[$artistId] = $artist->toCollection($this->l10n);
		}

		$allAlbumsByIdAsObj = [];
		$allAlbumsByIdAsArr = [];
		$coverHashes = $this->coverHelper->getAllCachedCoverHashes($userId);
		foreach ($allAlbums as &$album) {
			$albumId = $album->getId();
			$allAlbumsByIdAsObj[$albumId] = $album;
			$coverHash = isset($coverHashes[$albumId]) ? $coverHashes[$albumId] : null;
			$allAlbumsByIdAsArr[$albumId] = $album->toCollection(
					$this->urlGenerator, $this->l10n, $coverHash);
		}

		$artists = [];
		foreach ($allTracks as $track) {
			$albumObj = $allAlbumsByIdAsObj[$track->getAlbumId()];
			$trackArtistObj = $allArtistsByIdAsObj[$track->getArtistId()];
			$albumArtist = &$allArtistsByIdAsArr[$albumObj->getAlbumArtistId()];

			if (empty($albumObj) || empty($trackArtistObj) || empty($albumArtist)) {
				$this->logger->log("DB error on track {$track->id} '{$track->title}': ".
						"album or artist missing. Skipping the track.", 'warn');
			} else {
				$track->setAlbum($albumObj);
				$track->setArtist($trackArtistObj);
				
				if (!isset($albumArtist['albums'])) {
					$albumArtist['albums'] = [];
					$artists[] = &$albumArtist;
				}
				$album = &$allAlbumsByIdAsArr[$track->getAlbumId()];
				if (!isset($album['tracks'])) {
					$album['tracks'] = [];
					$albumArtist['albums'][] = &$album;
				}
				$album['tracks'][] = $track->toCollection($this->l10n);
			}
		}
		return \json_encode($artists);
	}

}
