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
	const None = 0;
	const Name = 1;
	const Parent = 2;
	const Newest = 3;
	const PlayCount = 4;
	const LastPlayed = 5;
	const Rating = 6;
}
