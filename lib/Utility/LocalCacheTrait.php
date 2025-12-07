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
 * @template CachedType
 */
trait LocalCacheTrait {

	/** @phpstan-var array<string, array<string, CachedType>> $localCache */
	protected array $localCache = [];

	/**
	 * Read value from the local cache or fall back to using the supplied factory function.
	 * In the latter case, the value is cached after obtaining it.
	 *
	 * @phpstan-param callable():CachedType $createItem
	 * @phpstan-return CachedType
	 */
	protected function cachedGet(string $userId, ?string $key, callable $createItem) {
		return $this->localCache[$userId][$key] ?? $this->localCache[$userId][$key] = $createItem();
	}

	protected function invalidateCache(string $userId) : void {
		unset($this->localCache[$userId]);
	}

}