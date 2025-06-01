<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2025
 */

namespace OCA\Music\Command;

use OCA\Music\Service\PodcastService;

use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PodcastImport extends BaseCommand {

	private IRootFolder $rootFolder;
	private PodcastService $podcastService;

	public function __construct(
			\OCP\IUserManager $userManager,
			\OCP\IGroupManager $groupManager,
			IRootFolder $rootFolder,
			PodcastService $podcastService) {
		$this->rootFolder = $rootFolder;
		$this->podcastService = $podcastService;
		parent::__construct($userManager, $groupManager);
	}

	protected function doConfigure() : void {
		$this
			->setName('music:podcast-import')
			->setDescription('import user podcast channels from an OPML file')
			->addOption(
				'file',
				null,
				InputOption::VALUE_REQUIRED,
				'path of the OPML file, relative to the user home folder'
			)
		;
	}

	protected function doExecute(InputInterface $input, OutputInterface $output, array $users) : void {
		$file = $input->getOption('file');

		if (empty($file)) {
			throw new \InvalidArgumentException('The OPML file path cannot be empty');
		}

		if ($input->getOption('all')) {
			$this->userManager->callForAllUsers(function($user) use ($output, $file) {
				$this->executeForUser($user->getUID(), $file, $output);
			});
		} else {
			foreach ($users as $userId) {
				$this->executeForUser($userId, $file, $output);
			}
		}
	}

	private function executeForUser(string $userId, string $filePath, OutputInterface $output) : void {
		$output->writeln("Importing podcast channels for <info>$userId</info>...");

		$userFolder = $this->rootFolder->getUserFolder($userId);

		try {
			$this->podcastService->importFromFile($userId, $userFolder, $filePath, function (array $channelResult) use ($output) {
				if (!empty($channelResult['channel'])) {
					$id = $channelResult['channel']->getId();
					$title = $channelResult['channel']->getTitle();
					$output->writeln("  Subscribed channel $id <info>$title</info>");
				} else if ($channelResult['status'] == PodcastService::STATUS_ALREADY_EXISTS) {
					$output->writeln("  Skipping already subscribed channel {$channelResult['rss']}");
				} else {
					$output->writeln("  Failed to subscribe <error>{$channelResult['rss']}</error>");
				}
			});
		} catch (NotFoundException $ex) {
			$output->writeln("  Invalid file path <error>$filePath</error>");
		} catch (\UnexpectedValueException $ex) {
			$output->writeln("  The file <error>$filePath</error> is not a supported OPML file");
		}
	}

}
