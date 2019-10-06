<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2019
 */

use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Gherkin\Node\TableNode;

/**
 * Defines application features from the specific context.
 */
class SubsonicContext implements Context, SnippetAcceptingContext {
	private $client;
	/** @var  SimpleXMLElement */
	private $xml;
	/** @var  array */
	private $json;
	/** @var string specifies the requested resource */
	private $resource;
	/** @var array options to pass to the Subsonic API request */
	private $options = [];

	/** @var array maps resources to the name of the XML element of the response */
	private $resourceToXMLElementMapping = [
		'getAlbumList' => 'albumList/album',
		'getAlbumList2' => 'albumList2/album',
	];

	/**
	 * Initializes context.
	 *
	 * Every scenario gets its own context instance.
	 * You can also pass arbitrary arguments to the
	 * context constructor through behat.yml.
	 *
	 * @param $baseUrl
	 * @param $username
	 * @param $password
	 */
	public function __construct($baseUrl, $username, $password) {
		$this->client = new SubsonicClient($baseUrl, $username, $password);
	}

	/**
	 * @When I specify the parameter :option with value :value
	 */
	public function iSpecifyTheParameterWithValue($option, $value) {
		$this->options[$option] = $value;
	}

	/**
	 * @When I request the :resource resource
	 */
	public function iRequestTheResource($resource) {
		$this->xml = $this->client->request($resource, $this->options);
		$this->resource = $resource;
	}

	/**
	 * @When I request the :resource resource in JSON
	 */
	public function iRequestTheResourceInJson($resource) {
		$this->json = $this->client->requestJson($resource, $this->options);
		$this->resource = $resource;
	}

	/**
	 * @Then I should get XML:
	 */
	public function iShouldGetXml(TableNode $table) {
		$elements = $this->xml->xpath('/subsonic-response/' .
			$this->resourceToXMLElementMapping[$this->resource]);

		$expectedIterator = $table->getIterator();
		foreach ($elements as $element) {
			$expectedElement = $expectedIterator->current();
			$expectedIterator->next();

			if ($expectedElement === null) {
				throw new Exception('More results than expected');
			}

			foreach ($expectedElement as $key => $expectedValue) {
				$actualValue = $element[$key];
				if ($actualValue != $expectedValue) {
					throw new Exception(\ucfirst($key) . " does not match - expected: '$expectedValue'" .
										" got: '$actualValue'" . PHP_EOL . $this->xml->asXML());
				}
			}
		}

		// getHash() doesn't return the header of the table
		$expectedCount = \count($table->getHash());
		$actualCount = \count($elements);
		if ($expectedCount !== $actualCount) {
			throw new Exception('Not all elements are in the result set - ' . $actualCount . 
								' does not match the expected ' . $expectedCount . PHP_EOL . $this->xml->asXML());
		}
	}

	/**
	 * @Then I should get JSON:
	 */
	public function iShouldGetJson(TableNode $table) {
		$xmlPath = $this->resourceToXMLElementMapping[$this->resource];
		$nodeNames = \explode('/', $xmlPath);
		$elements = $this->json['subsonic-response'][$nodeNames[0]][$nodeNames[1]];

		$expectedIterator = $table->getIterator();
		foreach ($elements as $element) {
			$expectedElement = $expectedIterator->current();
			$expectedIterator->next();

			if ($expectedElement === null) {
				throw new Exception('More results than expected');
			}

			foreach ($expectedElement as $key => $expectedValue) {
				$actualValue = $element[$key];
				if ($actualValue != $expectedValue) {
					throw new Exception(\ucfirst($key) . " does not match - expected: '$expectedValue'" .
										" got: '$actualValue'" . PHP_EOL . $this->xml->asXML());
				}
			}
		}

		// getHash() doesn't return the header of the table
		$expectedCount = \count($table->getHash());
		$actualCount = \count($elements);
		if ($expectedCount !== $actualCount) {
			throw new Exception('Not all elements are in the result set - ' . $actualCount . 
								' does not match the expected ' . $expectedCount . PHP_EOL . $this->xml->asXML());
		}
	}
}
