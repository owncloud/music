<?php declare(strict_types=1);
/**
 * ownCloud
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Alessandro Cosentino <cosenal@gmail.com>
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Alessandro Cosentino 2012
 * @copyright Bernhard Posselt 2012, 2014
 * @copyright Pauli Järvinen 2018 - 2024
 */

namespace OCA\Music\AppFramework\Utility;

/**
 * Reads and parses annotations from doc comments
 */
class MethodAnnotationReader {
	private array $annotations;

	/**
	 * @param object|string $object an object or classname
	 * @param string $method the method which we want to inspect for annotations
	 */
	public function __construct($object, string $method) {
		$this->annotations = [];

		$reflection = new \ReflectionMethod($object, $method);
		$docs = $reflection->getDocComment();

		// extract everything prefixed by @ and first letter uppercase
		$matches = null;
		\preg_match_all('/@([A-Z]\w+)/', $docs, $matches);
		$this->annotations = $matches[1];
	}

	/**
	 * Check if a method contains an annotation
	 * @param string $name the name of the annotation
	 * @return bool true if the annotation is found
	 */
	public function hasAnnotation(string $name) : bool {
		return \in_array($name, $this->annotations);
	}
}
