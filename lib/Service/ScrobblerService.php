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
	private ICrypto $crypto;
	private string $name;
	private string $identifier;
	private string $endpoint;
	private string $tokenRequestUrl;
	private ?string $appName;

	public function __construct(
		IConfig $config,
		Logger $logger,
		IURLGenerator $urlGenerator,
		TrackBusinessLayer $trackBusinessLayer,
		ICrypto $crypto,
		string $name,
		string $identifier,
		string $endpoint,
		string $tokenRequestUrl,
		?string $appName = 'music'
	) {
		$this->config = $config;
		$this->logger = $logger;
		$this->urlGenerator = $urlGenerator;
		$this->trackBusinessLayer = $trackBusinessLayer;
		$this->crypto = $crypto;
		$this->name = $name;
		$this->identifier = $identifier;
		$this->endpoint = $endpoint;
		$this->tokenRequestUrl = $tokenRequestUrl;
		$this->appName = $appName;
	}

	/**
	 * @throws \Exception when curl initialization or session key save fails
	 * @throws ScrobbleServiceException when auth.getSession call fails
	 */
	public function generateSession(string $token, string $userId) : void {
		$ch = $this->makeCurlHandle();
		\curl_setopt($ch, \CURLOPT_POSTFIELDS, \http_build_query(
			$this->generateMethodParams('auth.getSession', ['token' => $token])
		));
		$xml = \simplexml_load_string(\curl_exec($ch));

		$status = (string)$xml['status'];
		if ($status !== 'ok') {
			throw new ScrobbleServiceException((string)$xml->error, (int)$xml->error['code']);
		}
		$sessionValue = (string)$xml->session->key;

		$this->saveApiSession($userId, $sessionValue);
	}

	/**
	 * @throws \InvalidArgumentException
	 */
	public function clearSession(?string $userId) : void {
		try {
			$this->config->deleteUserValue($userId, $this->appName, $this->identifier . '.scrobbleSessionKey');
		} catch (\InvalidArgumentException $e) {
			$this->logger->error(
				'Could not delete user config "' . $this->identifier . '.scrobbleSessionKey". ' . $e->getMessage()
			);
			throw $e;
		}
	}

	public function getApiSession(string $userId) : ?string {
		$encryptedKey = $this->config->getUserValue($userId, $this->appName, $this->identifier . '.scrobbleSessionKey');
		if (!$encryptedKey) {
			return null;
		}
		$key = $this->crypto->decrypt($encryptedKey, $userId . $this->config->getSystemValue('secret'));
		return $key;
	}

	public function getName() : string {
		return $this->name;
	}

	public function getIdentifier() : string {
		return $this->identifier;
	}

	public function getApiKey() : ?string {
		return $this->config->getSystemValue('music.' . $this->identifier . '_api_key', null);
	}

	public function getApiSecret() : ?string {
		return $this->config->getSystemValue('music.' . $this->identifier . '_api_secret', null);
	}

	/**
	 * @param array<int,mixed> $trackIds
	 */
	public function scrobbleTrack(array $trackIds, string $userId, \DateTime $timeOfPlay) : void {
		$sessionKey = $this->getApiSession($userId);
		if (!$sessionKey) {
			return;
		}

		$timestamp = $timeOfPlay->getTimestamp();
		$scrobbleData = [
			'sk' => $sessionKey
		];

		/** @var array<Track> $tracks */
		$tracks = $this->trackBusinessLayer->findById($trackIds);
		foreach ($tracks as $i => $track) {
			$scrobbleData["artist[{$i}]"] = $track->getArtistName(); // todo: album artist
			$scrobbleData["track[{$i}]"] = $track->getTitle();
			$scrobbleData["timestamp[{$i}]"] = $timestamp;
			$scrobbleData["album[{$i}]"] = $track->getAlbumName();
			$scrobbleData["trackNumber[{$i}]"] = $track->getNumber();
		}
		$scrobbleData = $this->generateMethodParams('track.scrobble', $scrobbleData);

		$ch = $this->makeCurlHandle();
		\curl_setopt($ch, \CURLOPT_POSTFIELDS, \http_build_query($scrobbleData));
		$xml = \simplexml_load_string(\curl_exec($ch));

		if ((string)$xml['status'] !== 'ok') {
			$this->logger->warning('Failed to scrobble to ' . $this->name);
		}
	}

	public function getTokenRequestUrl(): ?string {
		$apiKey = $this->getApiKey();
		if (!$apiKey) {
			return null;
		}

		$tokenHandleUrl = $this->urlGenerator->linkToRouteAbsolute('music.scrobbler.handleToken', [
			'serviceIdentifier' => $this->identifier
		]);
		return "{$this->tokenRequestUrl}?api_key={$apiKey}&cb={$tokenHandleUrl}";
	}

	private function saveApiSession(string $userId, string $sessionValue) : void {
		try {
			$encryptedKey = $this->crypto->encrypt(
				$sessionValue,
				$userId . $this->config->getSystemValue('secret')
			);
			$this->config->setUserValue($userId, $this->appName, $this->identifier . '.scrobbleSessionKey', $encryptedKey);
		} catch (\Exception $e) {
			$this->logger->error('Encryption of scrobble session key failed');
			throw $e;
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
	 * @param array<string, mixed> $moreParams
	 * @param bool $sign
	 * @return array<string, mixed>
	 */
	private function generateMethodParams(string $method, array $moreParams = [], bool $sign = true) : array {
		$params = \array_merge($moreParams, [
			'method' => $method,
			'api_key' => $this->getApiKey()
		]);

		if ($sign) {
			$params['api_sig'] = $this->generateSignature($params);
		}

		return $params;
	}

	/**
	 * @return resource (in PHP8+ return \CurlHandle)
	 * @throws \RuntimeException when unable to initialize a cURL handle
	 */
	private function makeCurlHandle() {
		$ch = \curl_init();
		if (!$ch) {
			$this->logger->error('Failed to initialize a curl handle, is the php curl extension installed?');
			throw new \RuntimeException('Unable to initialize a curl handle');
		}
		\curl_setopt($ch, \CURLOPT_URL, $this->endpoint);
		\curl_setopt($ch, \CURLOPT_CONNECTTIMEOUT, 10);
		\curl_setopt($ch, \CURLOPT_POST, true);
		\curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
		return $ch;
	}
}
