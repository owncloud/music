<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2025
 */

namespace OCA\Music\Utility;

/**
 * Static utility functions to work with arrays
 */
class ArrayUtil {

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
	public static function groupBy(array $array, string $getKeyMethod) : array {
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
	public static function diff(array $b, array $a) : array {
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
	public static function multiGet(array $array, array $keys, bool $preserveKeys=false) : array {
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
	public static function columns(array $array, array $columns, $indexColumn=null) : array {
		if ($indexColumn !== null) {
			$array = \array_column($array, null, $indexColumn);
		}

		return \array_map(fn($row) => self::multiGet($row, $columns, true), $array);
	}

	/**
	 * Like the built-in function \array_filter but this one works recursively on nested arrays.
	 * Another difference is that this function always requires an explicit callback condition.
	 * Both inner nodes and leafs nodes are passed to the $condition.
	 */
	public static function filterRecursive(array $array, callable $condition) : array {
		$result = [];

		foreach ($array as $key => $value) {
			if ($condition($value)) {
				if (\is_array($value)) {
					$result[$key] = self::filterRecursive($value, $condition);
				} else {
					$result[$key] = $value;
				}
			}
		}

		return $result;
	}

	/**
	 * Inverse operation of self::filterRecursive, keeping only those items where
	 * the $condition evaluates to *false*.
	 */
	public static function rejectRecursive(array $array, callable $condition) : array {
		$invCond = fn($item) => !$condition($item);
		return self::filterRecursive($array, $invCond);
	}

	/**
	 * Convert the given array $arr so that keys of the potentially multi-dimensional array
	 * are converted using the mapping given in $dictionary. Keys not found from $dictionary
	 * are not altered.
	 */
	public static function convertKeys(array $arr, array $dictionary) : array {
		$newArr = [];

		foreach ($arr as $k => $v) {
			$key = $dictionary[$k] ?? $k;
			$newArr[$key] = \is_array($v) ? self::convertKeys($v, $dictionary) : $v;
		}

		return $newArr;
	}

	/**
	 * Walk through the given, potentially multi-dimensional, array and cast all leaf nodes
	 * to integer type. The array is modified in-place. Optionally, apply the conversion only
	 * on the leaf nodes matching the given predicate.
	 */
	public static function intCastValues(array &$arr, ?callable $predicate=null) : void {
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
	public static function sortByColumn(array &$arr, string $column) : void {
		\usort($arr, fn($a, $b) => StringUtil::caselessCompare($a[$column], $b[$column]));
	}

}