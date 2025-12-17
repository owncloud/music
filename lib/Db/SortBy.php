<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2017 - 2023
 */

namespace OCA\Music\Db;

/**
 * Enum-like class to define sort order
 */
abstract class SortBy {
	public const None = 0;
	public const Name = 1;
	public const Parent = 2;
	public const Newest = 3;
	public const PlayCount = 4;
	public const LastPlayed = 5;
	public const Rating = 6;
}
