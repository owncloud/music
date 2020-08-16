<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2018 - 2020
 */

namespace OCA\Music\Utility;

/**
 * Miscellaneous static utility functions
 */
class Util {

	/**
	 * Extract ID of each array element by calling getId and return
	 * the IDs as an array
	 * @param array $arr
	 * @return array
	 */
	public static function extractIds(array $arr) {
		return \array_map(function ($i) {
			return $i->getId();
		}, $arr);
	}

	/**
	 * Extract User ID of each array element by calling getUserId and return
	 * the IDs as an array
	 * @param array $arr
	 * @return array
	 */
	public static function extractUserIds(array $arr) {
		return \array_map(function ($i) {
			return $i->getUserId();
		}, $arr);
	}

	/**
	 * Create look-up table from given array of items which have a `getId` function.
	 * @param array $array
	 * @return array where keys are the values returned by `getId` of each item
	 */
	public static function createIdLookupTable(array $array) {
		$lut = [];
		foreach ($array as $item) {
			$lut[$item->getId()] = $item;
		}
		return $lut;
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
	public static function arrayDiff(array $b, array $a) {
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
	 * Get multiple items from @a $array, as indicated by a second array @a $indices.
	 * @param array $array
	 * @param array $indices
	 * @return array
	 */
	public static function arrayMultiGet(array $array, array $indices) {
		$result = [];
		foreach ($indices as $index) {
			$result[] = $array[$index];
		}
		return $result;
	}

	/**
	 * Convert the given array $arr so that keys of the potentially multi-dimensional array
	 * are converted using the mapping given in $dictionary. Keys not found from $dictionary
	 * are not altered. 
	 * @param array $arr
	 * @param array $dictionary
	 * @return array
	 */
	public static function convertArrayKeys(array $arr, array $dictionary) {
		$newArr = [];

		foreach ($arr as $k => $v) {
			$key = self::arrayGetOrDefault($dictionary, $k, $k);
			$newArr[$key] = is_array($v) ? self::convertArrayKeys($v, $dictionary) : $v;
		}

		return $newArr;
	}

	/**
	 * Get array value if exists, otherwise return a default value or null.
	 * The function supports getting value from a nested array by giving an array-type $key.
	 * In that case, the first value of the $key is used on the outer-most array, second value
	 * from $key on the next level, and so on. That is, arrayGetOrDefault($arr, ['a', 'b', 'c'])
	 * will return $arr['a']['b']['c'] is all the keys along the path are found.
	 *
	 * @param array $array
	 * @param int|string|array $key
	 * @param mixed|null $default
	 * @return mixed|null
	 */
	public static function arrayGetOrDefault(array $array, $key, $default=null) {
		if (!\is_array($key)) {
			$key = [$key];
		}

		$temp = $array;
		foreach ($key as $k) {
			if (isset($temp[$k])) {
				$temp = $temp[$k];
			} else {
				return $default;
			}
		}
		return $temp;
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
	 * @param boolean $ignoreCase
	 * @return boolean
	 */
	public static function startsWith($string, $potentialStart, $ignoreCase=false) {
		$actualStart = \substr($string, 0, \strlen($potentialStart));
		if ($ignoreCase) {
			$actualStart= \mb_strtolower($actualStart);
			$potentialStart= \mb_strtolower($potentialStart);
		}
		return $actualStart === $potentialStart;
	}

	/**
	 * Test if given string ends with another given string
	 * @param string $string
	 * @param string $potentialEnd
	 * @param boolean $ignoreCase
	 * @return boolean
	 */
	public static function endsWith($string, $potentialEnd, $ignoreCase=false) {
		$actualEnd = \substr($string, -\strlen($potentialEnd));
		if ($ignoreCase) {
			$actualEnd = \mb_strtolower($actualEnd);
			$potentialEnd = \mb_strtolower($potentialEnd);
		}
		return $actualEnd === $potentialEnd;
	}

	/**
	 * Multi-byte safe case-insensitive string comparison
	 * @param string $a
	 * @param string $b
	 * @return int < 0 if $a is less than $b; > 0 if $a is greater than $b, and 0 if they are equal. 
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

	/**
	 * Create relative path from the given working dir (CWD) to the given target path
	 * @param string $cwdPath Absolute CWD path
	 * @param string $targetPath Absolute target path
	 * @return string
	 */
	public static function relativePath($cwdPath, $targetPath) {
		$cwdParts = \explode('/', $cwdPath);
		$targetParts = \explode('/', $targetPath);

		// remove the common prefix of the paths
		while (\count($cwdParts) > 0 && \count($targetParts) > 0 && $cwdParts[0] === $targetParts[0]) {
			\array_shift($cwdParts);
			\array_shift($targetParts);
		}

		// prepend up-navigation from CWD to the closest common parent folder with the target
		for ($i = 0, $count = \count($cwdParts); $i < $count; ++$i) {
			\array_unshift($targetParts, '..');
		}

		return \implode('/', $targetParts);
	}

	/**
	 * Given a current working directory path (CWD) and a relative path (possibly containing '..' parts),
	 * form an absolute path matching the relative path. This is a reverse operation for Util::relativePath().
	 * @param string $cwdPath
	 * @param string $relativePath
	 * @return string
	 */
	public static function resolveRelativePath($cwdPath, $relativePath) {
		$cwdParts = \explode('/', $cwdPath);
		$relativeParts = \explode('/', $relativePath);

		// get rid of the trailing empty part of CWD which appears when CWD has a trailing '/'
		if ($cwdParts[\count($cwdParts)-1] === '') {
			\array_pop($cwdParts);
		}

		foreach ($relativeParts as $part) {
			if ($part === '..') {
				\array_pop($cwdParts);
			} else {
				\array_push($cwdParts, $part);
			}
		}

		return \implode('/', $cwdParts);
	}

	/**
	 * Encode a file path so that it can be used as part of a WebDAV URL
	 * @param string $path
	 * @return string
	 */
	public static function urlEncodePath($path) {
		// URL encode each part of the file path
		return \join('/', \array_map('rawurlencode', \explode('/', $path)));
	}

	/**
	 * Swap values of two variables in place
	 * @param mixed $a
	 * @param mixed $b
	 */
	public static function swap(&$a, &$b) {
		$temp = $a;
		$a = $b;
		$b = $temp;
	}
}
