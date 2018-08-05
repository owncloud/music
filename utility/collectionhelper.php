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

use \OCP\ICache;
use \OCP\IL10N;
use \OCP\IURLGenerator;

use \Doctrine\DBAL\Exception\UniqueConstraintViolationException;

/**
 * Utility to build and cache the monolithic json data describing the whole music library.
 * 
 * There has to be a logged-in user to use this class, the userId is injected via the class
 * constructor.
 * 
 * This class utilizes two caching mechanism: file-backed \OCP\ICache and database-backed
 * \OCA\Music\Db\Cache. The actual json data is stored in the former and a hash of the data
 * is stored into the latter. The hash is used as a flag indicating that the data is valid.
 * The rationale of this design is that the \OCP\ICache can be used only for the logged-in
 * user, but we must be able to invalidate the cache also in cases when the affected user is
 * not logged in (in FileHooks, ShareHooks, occ commands). On the other hand, depending on
 * the database configuration, the json data may be too large to store it to \OCA\Music\Db\Cache
 * (with tens of thousands of tracks, the size of the json may be more than 10 MB and the
 * DB may be configured with maximum object size of e.g. 1 MB).
 */
class CollectionHelper {
	private $albumBusinessLayer;
	private $artistBusinessLayer;
	private $trackBusinessLayer;
	private $coverHelper;
	private $urlGenerator;
	private $l10n;
	private $fileCache;
	private $dbCache;
	private $logger;
	private $userId;

	public function __construct(
			AlbumBusinessLayer $albumBusinessLayer,
			ArtistBusinessLayer $artistBusinessLayer,
			TrackBusinessLayer $trackBusinessLayer,
			CoverHelper $coverHelper,
			IURLGenerator $urlGenerator,
			IL10N $l10n,
			ICache $fileCache,
			Cache $dbCache,
			Logger $logger,
			$userId) {
		$this->albumBusinessLayer = $albumBusinessLayer;
		$this->artistBusinessLayer = $artistBusinessLayer;
		$this->trackBusinessLayer = $trackBusinessLayer;
		$this->coverHelper = $coverHelper;
		$this->urlGenerator = $urlGenerator;
		$this->l10n = $l10n;
		$this->fileCache = $fileCache;
		$this->dbCache = $dbCache;
		$this->logger = $logger;
		$this->userId = $userId;
	}

	public function getJson() {
		$collectionJson = $this->getCachedJson();

		if ($collectionJson === null) {
			$collectionJson = $this->buildJson();
			try {
				$this->addJsonToCache($collectionJson);
			} catch (UniqueConstraintViolationException $ex) {
				$this->logger->log("Race condition: collection.json for user {$this->userId} ".
						"cached twice, ignoring latter.", 'warn');
			}
		}

		return $collectionJson;
	}

	public function getCachedJsonHash() {
		return $this->dbCache->get($this->userId, 'collection');
	}

	private function getCachedJson() {
		$json = null;
		$hash = $this->dbCache->get($this->userId, 'collection');
		if ($hash !== null) {
			$json = $this->fileCache->get('music_collection.json');
			if ($json === null) {
				$this->logger->log("Inconsistent collection state for user {$this->userId}: ".
						"Hash found from DB-backed cache but data not found from the ".
						"file-backed cache. Removing also the hash.", 'debug');
				$this->dbCache->remove($this->userId, 'collection');
			}
		}
		return $json;
	}

	private function addJsonToCache($json) {
		$hash = \hash('md5', $json);
		$this->dbCache->add($this->userId, 'collection', $hash);
		$this->fileCache->set('music_collection.json', $json, 5*365*24*60*60);
	}

	private function getTracksAlbumsAndArtists($userId) {
		// Get all the entities from the DB first. The order of queries is important if we are in
		// the middle of a scanning process: we don't want to get tracks which do not yet have
		// an album entry or albums which do not yet have artist entry.
		/** @var Track[] $allTracks */
		$tracks = $this->trackBusinessLayer->findAll($this->userId);
		/** @var Album[] $allAlbums */
		$albums = $this->albumBusinessLayer->findAll($this->userId);
		/** @var Artist[] $allArtists */
		$artists = $this->artistBusinessLayer->findAll($this->userId);

		$albumsById = [];
		foreach ($albums as &$album) {
			$albumsById[$album->getId()] = $album;
		}

		$artistsById = [];
		foreach ($artists as &$artist) {
			$artistsById[$artist->getId()] = $artist;
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

	private function buildJson() {
		$entities = $this->getTracksAlbumsAndArtists($this->userId);
		$coverHashes = $this->coverHelper->getAllCachedCoverHashes($this->userId);

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

		return \json_encode($collection);
	}

}
