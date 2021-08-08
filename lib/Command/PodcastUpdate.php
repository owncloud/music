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
		;
	}

	protected function doExecute(InputInterface $input, OutputInterface $output, $users) {
		if ($input->getOption('all')) {
			$this->userManager->callForAllUsers(function($user) use ($output) {
				$this->updateForUser($user->getUID(), $output);
			});
		} else {
			foreach ($users as $userId) {
				$this->updateForUser($userId, $output);
			}
		}
	}

	private function updateForUser(string $userId, OutputInterface $output) : void {
		$output->writeln("Updating podcasts of <info>$userId</info>...");
		$this->podcastService->updateAllChannels($userId, function ($channelResult) use ($output) {
			$channel = $channelResult['channel'] ?? null;
			$id = $channel->getId() ?? -1;
			$title = $channel->getTitle() ?? '(unknown)';

			if ($channelResult['updated']) {
				$output->writeln("  Channel $id <info>$title</info> was updated");
			} elseif ($channelResult['status'] === PodcastService::STATUS_OK) {
				$output->writeln("  Channel $id <info>$title</info> had no changes");
			} else {
				$output->writeln("  Channel $id <error>$title</error> update failed");
			}
		});
	}
}
