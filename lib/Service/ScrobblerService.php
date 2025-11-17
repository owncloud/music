<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2020 - 2025
 */

namespace OCA\Music\Service;

use DateTime;
use OCA\Music\AppFramework\Core\Logger;
use OCA\Music\BusinessLayer\TrackBusinessLayer;
use OCA\Music\Db\Track;
use OCP\IConfig;
use OCP\IURLGenerator;

class ScrobblerService
{
	private IConfig $config;

	private Logger $logger;

	private IURLGenerator $urlGenerator;

	private TrackBusinessLayer $trackBusinessLayer;

	private ?string $appName;

	public const SCROBBLE_SERVICES = [
		'lastfm' => [
			'endpoint' => 'http://ws.audioscrobbler.com/2.0/',
			'name' => 'Last.fm'
		]
	];

	public function __construct(
		IConfig $config,
		Logger $logger,
		IURLGenerator $urlGenerator,
		TrackBusinessLayer $trackBusinessLayer,
		?string $appName = 'music'
	) {
		$this->config = $config;
		$this->logger = $logger;
		$this->urlGenerator = $urlGenerator;
		$this->trackBusinessLayer = $trackBusinessLayer;
		$this->appName = $appName;
	}

	public function generateSession(string $token, string $userId): string {
		$scrobbleService = $this->getApiService($userId);
		$ch = $this->makeCurlHandle($scrobbleService);
		$params = $this->generateBaseMethodParams('auth.getSession', $userId);
		$params['token'] = $token;
		$params['api_sig'] = $this->generateSignature($params, $userId);
		\curl_setopt($ch, \CURLOPT_POSTFIELDS, \http_build_query($params));
		$sessionText = \curl_exec($ch);
		$xml = \simplexml_load_string($sessionText);

		$status = (string)$xml['status'];
		if ($status !== 'ok') {
			return \sprintf('Error %d: %s', (int)$xml->error['code'], (string)$xml->error);
		}

		try {
			$this->config->setUserValue($userId, $this->appName, 'scrobbleSessionKey', (string)$xml->session->key);
		} catch (\Throwable $e) {
			$this->logger->error("Unable to save session key");
			return $e->getMessage();
		}
		return 'ok';
	}

	public function saveApiSettings(
		string $userId,
		string $apiKey,
		string $apiSecret,
		string $apiService
	): bool {
		try {
			$this->validateApiSettings($apiKey, $apiSecret, $apiService);
			$this->config->setUserValue($userId, $this->appName, 'scrobbleApiKey', $apiKey);
			$this->config->setUserValue($userId, $this->appName, 'scrobbleApiSecret', $apiSecret);
			$this->config->setUserValue($userId, $this->appName, 'scrobbleApiService', $apiService);
		} catch (\Throwable $e) {
			$this->logger->error("Unable to save {$apiService} API settings: " . $e->getMessage());
			throw $e;
		}
		return true;
	}

	public function getApiKey(string $userId) : ?string {
		return $this->config->getUserValue($userId, $this->appName, 'scrobbleApiKey', null);
	}

	public function getApiSecret(string $userId) : ?string {
		return $this->config->getUserValue($userId, $this->appName, 'scrobbleApiSecret', null);
	}

	public function getApiService(string $userId) : ?string {
		return $this->config->getUserValue($userId, $this->appName, 'scrobbleApiService', null);
	}

	public function getTokenRequestUrl(string $apiKey, string $apiService): string {
		$tokenHandleUrl = $this->urlGenerator->linkToRouteAbsolute('music.scrobbler.handleToken');
		switch ($apiService) {
			case 'lastfm':
				return "http://www.last.fm/api/auth/?api_key={$apiKey}&cb={$tokenHandleUrl}";
			default:
				throw new \Exception('Invalid service');
		}
	}

	/**
	 * @param array<string, string|array> $params
	 */
	private function generateSignature(array $params, string $userId) : string {
		\ksort($params);
		$paramString = '';
		foreach ($params as $key => $value) {
			if (\is_array($value)) {
				foreach ($value as $valIdx => $valVal) {
					$paramString .= "{$key}[{$valIdx}]{$valVal}";
				}
			} else {
				$paramString .= $key . $value;
			}
		}

		$paramString .= $apiSecret = $this->getApiSecret($userId);
		return \md5($paramString);
	}

	/**
	 * @return array<string, string>
	 */
	private function generateBaseMethodParams(string $method, string $userId) : array {
		$params = [
			'method' => $method,
			'api_key' => $this->getApiKey($userId)
		];

		return $params;
	}

	public function scrobbleTrack(array $trackIds, string $userId, \DateTime $timeOfPlay) : bool {
		$sessionKey = $this->config->getUserValue($userId, $this->appName, 'scrobbleSessionKey');
		if (!$sessionKey) {
			return false;
		}

		$timestamp = $timeOfPlay->getTimestamp();
		$scrobbleData = \array_merge($this->generateBaseMethodParams('track.scrobble', $userId), [
			'sk' => $sessionKey,
		]);

		/** @var array<Track> $tracks */
		$tracks = $this->trackBusinessLayer->findById($trackIds);
		foreach ($tracks as $i => $track) {
			$scrobbleData["artist[{$i}]"] = $track->getArtistName();
			$scrobbleData["track[{$i}]"] = $track->getTitle();
			$scrobbleData["timestamp[{$i}]"] = $timestamp;
			$scrobbleData["album[{$i}]"] = $track->getAlbumName();
			$scrobbleData["trackNumber[{$i}]"] = $track->getNumber();
		}
		$scrobbleData['api_sig'] = $this->generateSignature($scrobbleData, $userId);

		$scrobbleService = $this->getApiService($userId);
		$ch = $this->makeCurlHandle($scrobbleService);
		$postFields = \http_build_query($scrobbleData);
		\curl_setopt($ch, \CURLOPT_POSTFIELDS, $postFields);
		$xml = \simplexml_load_string(\curl_exec($ch));

		$status = (string)$xml['status'];
		if ($status !== 'ok') {
			return false;
		}

		return true;
	}

	/**
	 * @return false|resource|\CurlHandle
	 */
	private function makeCurlHandle(string $scrobblerServiceIdentifier) {
		$endpoint = self::SCROBBLE_SERVICES[$scrobblerServiceIdentifier]['endpoint'];
		$ch = \curl_init($endpoint);
		if (!$ch) {
			return false;
		}
		\curl_setopt($ch, \CURLOPT_CONNECTTIMEOUT, 10);
		\curl_setopt($ch, \CURLOPT_POST, true);
		\curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
		return $ch;
	}

	private function validateApiSettings(string $apiKey, string $apiSecret, string $apiService) : void
	{
		if ($apiKey === '' || $apiSecret === '' || $apiService === '') {
			throw new \Exception('API key, secret, and service are all required');
		}
	}
}
