<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2017
 */

namespace OCA\Music\Db;


/**
 * Enum-like class to define sort order
 */
abstract class SortBy {
	const None = 0;
	const Name = 1;
}
