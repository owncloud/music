<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2021
 */

namespace OCA\Music\Db;

/**
 * Enum-like class to define matching mode for the search functions
 */
abstract class MatchMode {
	const Exact = 0;		// the whole pattern must be matched exactly, still ignoring case
	const Wildcards = 1;	// the pattern may contain wildcards '%' or '_'
	const Substring = 2;	// the pattern is matched as substring(s), supporting also wildcards;
							// quotation may be used to pass a single substring which may contain whitespace
}
