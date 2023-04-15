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
 * @copyright Pauli Järvinen 2017 - 2023
 */

namespace OCA\Music\BackgroundJob;

use OCA\Music\App\Music;

use OC\BackgroundJob\TimedJob;
// NC15+ would have TimedJob also as a public class and has deprecated the private class used above.
// However, we can't use this new alternative as it's not available on ownCloud.
// use OCP\BackgroundJob\TimedJob;

class Cleanup extends TimedJob {

	/**
	 * Run background cleanup task
	 */
	public function run($arguments) {
		$app = \OC::$server->query(Music::class);

		$container = $app->getContainer();

		$logger = $container->query('Logger');
		$logger->log('Run ' . \get_class(), 'debug');

		// remove orphaned entities
		$container->query('Maintenance')->cleanUp();

		// remove expired sessions
		$container->query('AmpacheSessionMapper')->cleanUp();

		// find covers - TODO performance stuff - maybe just call this once in an hour
		$container->query('Scanner')->findAlbumCovers();
	}
}
