<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2013, 2014
 * @copyright Pauli Järvinen 2017 - 2021
 */

namespace OCA\Music\Backgroundjob;

use OCA\Music\App\Music;

class Cleanup {

	/**
	 * Run background cleanup task
	 */
	public static function run() {
		$app = \OC::$server->query(Music::class);

		$container = $app->getContainer();

		// remove orphaned entities
		$container->query('Maintenance')->cleanUp();

		// remove expired sessions
		$container->query('AmpacheSessionMapper')->cleanUp();

		// find covers - TODO performance stuff - maybe just call this once in an hour
		$container->query('Scanner')->findAlbumCovers();
	}
}
