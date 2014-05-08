<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @copyright Morris Jobke 2013, 2014
 */

namespace OCA\Music\Backgroundjob;

use \OCA\Music\App\Music;

class CleanUp {

	/**
	 * Calls the cleanup method of the scanner
	 */
	public static function run() {
		$app = new Music();

		$container = $app->getContainer();

		// remove orphaned entities
		$container->query('Scanner')->cleanUp();
		// find covers - TODO performance stuff - maybe just call this once in an hour
		$container->query('AlbumBusinessLayer')->findCovers();

		// remove expired sessions
		$container->query('AmpacheSessionMapper')->cleanUp();
	}
}
