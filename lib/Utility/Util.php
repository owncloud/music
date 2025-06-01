<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2018 - 2025
 */

namespace OCA\Music\Utility;

use OCP\Files\Folder;

/**
 * Miscellaneous static utility functions
 */
class Util {

	const UINT32_MAX = 0xFFFFFFFF;
	const SINT32_MAX = 0x7FFFFFFF;
	const SINT32_MIN = -self::SINT32_MAX - 1;

	/**
	 * Extract ID of each array element by calling getId and return
	 * the IDs as an array
	 */
	public static function extractIds(array $arr) : array {
		return \array_map(fn($i) => $i->getId(), $arr);
	}

	/**
	 * Extract User ID of each array element by calling getUserId and return
	 * the IDs as an array
	 */
	public static function extractUserIds(array $arr) : array {
		return \array_map(fn($i) => $i->getUserId(), $arr);
	}

	/**
	 * Create a look-up table from given array of items which have a `getId` function.
	 * @return array where keys are the values returned by `getId` of each item
	 */
	public static function createIdLookupTable(array $array) : array {
		$lut = [];
		foreach ($array as $item) {
			$lut[$item->getId()] = $item;
		}
		return $lut;
	}

	/**
	 * Create a look-up table from given array so that keys of the table are obtained by calling
	 * the given method on each array entry and the values are arrays of entries having the same
	 * value returned by that method.
	 * @param string $getKeyMethod Name of a method found on $array entries which returns a string or an int
	 * @return array [int|string => array]
	 */
	public static function arrayGroupBy(array $array, string $getKeyMethod) : array {
		$lut = [];
		foreach ($array as $item) {
			$lut[$item->$getKeyMethod()][] = $item;
		}
		return $lut;
	}

