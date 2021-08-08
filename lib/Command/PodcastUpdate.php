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

namespace OCA\Music\Command;

use OCA\Music\Utility\PodcastService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PodcastUpdate extends BaseCommand {

	/** @var PodcastService */
	private $podcastService;

	public function __construct(
			\OCP\IUserManager $userManager,
			\OCP\IGroupManager $groupManager,
			PodcastService $podcastService) {
		$this->podcastService = $podcastService;
		parent::__construct($userManager, $groupManager);
	}

	protected function doConfigure() {
		$this
			->setName('music:podcast-update')
			->setDescription('update podcast channels of one or more users from their sources')
			->addOption(
				'older-than',
				null,
				InputOption::VALUE_REQUIRED,
				'check updates only for channels which have not been checked for this many hours (sub-hour resolution supported with decimals)'
			)
		;
	}

	protected function doExecute(InputInterface $input, OutputInterface $output, $users) {
		$olderThan = $input->getOption('older-than');
		if ($olderThan !== null) {
			$olderThan = (float)$olderThan;
		}

		if ($input->getOption('all')) {
			$this->userManager->callForAllUsers(function($user) use ($output, $olderThan) {
				$this->updateForUser($user->getUID(), $olderThan, $output);
			});
		} else {
			foreach ($users as $userId) {
				$this->updateForUser($userId, $olderThan, $output);
			}
		}
	}

	private function updateForUser(string $userId, ?float $olderThan, OutputInterface $output) : void {
		$output->writeln("Updating podcasts of <info>$userId</info>...");

		$result = $this->podcastService->updateAllChannels($userId, $olderThan, function (array $channelResult) use ($output) {
			if (isset($channelResult['channel'])) {
				$id = $channelResult['channel']->getId();
				$title = $channelResult['channel']->getTitle();
			} else {
				$id = -1;
				$title = '(unknown)';
			}

			if ($channelResult['updated']) {
				$output->writeln("  Channel $id <info>$title</info> was updated");
			} elseif ($channelResult['status'] === PodcastService::STATUS_OK) {
				$output->writeln("  Channel $id <info>$title</info> had no changes");
			} else {
				$output->writeln("  Channel $id <error>$title</error> update failed");
			}
		});

		if ($result['changed'] + $result['unchanged'] + $result['failed'] === 0) {
			$output->writeln("  (no channels to update)");
		}
	}
}
