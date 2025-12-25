<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2019 - 2025
 */

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

/**
 * Abstraction for the specific Subsonic API calls and how to parse the result
 */
class SubsonicClient {

	/** @var string the Subsonic API URL */
	private string $baseUrl;
	/** @var string */
	private string $userName;
	/** @var string */
	private string $password;

	/**
	 * connects to the API endpoint and authenticates the user
	 *
	 * @param string $baseUrl URL of the cloud instance
	 */
	public function __construct(string $baseUrl, string $userName, string $password) {
		$this->baseUrl = $baseUrl . '/apps/music/subsonic/rest/';
		$this->userName = $userName;
		$this->password = $password;
	}

	/**
	 * requests the given Subsonic method and returns an XML object
	 *
	 * @param array<string, string> $queryParams
	 * @throws SubsonicClientException if the XML couldn't be parsed or there is an error in the response
	 */
	public function request(string $method, array $queryParams = []) : \SimpleXMLElement {
		$response = $this->doRequest($method, $queryParams);

		try {
			$xml = ClientUtil::getXml($response);
			$xml->registerXPathNamespace('ss', 'http://subsonic.org/restapi');
		} catch (Exception $e) {
			throw new SubsonicClientException('Could not parse XML', 0, $e);
		}

		$rootElem = $xml->xpath('/ss:subsonic-response')[0];

		if (empty($rootElem)) {
			throw new SubsonicClientException('Invalid response, no root element found');
		} elseif ($rootElem['status'] != 'ok') {
			$error = $rootElem->xpath('ss:error')[0];
			throw new SubsonicClientException('Subsonic error: ' . $error['code'] . ' - ' . $error['message']);
		}

		return $xml;
	}

	/**
	 * requests the given Subsonic method in JSON format and returns the parsed JSON
	 *
	 * @param array<string, string> $queryParams
	 * @throws SubsonicClientException if the JSON couldn't be parsed or there is an error in the response
	 */
	public function requestJson(string $method, array $queryParams = []) : array {
		$queryParams['f'] = 'json';
		$response = $this->doRequest($method, $queryParams);

		try {
			$json = \json_decode($response->getBody(), true);
		} catch (Exception $e) {
			throw new SubsonicClientException('Could not parse JSON', 0, $e);
		}

		if (!\is_array($json)) {
			throw new SubsonicClientException('Unexpected JSON parse result');
		}

		$rootElem = $json['subsonic-response'];

		if (empty($rootElem)) {
			throw new SubsonicClientException('Invalid response, no root element found');
		} elseif ($rootElem['status'] != 'ok') {
			$error = $rootElem['error'];
			throw new SubsonicClientException('Subsonic error: ' . $error['code'] . ' - ' . $error['message']);
		}

		return $json;
	}

	private function doRequest(string $method, array $queryParams) : ResponseInterface {
		$client = new Client(['verify' => false]);
		return $client->get($this->baseUrl . $method, [
			'query' => \array_merge([
				'u' => $this->userName,
				'p' => $this->password,
				'c' => 'BehatSubsonicClient',
				'v' => '1.4'
			], $queryParams)
		]);
	}

}
