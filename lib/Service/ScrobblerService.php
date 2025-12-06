<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Matthew Wells
 * @copyright Matthew Wells 2025
 */

namespace OCA\Music\Service;

use DateTime;
use OCA\Music\AppFramework\Core\Logger;
use OCA\Music\BusinessLayer\TrackBusinessLayer;
use OCA\Music\Db\Track;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\Security\ICrypto;

class ScrobblerService
{
	private IConfig $config;

	private Logger $logger;

	private IURLGenerator $urlGenerator;

	private TrackBusinessLayer $trackBusinessLayer;

	private ?string $appName;

	private ICrypto $crypto;

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
		ICrypto $crypto,
		?string $appName = 'music'
	) {
		$this->config = $config;
		$this->logger = $logger;
		$this->urlGenerator = $urlGenerator;
		$this->trackBusinessLayer = $trackBusinessLayer;
		$this->crypto = $crypto;
		$this->appName = $appName;
	}

	/**
	 * @throws \Throwable when unable to generate or save a session
	 */
	public function generateSession(string $token, string $userId) : void {
		$scrobbleService = $this->getApiService();
		$ch = $this->makeCurlHandle($scrobbleService);
		$params = $this->generateBaseMethodParams('auth.getSession');
		$params['token'] = $token;
		$params['api_sig'] = $this->generateSignature($params);
		\curl_setopt($ch, \CURLOPT_POSTFIELDS, \http_build_query($params));
		$sessionText = \curl_exec($ch);
		$xml = \simplexml_load_string($sessionText);

		$status = (string)$xml['status'];
		if ($status !== 'ok') {
			throw new \Exception((string)$xml->error, (int)$xml->error['code']);
		}

		try {
			$encryptedKey = $this->crypto->encrypt(
				(string)$xml->session->key,
				$userId . $this->config->getSystemValue('secret')
			);
			$this->config->setUserValue($userId, $this->appName, 'scrobbleSessionKey', $encryptedKey);
		} catch (\Throwable $e) {
			$this->logger->error('Unable to save session key ' . $e->getMessage());
			throw $e;
		}
	}

	public function getApiKey() : ?string {
		return $this->config->getSystemValue('music.scrobble_api_key', null);
	}

	public function getApiSecret() : ?string {
		return $this->config->getSystemValue('music.scrobble_api_secret', null);
	}

	public function getApiService() : ?string {
		return $this->config->getSystemValue('music.scrobble_api_service', null);
	}

	public function getApiSession(string $userId): ?string
	{
		$encryptedKey = $this->config->getUserValue($userId, $this->appName, 'scrobbleSessionKey');
		if (!$encryptedKey) {
			return null;
		}
		$key = $this->crypto->decrypt($encryptedKey, $userId . $this->config->getSystemValue('secret'));
		return $key;
	}

	public function getTokenRequestUrl(): ?string {
		$apiKey = $this->getApiKey();
		$apiService = $this->getApiService();
		if (!$apiKey || !$apiService) {
			return null;
		}

		$tokenHandleUrl = $this->urlGenerator->linkToRouteAbsolute('music.scrobbler.handleToken');
		switch ($apiService) {
			case 'lastfm':
				return "http://www.last.fm/api/auth/?api_key={$apiKey}&cb={$tokenHandleUrl}";
			default:
				throw new \Exception('Invalid service');
		}
	}

	/**
	 * @param array<int,mixed> $trackIds
	 */
	public function scrobbleTrack(array $trackIds, string $userId, \DateTime $timeOfPlay) : bool {
		$sessionKey = $this->getApiSession($userId);
		$scrobbleService = $this->getApiService();
		if (!$sessionKey || !$scrobbleService) {
			return false;
		}

		$timestamp = $timeOfPlay->getTimestamp();
		$scrobbleData = \array_merge($this->generateBaseMethodParams('track.scrobble'), [
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
		$scrobbleData['api_sig'] = $this->generateSignature($scrobbleData);

		try {
			$ch = $this->makeCurlHandle($scrobbleService);
			$postFields = \http_build_query($scrobbleData);
			\curl_setopt($ch, \CURLOPT_POSTFIELDS, $postFields);
			$xml = \simplexml_load_string(\curl_exec($ch));
			$status = (string)$xml['status'] === 'ok';
		} catch (\Throwable $t) {
			$status = false;
			$this->logger->error($t->getMessage());
		} finally {
			return $status;
		}
	}

	public function clearSession(?string $userId) : bool {
		try {
			$this->config->deleteUserValue($userId, $this->appName, 'scrobbleSessionKey');
			return true;
		} catch (\InvalidArgumentException $e) {
			$this->logger->error('Could not delete user config "scrobbleSessionKey". ' . $e->getMessage());
		}
		return false;
	}

	public function getName() : string
	{
		$apiService = $this->getApiService();
		if (!$apiService) {
			return '';
		}

		switch ($apiService) {
			case 'lastfm':
				return "Last.fm";
			default:
				throw new \Exception('Invalid service');
		}
	}

	/**
	 * @param array<string, string|array> $params
	 */
	private function generateSignature(array $params) : string {
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

		$paramString .= $apiSecret = $this->getApiSecret();
		return \md5($paramString);
	}

	/**
	 * @return array<string, string>
	 */
	private function generateBaseMethodParams(string $method) : array {
		$params = [
			'method' => $method,
			'api_key' => $this->getApiKey()
		];

		return $params;
	}

	/**
	 * @return resource in PHP8+ \CurlHandle
	 * @throws \RuntimeException when unable to initialize a cURL handle
	 */
	private function makeCurlHandle(string $scrobblerServiceIdentifier) {
		$endpoint = self::SCROBBLE_SERVICES[$scrobblerServiceIdentifier]['endpoint'];
		$ch = \curl_init($endpoint);
		if (!$ch) {
			throw new \RuntimeException('Unable to initialize a cURL handle');
		}
		\curl_setopt($ch, \CURLOPT_CONNECTTIMEOUT, 10);
		\curl_setopt($ch, \CURLOPT_POST, true);
		\curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
		return $ch;
	}
}