	/**
	 * Get difference of two arrays, i.e. elements belonging to $b but not $a.
	 * This function is faster than the built-in array_diff for large arrays but
	 * at the expense of higher RAM usage and can be used only for arrays of
	 * integers or strings.
	 * From https://stackoverflow.com/a/8827033
	 */
	public static function arrayDiff(array $b, array $a) : array {
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
	 * Get multiple items from @a $array, as indicated by a second array @a $keys.
	 * If @a $preserveKeys is given as true, the result will have the original keys, otherwise
	 * the result is re-indexed with keys 0, 1, 2, ...
	 */
	public static function arrayMultiGet(array $array, array $keys, bool $preserveKeys=false) : array {
		$result = [];
		foreach ($keys as $key) {
			if ($preserveKeys) {
				$result[$key] = $array[$key];
			} else {
				$result[] = $array[$key];
			}
		}
		return $result;
	}

	/**
	 * Get multiple columns from the multidimensional @a $array. This is similar to the built-in
	 * function \array_column except that this can return multiple columns and not just one.
	 * @param int|string|null $indexColumn
	 */
	public static function arrayColumns(array $array, array $columns, $indexColumn=null) : array {
		if ($indexColumn !== null) {
			$array = \array_column($array, null, $indexColumn);
		}

		return \array_map(fn($row) => self::arrayMultiGet($row, $columns, true), $array);
	}

	/**
	 * Like the built-in function \array_filter but this one works recursively on nested arrays.
	 * Another difference is that this function always requires an explicit callback condition.
	 * Both inner nodes and leafs nodes are passed to the $condition.
	 */
	public static function arrayFilterRecursive(array $array, callable $condition) : array {
		$result = [];

		foreach ($array as $key => $value) {
			if ($condition($value)) {
				if (\is_array($value)) {
					$result[$key] = self::arrayFilterRecursive($value, $condition);
				} else {
					$result[$key] = $value;
				}
			}
		}

		return $result;
	}

	/**
	 * Inverse operation of self::arrayFilterRecursive, keeping only those items where
	 * the $condition evaluates to *false*.
	 */
	public static function arrayRejectRecursive(array $array, callable $condition) : array {
		$invCond = fn($item) => !$condition($item);
		return self::arrayFilterRecursive($array, $invCond);
	}

	/**
	 * Convert the given array $arr so that keys of the potentially multi-dimensional array
	 * are converted using the mapping given in $dictionary. Keys not found from $dictionary
	 * are not altered.
	 */
	public static function convertArrayKeys(array $arr, array $dictionary) : array {
		$newArr = [];

		foreach ($arr as $k => $v) {
			$key = $dictionary[$k] ?? $k;
			$newArr[$key] = \is_array($v) ? self::convertArrayKeys($v, $dictionary) : $v;
		}

		return $newArr;
	}

	/**
	 * Walk through the given, potentially multi-dimensional, array and cast all leaf nodes
	 * to integer type. The array is modified in-place. Optionally, apply the conversion only
	 * on the leaf nodes matching the given predicate.
	 */
	public static function intCastArrayValues(array &$arr, ?callable $predicate=null) : void {
		\array_walk_recursive($arr, function(&$value) use($predicate) {
			if ($predicate === null || $predicate($value)) {
				$value = (int)$value;
			}
		});
	}

	/**
	 * Given a two-dimensional array, sort the outer dimension according to values in the
	 * specified column of the inner dimension.
	 */
	public static function arraySortByColumn(array &$arr, string $column) : void {
		\usort($arr, fn($a, $b) => StringUtil::caselessCompare($a[$column], $b[$column]));
	}

	/**
	 * Like the built-in \explode(...) function but this one can be safely called with
	 * null string, and no warning will be emitted. Also, this returns an empty array from
	 * null and '' inputs while the built-in alternative returns a 1-item array containing
	 * an empty string.
	 * @param string $delimiter
	 * @param string|null $string
	 * @return array
	 */
	public static function explode(string $delimiter, ?string $string) : array {
		if ($delimiter === '') {
			throw new \UnexpectedValueException();
		} elseif ($string === null || $string === '') {
			return [];
		} else {
			return \explode($delimiter, $string);
		}
	}

	/**
	 * Convert file size given in bytes to human-readable format
	 */
	public static function formatFileSize(?int $bytes, int $decimals = 1) : ?string {
		if ($bytes === null) {
			return null;
		} else {
			$units = 'BKMGTP';
			$factor = \floor((\strlen((string)$bytes) - 1) / 3);
			return \sprintf("%.{$decimals}f", $bytes / \pow(1024, $factor)) . @$units[(int)$factor];
		}
	}

	/**
	 * Convert time given as seconds to the HH:MM:SS format
	 */
	public static function formatTime(?int $seconds) : ?string {
		if ($seconds === null) {
			return null;
		} else {
			return \sprintf('%02d:%02d:%02d', ($seconds/3600), ($seconds/60%60), $seconds%60);
		}
	}

	/**
	 * Convert date and time given in the SQL format to the ISO UTC "Zulu format" e.g. "2021-08-19T19:33:15Z"
	 */
	public static function formatZuluDateTime(?string $dbDateString) : ?string {
		if ($dbDateString === null) {
			return null;
		} else {
			$dateTime = new \DateTime($dbDateString);
			return $dateTime->format('Y-m-d\TH:i:s.v\Z');
		}
	}

	/**
	 * Convert date and time given in the SQL format to the ISO UTC "offset format" e.g. "2021-08-19T19:33:15+00:00"
	 */
	public static function formatDateTimeUtcOffset(?string $dbDateString) : ?string {
		if ($dbDateString === null) {
			return null;
		} else {
			$dateTime = new \DateTime($dbDateString);
			return $dateTime->format('c');
		}
	}

	/**
	 * Encode a file path so that it can be used as part of a WebDAV URL
	 */
	public static function urlEncodePath(string $path) : string {
		// URL encode each part of the file path
		return \join('/', \array_map('rawurlencode', \explode('/', $path)));
	}

	/**
	 * Compose URL from parts as returned by the system function parse_url.
	 * From https://stackoverflow.com/a/35207936
	 */
	public static function buildUrl(array $parts) : string {
		return (isset($parts['scheme']) ? "{$parts['scheme']}:" : '') .
				((isset($parts['user']) || isset($parts['host'])) ? '//' : '') .
				(isset($parts['user']) ? "{$parts['user']}" : '') .
				(isset($parts['pass']) ? ":{$parts['pass']}" : '') .
				(isset($parts['user']) ? '@' : '') .
				(isset($parts['host']) ? "{$parts['host']}" : '') .
				(isset($parts['port']) ? ":{$parts['port']}" : '') .
				(isset($parts['path']) ? "{$parts['path']}" : '') .
				(isset($parts['query']) ? "?{$parts['query']}" : '') .
				(isset($parts['fragment']) ? "#{$parts['fragment']}" : '');
	}

	/**
	 * Swap values of two variables in place
	 * @param mixed $a
	 * @param mixed $b
	 */
	public static function swap(&$a, &$b) : void {
		$temp = $a;
		$a = $b;
		$b = $temp;
	}

	/**
	 * Limit an integer value between the specified minimum and maximum.
	 * A null value is a valid input and will produce a null output.
	 * @param int|float|null $input
	 * @param int|float $min
	 * @param int|float $max
	 * @return int|float|null
	 */
	public static function limit($input, $min, $max) {
		if ($input === null) {
			return null;
		} else {
			return \max($min, \min($input, $max));
		}
	}
}
