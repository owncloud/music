<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2020 - 2024
 */

namespace OCA\Music\Utility;

use OCA\Music\AppFramework\Core\Logger;
use OCA\Music\Db\Cache;

class Random {
	private Cache $cache;
	private Logger $logger;

	public function __construct(Cache $cache, Logger $logger) {
		$this->cache = $cache;
		$this->logger = $logger;
	}

	/**
	 * Create cryptographically secure random string
	 */
	public static function secure(int $length) : string {
		return \bin2hex(\random_bytes($length));
	}

	/**
	 * Get one random item from the given array. Return null if the array is empty.
	 */
	public static function pickItem(array $itemArray) {
		if (empty($itemArray)) {
			return null;
		} else {
			$rndIdx = \array_rand($itemArray, 1);
			return $itemArray[$rndIdx];
		}
	}

	/**
	 * Get desired number of random items from the given array
	 *
	 * @param array $itemArray
	 * @param int $count
	 * @return array
	 */
	public static function pickItems(array $itemArray, int $count) : array {
		$count = \min($count, \count($itemArray)); // can't return more than all items

		if ($count == 0) {
			return [];
		} else {
			$indices = \array_rand($itemArray, $count);
			if (!\is_array($indices)) { // array_rand does not return an array if $count == 1
				$indices = [$indices];
			}
			\shuffle($indices);

			return Util::arrayMultiGet($itemArray, $indices);
		}
	}

	/**
	 * Get desired number of random array indices. This function supports paging
	 * so that all the indices can be browsed through page-by-page, without getting
	 * the same index more than once. This requires persistence and identifying the
	 * logical array in question. The array is identified by the user ID and a free
	 * text identifier supplied by the caller.
	 *
	 * For a single logical array, the indices are shuffled every time when the
	 * page 0 is requested. Also, if the size of the array in question has changed
	 * since the previous call, then the indices are reshuffled.
	 *
	 * @param int $arrSize Size of the array for which random indices are to be generated
	 * @param int|null $offset Offset to get only part of the results (paging), null implies offset 0
	 * @param int|null $count Result size to get only part of the results (paging), null gets all the remaining indices from the @a offset
	 * @param string $userId The current user ID
	 * @param string $arrId Identifier for the logical array to facilitate paging
	 * @return int[]
	 */
	public function getIndices(int $arrSize, ?int $offset, ?int $count, string $userId, string $arrId) : array {
		$offset = $offset ?? 0;
		$cacheKey = 'random_indices_' . $arrId;

		$indices = self::decodeIndices($this->cache->get($userId, $cacheKey));

		// reshuffle if necessary
		if ($offset === 0 || \count($indices) != $arrSize) {
			if ($arrSize > 0) {
				$indices = \range(0, $arrSize - 1);
			} else {
				$indices = [];
			}
			\shuffle($indices);
			$this->cache->set($userId, $cacheKey, self::encodeIndices($indices));
		}

		return \array_slice($indices, $offset, $count);
	}

	private static function encodeIndices($indices) {
		return \implode(',', $indices);
	}

	private static function decodeIndices($buffer) {
		if (empty($buffer)) {
			return [];
		} else {
			return \explode(',', $buffer);
		}
	}
}
