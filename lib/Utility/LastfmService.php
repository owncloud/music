<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2020
 */

namespace OCA\Music\Utility;

use \OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use \OCA\Music\AppFramework\Core\Logger;
use \OCA\Music\BusinessLayer\AlbumBusinessLayer;
use \OCA\Music\BusinessLayer\ArtistBusinessLayer;
use \OCA\Music\BusinessLayer\TrackBusinessLayer;
use \OCA\Music\Db\Artist;

use \OCP\IConfig;

class LastfmService {
	private $albumBusinessLayer;
	private $artistBusinessLayer;
	private $trackBusinessLayer;
	private $logger;
	private $apiKey;

	const LASTFM_URL = 'http://ws.audioscrobbler.com/2.0/';

	public function __construct(
			AlbumBusinessLayer $albumBusinessLayer,
			ArtistBusinessLayer $artistBusinessLayer,
			TrackBusinessLayer $trackBusinessLayer,
			IConfig $config,
			Logger $logger) {
		$this->albumBusinessLayer = $albumBusinessLayer;
		$this->artistBusinessLayer = $artistBusinessLayer;
		$this->trackBusinessLayer = $trackBusinessLayer;
		$this->logger = $logger;
		$this->apiKey = $config->getSystemValue('music.lastfm_api_key');
	}

	/**
	 * @param integer $artistId
	 * @param string $userId
	 * @return array
	 * @throws BusinessLayerException if artist with the given ID is not found
	 */
	public function getArtistInfo(int $artistId, string $userId) : array {
		$artist = $this->artistBusinessLayer->find($artistId, $userId);

		$result = $this->getInfoFromLastFm([
				'method' => 'artist.getInfo',
				'artist' => $artist->getName()
		]);

		// add ID to those similar artists which can be found from the library
		$similar = $result['artist']['similar']['artist'] ?? null;
		if ($similar !== null) {
			$result['artist']['similar']['artist'] = \array_map(function ($lastfmArtist) use ($userId) {
				$matching = $this->artistBusinessLayer->findAllByName($lastfmArtist['name'], $userId);
				if (!empty($matching)) {
					$lastfmArtist['id'] = $matching[0]->getId();
				}
				return $lastfmArtist;
			}, $similar);
		}

		return $result;
	}

	/**
	 * @param integer $albumId
	 * @param string $userId
	 * @return array
	 * @throws BusinessLayerException if album with the given ID is not found
	 */
	public function getAlbumInfo(int $albumId, string $userId) : array {
		$album = $this->albumBusinessLayer->find($albumId, $userId);

		return $this->getInfoFromLastFm([
				'method' => 'album.getInfo',
				'artist' => $album->getAlbumArtistName(),
				'album' => $album->getName()
		]);
	}

	/**
	 * @param integer $trackId
	 * @param string $userId
	 * @return array
	 * @throws BusinessLayerException if track with the given ID is not found
	 */
	public function getTrackInfo(int $trackId, string $userId) : array {
		$track= $this->trackBusinessLayer->find($trackId, $userId);

		return $this->getInfoFromLastFm([
				'method' => 'track.getInfo',
				'artist' => $track->getArtistName(),
				'track' => $track->getTitle()
		]);
	}

	/**
	 * Get artists from the user's library similar to the given artist
	 * @param integer $artistId
	 * @param string $userId
	 * @parma bool $includeNotPresent When true, the result may include also artists which
	 *                                are not found from the user's music library. Such
	 *                                artists have many fields including `id` set as null.
	 * @return Artist[]
	 * @throws BusinessLayerException if artist with the given ID is not found
	 */
	public function getSimilarArtists(int $artistId, string $userId, $includeNotPresent=false) : array {
		$artist = $this->artistBusinessLayer->find($artistId, $userId);

		$similarOnLastfm = $this->getInfoFromLastFm([
			'method' => 'artist.getSimilar',
			'artist' => $artist->getName()
		]);

		$result = [];
		$similarArr = $similarOnLastfm['similarartists']['artist'] ?? null;
		if ($similarArr !== null) {
			foreach ($similarArr as $lastfmArtist) {
				$matchingLibArtists = $this->artistBusinessLayer->findAllByName($lastfmArtist['name'], $userId);

				if (!empty($matchingLibArtists)) {
					foreach ($matchingLibArtists as &$matchArtist) { // loop although there really shouldn't be more than one
						$matchArtist->setLastfmUrl($lastfmArtist['url']);
					}
					$result = \array_merge($result, $matchingLibArtists);
				} elseif ($includeNotPresent) {
					$unfoundArtist = new Artist();
					$unfoundArtist->setName($lastfmArtist['name'] ?? null);
					$unfoundArtist->setMbid($lastfmArtist['mbid'] ?? null);
					$unfoundArtist->setLastfmUrl($lastfmArtist['url'] ?? null);
					$result[] = $unfoundArtist;
				}
			}
		}

		return $result;
	}

	private function getInfoFromLastFm($args) {
		if (empty($this->apiKey)) {
			return ['api_key_set' => false];
		} else {
			// append the standard args
			$args['api_key'] = $this->apiKey;
			$args['format'] = 'json';

			// remove args with null or empty values
			$args = \array_filter($args, [Util::class, 'isNonEmptyString']);

			// glue arg keys and values together ...
			$args = \array_map(function ($key, $value) {
				return $key . '=' . \urlencode($value);
			}, \array_keys($args), $args);
			// ... and form the final query string
			$queryString = '?' . \implode('&', $args);

			$info = \file_get_contents(self::LASTFM_URL . $queryString);

			if ($info === false) {
				$info = ['connection_ok' => false];
			} else {
				$info = \json_decode($info, true);
				$info['connection_ok'] = true;
			}
			$info['api_key_set'] = true;
			return $info;
		}
	}
}
