<?php

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

use \OCA\Music\AppFramework\Core\Logger;
use \OCA\Music\BusinessLayer\AlbumBusinessLayer;
use \OCA\Music\BusinessLayer\ArtistBusinessLayer;
use \OCA\Music\BusinessLayer\TrackBusinessLayer;

use \OCP\IConfig;
use OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;


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
	public function getArtistInfo($artistId, $userId) {
		$artist = $this->artistBusinessLayer->find($artistId, $userId);

		return $this->getInfoFromLastFm([
				'method' => 'artist.getInfo',
				'artist' => $artist->getName()
		]);
	}

	/**
	 * @param integer $albumId
	 * @param string $userId
	 * @return array
	 * @throws BusinessLayerException if album with the given ID is not found
	 */
	public function getAlbumInfo($albumId, $userId) {
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
	public function getTrackInfo($trackId, $userId) {
		$track= $this->trackBusinessLayer->find($trackId, $userId);

		return $this->getInfoFromLastFm([
				'method' => 'track.getInfo',
				'artist' => $track->getArtistName(),
				'track' => $track->getTitle()
		]);
	}

	private function getInfoFromLastFm($args) {
		if (empty($this->apiKey)) {
			return ['api_key_set' => false];
		}
		else {
			// append the standard args
			$args['api_key'] = $this->apiKey;
			$args['format'] = 'json';

			// glue arg keys and values together ...
			$args= \array_map(function($key, $value) {
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
