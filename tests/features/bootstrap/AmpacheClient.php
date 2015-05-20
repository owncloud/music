<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @copyright Morris Jobke 2015
 */

use GuzzleHttp\Client;

class AmpacheClient {

	private $baseUrl;
	private $authToken;

	public function __construct($baseUrl, $userName, $password) {
		$this->baseUrl = $baseUrl;

		$time = time();
		$key = hash('sha256', $password);
		$passphrase = hash('sha256', $time . $key);

		$xml = $this->request('handshake', [
			'auth' => $passphrase,
			'timestamp' => $time,
			'version' => 350001,
			'user' => $userName,
		]);

		$authToken = $xml->xpath('/root/auth');
		if (!$authToken) {
			throw new AmpacheClientException('No auth token in response ' . $xml->asXML());
		}

		$this->authToken = $authToken[0]->__toString();
	}

	public function hasAuthToken() {
		return $this->authToken !== null;
	}

	/**
	 * @param $method
	 * @param array $options
	 * @return SimpleXMLElement
	 * @throws AmpacheClientException
	 * @throws Exception
	 */
	public function request($method, $options = []) {
		if (!in_array($method, ['artists', 'handshake', 'albums'])) {
			throw new Exception('Unsupported method: ' . $method);
		}

		$client = new Client();
		$response = $client->get($this->baseUrl, [
			'query' => array_merge([
				'action' => $method,
				'auth' => $this->authToken,
			], $options)
		]);


		try {
			$xml = $response->xml();
		} catch (\GuzzleHttp\Exception\ParseException $e) {
			throw new AmpacheClientException('Could not parse XML', 0, $e);
		}

		$error = $xml->xpath('/root/error');

		if ($error) {
			throw new AmpacheClientException('Ampache error: ' . $error[0]->__toString() . ' (' . $error[0]->attributes()['code'] . ') ' . $response->getEffectiveUrl());
		}

		return $xml;
	}

}
