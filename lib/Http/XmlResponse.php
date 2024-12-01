<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2019 - 2024
 */

namespace OCA\Music\Http;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Response;

/**
 * This class creates an XML response out of a passed in associative array,
 * similarly how the class JSONResponse works. The content is described with
 * a recursive array structure, where arrays may have string or integer keys.
 * One array should not mix string and integer keys, that will lead to undefined
 * outcome. Furthermore, array with integer keys is supported only as payload of
 * an array with string keys.
 *
 * Note that this response type has been created to fulfill the needs of the
 * SubsonicController and AmpacheController and may not be suitable for all other
 * purposes.
 */
class XmlResponse extends Response {
	private array $content;
	private \DOMDocument $doc;
	/* @var bool|string[] $attributeKeys */
	private $attributeKeys;
	private bool $boolAsInt;
	private bool $nullAsEmpty;
	private ?string $textNodeKey;

	/**
	 * @param array $content
	 * @param bool|string[] $attributes If true, then key-value pair is made into attribute if possible.
	 *                                  If false, then key-value pairs are never made into attributes.
	 *                                  If an array, then keys found from the array are made into attributes if possible.
	 * @param bool $boolAsInt If true, any boolean values are yielded as int 0/1.
	 *                        If false, any boolean values are yielded as string "false"/"true".
	 * @param bool $nullAsEmpty If true, any null values are converted to empty strings, and the result has an empty element or attribute.
	 *                          If false, any null-valued keys are are left out from the result.
	 * @param ?string $textNodeKey When a key within @a $content matches this, the corresponding value is converted to a text node,
	 *                             instead of creating an element or attribute named by the key.
	 */
	public function __construct(array $content, /*mixed*/ $attributes=true,
								bool $boolAsInt=false, bool $nullAsEmpty=false,
								?string $textNodeKey='value') {
		$this->setStatus(Http::STATUS_OK);
		$this->addHeader('Content-Type', 'application/xml');

		// The content must have exactly one root element, add one if necessary
		if (\count($content) != 1) {
			$content = ['root' => $content];
		}
		$this->content = $content;
		$this->doc = new \DOMDocument('1.0', 'UTF-8');
		$this->doc->formatOutput = true;
		$this->attributeKeys = $attributes;
		$this->boolAsInt = $boolAsInt;
		$this->nullAsEmpty = $nullAsEmpty;
		$this->textNodeKey = $textNodeKey;
	}

	public function render() {
		$rootName = \array_keys($this->content)[0];
		// empty content is a special case which cannot be handled by the standard recursive manner
		if (empty($this->content[$rootName])) {
			$this->doc->appendChild($this->doc->createElement($rootName));
		} else {
			$this->addChildElement($this->doc, $rootName, $this->content[$rootName]);
		}
		return $this->doc->saveXML();
	}

	private function addChildElement($parentNode, $key, $value, $allowAttribute=true) {
		if (\is_bool($value)) {
			if ($this->boolAsInt) {
				$value = $value ? '1' : '0';
			} else {
				$value = $value ? 'true' : 'false';
			}
		} elseif (\is_numeric($value)) {
			$value = (string)$value;
		} elseif ($value === null && $this->nullAsEmpty) {
			$value = '';
		}

		if (\is_string($value)) {
			if ($key == $this->textNodeKey) {
				$parentNode->appendChild($this->doc->createTextNode($value));
			} elseif ($allowAttribute && $this->keyMayDefineAttribute($key)) {
				$parentNode->setAttribute($key, $value);
			} else {
				$child = $this->doc->createElement($key);
				$child->appendChild($this->doc->createTextNode($value));
				$parentNode->appendChild($child);
			}
		} elseif (\is_array($value)) {
			if (self::arrayIsIndexed($value)) {
				foreach ($value as $child) {
					$this->addChildElement($parentNode, $key, $child, /*allowAttribute=*/false);
				}
			} else { // associative array
				$element = $this->doc->createElement($key);
				$parentNode->appendChild($element);
				foreach ($value as $childKey => $childValue) {
					$this->addChildElement($element, $childKey, $childValue);
				}
			}
		} elseif ($value instanceof \stdClass) {
			// empty element
			$element = $this->doc->createElement($key);
			$parentNode->appendChild($element);
		} elseif ($value === null) {
			// skip
		} else {
			throw new \Exception("Unexpected value type for key $key");
		}
	}

	private function keyMayDefineAttribute($key) {
		if (\is_array($this->attributeKeys)) {
			return \in_array($key, $this->attributeKeys);
		} else {
			return \boolval($this->attributeKeys);
		}
	}

	/**
	 * Array is considered to be "indexed" if its first element has numerical key.
	 * Empty array is considered to be "indexed".
	 * @param array $array
	 */
	private static function arrayIsIndexed(array $array) {
		\reset($array);
		return empty($array) || \is_int(\key($array));
	}
}
