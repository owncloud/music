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
use \OCA\Music\BusinessLayer\ArtistBusinessLayer;

use \OCP\IConfig;
use OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;


class LastfmService {
	private $artistBusinessLayer;
	private $logger;
	private $apiKey;

	const LASTFM_URL = 'http://ws.audioscrobbler.com/2.0/';

	public function __construct(
			ArtistBusinessLayer $artistBusinessLayer,
			IConfig $config,
			Logger $logger) {
		$this->artistBusinessLayer = $artistBusinessLayer;
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

		if (empty($this->apiKey)) {
			return ['api_key_set' => false];
		}
		else {
			$name = \str_replace(' ', '%20', $artist->getName());
			$info = \file_get_contents(self::LASTFM_URL .
					'?method=artist.getInfo' .
					'&artist=' . $name .
					'&api_key=' . $this->apiKey .
					'&format=json');

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
