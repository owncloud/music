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
	 * Truncate the given string to maximum length, appendig ellipsis character
	 * if the truncation happened. Also null argument may be safely passed and
	 * it remains unaltered.
	 * @param string|null $string
	 * @param int $maxLength
	 * @return string|null
	 */
	public static function truncate($string, $maxLength) {
		if ($string === null) {
			return null;
		} else {
			return \mb_strimwidth($string, 0, $maxLength, "\u{2026}");
		}
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

	/**
	 * Test if given string ends with another given string
	 * @param string $string
	 * @param string $potentialEnd
	 * @return boolean
	 */
	public static function endsWith($string, $potentialEnd) {
		return \substr($string, -\strlen($potentialEnd)) === $potentialEnd;
	}

	/**
	 * Multi-byte safe case-insensitive string comparison
	 * @param string $a
	 * @param string $b
	 */
	public static function stringCaseCompare($a, $b) {
		return \strcmp(\mb_strtolower($a), \mb_strtolower($b));
	}

	/**
	 * Convert file size given in bytes to human-readable format
	 * @param int $bytes
	 * @param int $decimals
	 * @return string
	 */
	public static function formatFileSize($bytes, $decimals = 1) {
		$units = 'BKMGTP';
		$factor = \floor((\strlen($bytes) - 1) / 3);
		return \sprintf("%.{$decimals}f", $bytes / \pow(1024, $factor)) . @$units[(int)$factor];
	}

	/**
	 * @param Folder $parentFolder
	 * @param string $relativePath
	 * @return Folder
	 */
	public static function getFolderFromRelativePath($parentFolder, $relativePath) {
		if ($relativePath !== null && $relativePath !== '/' && $relativePath !== '') {
			return $parentFolder->get($relativePath);
		} else {
			return $parentFolder;
		}
	}
}
