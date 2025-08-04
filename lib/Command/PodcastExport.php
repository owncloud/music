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

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PodcastExport extends BaseCommand {

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
			->setName('music:podcast-export')
			->setDescription('export user podcast channels to an OPML file')
			->addOption(
				'file',
				null,
				InputOption::VALUE_REQUIRED,
				'target file path, relative to the user home folder',
				'Podcasts.opml'
			)
			->addOption(
				'overwrite',
				null,
				InputOption::VALUE_NONE,
				'overwrite the target file if it already exists'
			)
		;
	}

	protected function doExecute(InputInterface $input, OutputInterface $output, array $users) : void {
		$path = $input->getOption('file');

		list('basename' => $file, 'dirname' => $dir) = \pathinfo($path);

		$overwrite = (bool)$input->getOption('overwrite');

		if (empty($file)) {
			throw new \InvalidArgumentException('Invalid argument: The file name must not be empty');
		}

		if ($input->getOption('all')) {
			$this->userManager->callForAllUsers(function($user) use ($output, $dir, $file, $overwrite) {
				$this->executeForUser($user->getUID(), $dir, $file, $overwrite, $output);
			});
		} else {
			foreach ($users as $userId) {
				$this->executeForUser($userId, $dir, $file, $overwrite, $output);
			}
		}
	}

	private function executeForUser(string $userId, string $dir, string $fileName, bool $overwrite, OutputInterface $output) : void {
		$output->writeln("Exporting podcast channels of <info>$userId</info>...");

		$userFolder = $this->rootFolder->getUserFolder($userId);

		try {
			$filePath = $this->podcastService->exportToFile($userId, $userFolder, $dir, $fileName, $overwrite ? 'overwrite' : 'abort');
			$output->writeln("  Exported to <info>$filePath</info>");
		} catch (\OCP\Files\NotFoundException $ex) {
			$output->writeln("  Invalid folder path <error>$dir</error>");
		} catch (\RuntimeException $ex) {
			$output->writeln("  File in the path <error>$dir/$fileName</error> already exists, pass the argument <info>--overwrite</info> to overwrite it or specify different path with the argument <info>--file</info>");
		}
	}

}
