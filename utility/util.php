<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2018
 */

namespace OCA\Music\Utility;

/**
 * Miscellaneous static utility functions
 */
class Util {

	/**
	 * Extract ID of each array element by calling getId and return
	 * the IDs as array
	 * @param array $arr
	 * @return array
	 */
	public static function extractIds(array $arr) {
		return \array_map(function ($i) {
			return $i->getId();
		}, $arr);
	}

	/**
	 * Get difference of two arrays, i.e. elements belonging to $b but not $a.
	 * This function is faster than the built-in array_diff for large arrays but
	 * at the expense of higher RAM usage and can be used only for arrays of
	 * integers or strings.
	 * From https://stackoverflow.com/a/8827033
	 * @param array $b
	 * @param array $a
	 * @return array
	 */
	public static function arrayDiff($b, $a) {
		$at = \array_flip($a);
		$d = [];
		foreach ($b as $i) {
			if (!isset($at[$i])) {
				$d[] = $i;
			}
		}
		return $d;
	}

	/**
	 * Test if given string starts with another given string
	 * @param string $string
	 * @param string $potentialStart
	 * @return boolean
	 */
	public static function startsWith($string, $potentialStart) {
		return \substr($string, 0, \strlen($potentialStart)) === $potentialStart;
	}
}
