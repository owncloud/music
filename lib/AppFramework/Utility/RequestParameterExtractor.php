<?php declare(strict_types=1);
/**
 * ownCloud
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2021
 */

namespace OCA\Music\AppFramework\Utility;

use OCP\IRequest;

/**
 * Reads and parses annotations from doc comments
 */
class RequestParameterExtractor {
	private $request;

	public function __construct(IRequest $request) {
		$this->request = $request;
	}

	/**
	 * @param object|string $object an object or classname
	 * @param string $method the method for which we want to extract parameters from the HTTP request
	 * @throws RequestParameterExtractorException if a required parameter is not found from the request
	 * @return array of mixed types (string, int, bool, null)
	 */
	public function getParametersForMethod($object, string $method) : array {
		$refMethod = new \ReflectionMethod($object, $method);
		return \array_map([$this, 'getParameterValueFromRequest'], $refMethod->getParameters());
	}

	/**
	 * @throws RequestParameterExtractorException
	 * @return string|int|bool|null
	 */
	private function getParameterValueFromRequest(\ReflectionParameter $parameter) {
		$paramName = $parameter->getName();
		$type = (string)$parameter->getType();

		if ($type === 'array') {
			$parameterValue = $this->getRepeatedParam($paramName);
		} else {
			$parameterValue = $this->request->getParam($paramName);
		}

		if ($parameterValue === null) {
			if ($parameter->isOptional()) {
				$parameterValue = $parameter->getDefaultValue();
			} elseif (!$parameter->allowsNull()) {
				throw new RequestParameterExtractorException("Required parameter '$paramName' missing");
			}
		} else {
			// cast non-null values to requested type
			if ($type === 'int' || $type === 'integer') {
				$parameterValue = (int)$parameterValue;
			} elseif ($type === 'bool' || $type === 'boolean') {
				$parameterValue = \filter_var($parameterValue, FILTER_VALIDATE_BOOLEAN);
			}
		}

		return $parameterValue;
	}

	/**
	 * Get values for parameter which may be present multiple times in the query string or POST data.
	 * @return string[]
	 */
	private function getRepeatedParam(string $paramName) : array {
		// We can't use the IRequest object nor $_GET and $_POST to get the data
		// because all of these are based to the idea of unique parameter names.
		// If the same name is repeated, only the last value is saved. Hence, we
		// need to parse the raw data manually.
		
		// query string is always present (although it could be empty)
		$values = self::parseRepeatedKeyValues($paramName, $_SERVER['QUERY_STRING']);

		// POST data is available if the method is POST
		if ($this->request->getMethod() == 'POST') {
			$values = \array_merge($values,
					self::parseRepeatedKeyValues($paramName, \file_get_contents('php://input')));
		}

		return $values;
	}

	/**
	 * Parse a string like "someKey=value1&someKey=value2&anotherKey=valueA&someKey=value3"
	 * and return an array of values for the given key
	 * @return string[]
	 */
	private static function parseRepeatedKeyValues(string $key, string $data) : array {
		$result = [];

		$keyValuePairs = \explode('&', $data);

		foreach ($keyValuePairs as $pair) {
			$keyAndValue = \explode('=', $pair);
			
			if ($keyAndValue[0] == $key) {
				$result[] = $keyAndValue[1];
			}
		}

		return $result;
	}
}
