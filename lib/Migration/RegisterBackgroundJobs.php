<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2023
 */

namespace OCA\Music\Migration;

use OCA\Music\BackgroundJob\Cleanup;
use OCA\Music\BackgroundJob\PodcastUpdateCheck;

use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class RegisterBackgroundJobs implements IRepairStep {

	public function getName() {
		return 'Register Music background jobs and remove legacy registrations';
	}

	/**
	 * @inheritdoc
	 */
	public function run(IOutput $output) {
		$jobList = \OC::$server->getJobList();

		$jobList->add(Cleanup::class);
		$jobList->add(PodcastUpdateCheck::class);

		// remove legacy job registrations possibly made by older versions of the Music app
		$jobList->remove('OC\BackgroundJob\Legacy\RegularJob', ['OCA\Music\Backgroundjob\Cleanup', 'run']);
		$jobList->remove('OC\BackgroundJob\Legacy\RegularJob', ['OCA\Music\Backgroundjob\CleanUp', 'run']);
		$jobList->remove('OC\BackgroundJob\Legacy\RegularJob', ['OCA\Music\Backgroundjob\PodcastUpdateCheck', 'run']);
	}

}
