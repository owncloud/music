<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2019 - 2021
 */

use GuzzleHttp\Client;

/**
 * Abstraction for the specific Subsonic API calls and how to parse the result
 */
class SubsonicClient {

	/** @var string the Subsonic API URL */
	private $baseUrl;
	/** @var string */
	private $userName;
	/** @var string */
	private $password;

	/**
	 * connects to the API endpoint and authenticates the user
	 *
	 * @param string $baseUrl URL of the cloud instance
	 * @param string $userName
	 * @param string $password
	 */
	public function __construct($baseUrl, $userName, $password) {
		$this->baseUrl = $baseUrl . '/apps/music/subsonic/rest/';
		$this->userName = $userName;
		$this->password = $password;
	}

	/**
	 * requests the given Subsonic method and returns an XML object
	 *
	 * @param string $method
	 * @param array $options
	 * @return SimpleXMLElement
	 * @throws SubsonicClientException if the XML couldn't be parsed or there is an error in the response
	 */
	public function request($method, $options = []) {
		$response = $this->doRequest($method, $options);

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
	 * @param string $method
	 * @param array $options
	 * @return array
	 * @throws SubsonicClientException if the JSON couldn't be parsed or there is an error in the response
	 */
	public function requestJson($method, $options = []) {
		$options['f'] = 'json';
		$response = $this->doRequest($method, $options);

		try {
			$json = \json_decode($response->getBody(), true);
		} catch (Exception $e) {
			throw new SubsonicClientException('Could not parse JSON', 0, $e);
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

	private function doRequest($method, $options) {
		$client = new Client(['verify' => false]);
		return $client->get($this->baseUrl . $method, [
			'query' => \array_merge([
				'u' => $this->userName,
				'p' => $this->password,
				'c' => 'BehatSubsonicClient',
				'v' => '1.4'
			], $options)
		]);
	}

}
