<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2019, 2020
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
	/** @var array values stored to be passed to the next step(s) */
	private $storedValues = [];

	private static function tableSize(TableNode $table) {
		// getHash() doesn't return the header of the table
		return \count($table->getHash());
	}

	private static function startsWith($string, $potentialStart) {
		return \substr($string, 0, \strlen($potentialStart)) === $potentialStart;
	}

	private static function resultElementForResource($resource) {
		if ($resource === 'getMusicDirectory') {
			return 'directory';
		} elseif ($resource === 'createPlaylist') {
			return '';
		} elseif (self::startsWith($resource, 'get')) {
			return \lcfirst(\substr($resource, 3));
		} elseif (self::startsWith($resource, 'search')) {
			return \substr($resource, 0, 6) . 'Result' . \substr($resource, 6);
		} else {
			throw new \Exception("Resource $resource not supported in test context");
		}
	}

	private function xpath($path) {
		// drop double slashes (could occur when a path segment is empty)
		$path = \str_replace('//', '/', $path);
		// add the namespace prefix to each path segment
		$path = \str_replace('/', '/ss:', $path);

		return $this->xml->xpath($path);
	}

	private function storeAttributeFromXmlResult($attr, $entryType, $entryIndex, $storeName = null) {
		$elements = $this->xpath('/subsonic-response/' .
				self::resultElementForResource($this->resource) . '/' . $entryType);

		$element = $elements[$entryIndex];

		if (empty($storeName)) {
			$storeName = $attr;
		}

		$this->storedValues[$storeName] = $element[$attr]->__toString();
	}

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
	 * @When I request the :resource resource with parameter :option having value :value
	 */
	public function iRequestTheResourceWithParameterHavingValue($resource, $option, $value) {
		$this->iSpecifyTheParameterWithValue($option, $value);
		$this->iRequestTheResource($resource);
	}

	/**
	 * @When I request the :resource resource in JSON
	 */
	public function iRequestTheResourceInJson($resource) {
		$this->json = $this->client->requestJson($resource, $this->options);
		$this->resource = $resource;
	}

	/**
	 * @Then I should get empty XML response
	 */
	public function iShouldGetEmptyXmlResponse() {
		$rootElem = $this->xpath('/subsonic-response')[0];
		if ($rootElem->count() > 0) {
			throw new \Exception('<subsonic-response> has ' . $rootElem->count() . ' children while none expected');
		}
	}

	/**
	 * @Then I should get XML with :entryType entry/entries:
	 * @Then the XML result should contain :entryType entry/entries:
	 */
	public function iShouldGetXmlWithEntries($entryType, TableNode $table) {
		$elements = $this->xpath('/subsonic-response/' .
			self::resultElementForResource($this->resource) . '/' . $entryType);

		$expectedIterator = $table->getIterator();
		foreach ($elements as $element) {
			$expectedElement = $expectedIterator->current();
			$expectedIterator->next();

			if ($expectedElement === null) {
				throw new \Exception('More results than expected');
			}

			foreach ($expectedElement as $key => $expectedValue) {
				$actualValue = $element[$key];
				if ($actualValue != $expectedValue) {
					throw new \Exception(\ucfirst($key) . " does not match - expected: '$expectedValue'" .
										" got: '$actualValue'" . PHP_EOL . $this->xml->asXML());
				}
			}
		}

		$expectedCount = self::tableSize($table);
		$actualCount = \count($elements);
		if ($expectedCount !== $actualCount) {
			throw new \Exception('Not all elements are in the result set - ' . $actualCount .
								' does not match the expected ' . $expectedCount . PHP_EOL . $this->xml->asXML());
		}
	}

	/**
	 * @Then I should get JSON with :entryType entry/entries:
	 */
	public function iShouldGetJson($entryType, TableNode $table) {
		$resultElement = self::resultElementForResource($this->resource);
		$elements = $this->json['subsonic-response'][$resultElement][$entryType];

		$expectedIterator = $table->getIterator();
		foreach ($elements as $element) {
			$expectedElement = $expectedIterator->current();
			$expectedIterator->next();

			if ($expectedElement === null) {
				throw new \Exception('More results than expected');
			}

			foreach ($expectedElement as $key => $expectedValue) {
				$actualValue = $element[$key];
				if ($actualValue != $expectedValue) {
					throw new \Exception(\ucfirst($key) . " does not match - expected: '$expectedValue'" .
										" got: '$actualValue'" . PHP_EOL . \json_encode($this->json));
				}
			}
		}

		$expectedCount = self::tableSize($table);
		$actualCount = \count($elements);
		if ($expectedCount !== $actualCount) {
			throw new \Exception('Not all elements are in the result set - ' . $actualCount .
								' does not match the expected ' . $expectedCount . PHP_EOL . \json_encode($this->json));
		}
	}

	/**
	 * @Then I should get XML containing :expectedCount :entryType entry/entries
	 * @Then the XML result should contain :expectedCount :entryType entry/entries
	 */
	public function iShouldGetXmlContainingEntries($expectedCount, $entryType) {
		$elements = $this->xpath('/subsonic-response/' .
			self::resultElementForResource($this->resource) . '/' . $entryType);
		$actualCount = \count($elements);

		if ((int)$expectedCount !== $actualCount) {
			throw new \Exception('Unexpected number of entries in the result set - ' . $actualCount .
								' does not match the expected ' . $expectedCount . PHP_EOL . $this->xml->asXML());
		}
	}

	/**
	 * @Given I store the attribute :attr from the first :entryType XML element
	 */
	public function iStoreTheFirstFromTheXmlResult($attr, $entryType) {
		$this->storeAttributeFromXmlResult($attr, $entryType, 0);
	}

	/**
	 * @Given I store the attribute :attr from the first :entryType XML element as :storeName
	 */
	public function iStoreTheFirstFromTheXmlResultAs($attr, $entryType, $storeName) {
		$this->storeAttributeFromXmlResult($attr, $entryType, 0, $storeName);
	}

	/**
	 * @Given I store the attribute :attr from the second :entryType XML element
	 */
	public function iStoreTheSecondFromTheXmlResult($attr, $entryType) {
		$this->storeAttributeFromXmlResult($attr, $entryType, 1);
	}

	/**
	 * @Given I have stored :attr from the :entryType matching :query
	 */
	public function iHaveStoredAttrFromTheEntryMatchingQuery($attr, $entryType, $query) {
		$this->iSpecifyTheParameterWithValue('query', $query);
		$this->iRequestTheResource('search2');
		$this->iStoreTheFirstFromTheXmlResult($attr, $entryType);
	}

	/**
	 * @When I specify the parameter :option with the stored value
	 */
	public function iSpecifyTheParameterWithTheStoredValue($option) {
		$this->options[$option] = $this->storedValues[$option];
	}

	/**
	 * @When I specify the parameter :option with the stored value of :source
	 */
	public function iSpecifyTheParameterWithTheStoredValueOf($option, $source) {
		$this->options[$option] = $this->storedValues[$source];
	}
}
