<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2021 - 2025
 */

namespace OCA\Music\Command;

use OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use OCA\Music\AppFramework\Utility\FileExistsException;
use OCA\Music\BusinessLayer\PlaylistBusinessLayer;
use OCA\Music\Db\Playlist;
use OCA\Music\Service\PlaylistFileService;

use OCP\Files\Folder;
use OCP\Files\IRootFolder;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PlaylistExport extends BaseCommand {

	private IRootFolder $rootFolder;
	private PlaylistBusinessLayer $businessLayer;
	private PlaylistFileService $playlistFileService;

	public function __construct(
			\OCP\IUserManager $userManager,
			\OCP\IGroupManager $groupManager,
			IRootFolder $rootFolder,
			PlaylistBusinessLayer $playlistBusinessLayer,
			PlaylistFileService $playlistFileService) {
		$this->rootFolder = $rootFolder;
		$this->businessLayer = $playlistBusinessLayer;
		$this->playlistFileService = $playlistFileService;
		parent::__construct($userManager, $groupManager);
	}

	protected function doConfigure() : void {
		$this
			->setName('music:playlist-export')
			->setDescription('export user playlist(s) to file(s)')
			->addOption(
				'list-id',
				null,
				InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
				'ID of the playlist to export'
			)
			->addOption(
				'list-name',
				null,
				InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
				'name of the playlist to export'
			)
			->addOption(
				'all-lists',
				null,
				InputOption::VALUE_NONE,
				'export all playlists of the user'
			)
			->addOption(
				'dir',
				null,
				InputOption::VALUE_REQUIRED,
				'target directory, relative to the user home folder (the dir must exist)',
				''
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
		$ids = $input->getOption('list-id');
		$names = $input->getOption('list-name');
		$allLists = (bool)$input->getOption('all-lists');
		$dir = $input->getOption('dir');
		$overwrite = (bool)$input->getOption('overwrite');

		if (empty($ids) && empty($names) && !$allLists) {
			throw new \InvalidArgumentException('At least one of the arguments <error>list-id</error>, ' .
												'<error>list-name</error>, <error>all-lists</error> must be given');
		}
		elseif ($allLists && (!empty($ids) || !empty($names))) {
			throw new \InvalidArgumentException('Argument <error>all-lists</error> should not be used together with ' .
												'<error>list-id</error> nor <error>list-name</error>');
		}

		if ($input->getOption('all')) {
			$this->userManager->callForAllUsers(function($user) use ($output, $ids, $names, $allLists, $dir, $overwrite) {
				$this->executeForUser($user->getUID(), $ids, $names, $allLists, $dir, $overwrite, $output);
			});
		} else {
			foreach ($users as $userId) {
				$this->executeForUser($userId, $ids, $names, $allLists, $dir, $overwrite, $output);
			}
		}
	}

	private function executeForUser(string $userId, array $ids, array $names, bool $allLists,
									string $dir, bool $overwrite, OutputInterface $output) : void {
		$output->writeln("Exporting playlist(s) of <info>$userId</info>...");

		if ($allLists) {
			$lists = $this->businessLayer->findAll($userId);
		}
		else {
			$lists = $this->businessLayer->findById($ids, $userId);

			foreach ($names as $name) {
				$listsOfName = $this->businessLayer->findAllByName($name, $userId);
				if (\count($listsOfName) === 0) {
					$output->writeln("  User <info>$userId</info> has no playlist with name <error>$name</error>");
				} else {
					$lists = \array_merge($lists, $listsOfName);
				}
			}
		}

		$userFolder = $this->rootFolder->getUserFolder($userId);
		foreach ($lists as $playlist) {
			$this->exportPlaylist($playlist, $userId, $userFolder, $dir, $overwrite, $output);
		}
	}

	private function exportPlaylist(Playlist $playlist, string $userId, Folder $userFolder, string $dir, bool $overwrite, OutputInterface $output) : void {
		try {
			$id = $playlist->getId();
			$filePath = $this->playlistFileService->exportToFile($id, $userId, $userFolder, $dir, null, $overwrite ? 'overwrite' : 'abort');
			$output->writeln("  The playlist <info>$id</info> was exported to <info>$filePath</info>");
		} catch (BusinessLayerException $ex) {
			$output->writeln("  User <info>$userId</info> has no playlist with id <error>$id</error>");
		} catch (\OCP\Files\NotFoundException $ex) {
			$output->writeln("  Invalid folder path <error>$dir</error>");
		} catch (FileExistsException $ex) {
			$output->writeln("  Playlist file with the name <error>{$playlist->getName()}</error> already exists, pass the argument <info>--overwrite</info> to overwrite it");
		}
	}
}
