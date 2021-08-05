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

use OCA\Music\BusinessLayer\PodcastChannelBusinessLayer;
use OCA\Music\BusinessLayer\PodcastEpisodeBusinessLayer;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PodcastReset extends BaseCommand {

	/** @var PodcastChannelBusinessLayer */
	private $channelBusinessLayer;
	/** @var PodcastEpisodeBusinessLayer */
	private $episodeBusinessLayer;

	public function __construct(
			\OCP\IUserManager $userManager,
			\OCP\IGroupManager $groupManager,
			PodcastChannelBusinessLayer $channelBusinessLayer,
			PodcastEpisodeBusinessLayer $episodeBusinessLayer) {
		$this->channelBusinessLayer = $channelBusinessLayer;
		$this->episodeBusinessLayer = $episodeBusinessLayer;
		parent::__construct($userManager, $groupManager);
	}

	protected function doConfigure() {
		$this
			->setName('music:podcast-reset')
			->setDescription('remove all podcast channels of one or more users')
		;
	}

	protected function doExecute(InputInterface $input, OutputInterface $output, $users) {
		if ($input->getOption('all')) {
			$this->userManager->callForAllUsers(function($user) use ($output) {
				$this->resetPodcasts($user->getUID(), $output);
			});
		} else {
			foreach ($users as $userId) {
				$this->resetPodcasts($userId, $output);
			}
		}
	}

	private function resetPodcasts(string $userId, OutputInterface $output) {
		$output->writeln("Reset all podcasts of the user <info>$userId</info>");

		$this->episodeBusinessLayer->deleteAll($userId);
		$this->channelBusinessLayer->deleteAll($userId);
	}
}
