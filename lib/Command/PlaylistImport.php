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
use OCA\Music\BusinessLayer\PlaylistBusinessLayer;
use OCA\Music\Db\Playlist;
use OCA\Music\Service\PlaylistFileService;

use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PlaylistImport extends BaseCommand {

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
			->setName('music:playlist-import')
			->setDescription('import user playlist(s) from file(s)')
			->addOption(
				'file',
				null,
				InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
				'path of the playlist file, relative to the user home folder; * and ? are treated as wildcards within the file name but not on the directory name'
			)
			->addOption(
				'overwrite',
				null,
				InputOption::VALUE_NONE,
				'overwrite the target playlist if it already exists'
			)
			->addOption(
				'append',
				null,
				InputOption::VALUE_NONE,
				'append imported tracks to an existing playlist if found'
			)
		;
	}

	protected function doExecute(InputInterface $input, OutputInterface $output, array $users) : void {
		$files = $input->getOption('file');
		$overwrite = (bool)$input->getOption('overwrite');
		$append = (bool)$input->getOption('append');

		if (empty($files)) {
			throw new \InvalidArgumentException('At least one <error>file</error> argument must be given');
		}

		if ($overwrite && $append) {
			throw new \InvalidArgumentException('The options <error>overwrite</error> and <error>append</error> are mutually exclusive');
		}

		if ($input->getOption('all')) {
			$this->userManager->callForAllUsers(function($user) use ($output, $files, $overwrite, $append) {
				$this->executeForUser($user->getUID(), $files, $overwrite, $append, $output);
			});
		} else {
			foreach ($users as $userId) {
				$this->executeForUser($userId, $files, $overwrite, $append, $output);
			}
		}
	}

	private function executeForUser(string $userId, array $files, bool $overwrite, bool $append, OutputInterface $output) : void {
		$output->writeln("Importing playlist(s) for <info>$userId</info>...");

		$userFolder = $this->rootFolder->getUserFolder($userId);
		$files = self::resolveWildcards($files, $userFolder, $output);

		foreach ($files as $filePath) {
			$name = \pathinfo($filePath, PATHINFO_FILENAME);
			assert(\is_string($name));
			$existingLists = $this->businessLayer->findAllByName($name, $userId);
			if (\count($existingLists) === 0) {
				$playlist = $this->businessLayer->create($name, $userId);
			} elseif (!$overwrite && !$append) {
				$output->writeln("  The playlist <error>$name</error> already exists, give argument <info>overwrite</info> or <info>append</info>");
				$playlist = null;
			} else {
				$playlist = $existingLists[0];
			}

			if ($playlist !== null) {
				$this->importPlaylist($filePath, $playlist, $userId, $userFolder, $overwrite, $output);
			}
		}
	}

	private function importPlaylist(string $filePath, Playlist $playlist, string $userId, Folder $userFolder, bool $overwrite, OutputInterface $output) : void {
		try {
			$id = $playlist->getId();
			$result = $this->playlistFileService->importFromFile($playlist->getId(), $userId, $userFolder, $filePath, $overwrite ? 'overwrite' : 'append');
			$output->writeln("  <info>{$result['imported_count']}</info> tracks were imported to playlist <info>$id</info> from <info>$filePath</info>");
		} catch (BusinessLayerException $ex) {
			$output->writeln("  User <info>$userId</info> has no playlist with id <error>$id</error>");
		} catch (\OCP\Files\NotFoundException $ex) {
			$output->writeln("  Invalid file path <error>$filePath</error>");
		} catch (\UnexpectedValueException $ex) {
			$output->writeln("  The file <error>$filePath</error> is not a supported playlist file");
		}
	}

	/**
	 * @param string[] $paths
	 * @return string[]
	 */
	private static function resolveWildcards(array $paths, Folder $userFolder, OutputInterface $output) : array {
		$result = [];

		foreach ($paths as $path) {
			list('basename' => $basename, 'dirname' => $dirname) = \pathinfo($path);
			if (\strpos($basename, '?') === false && \strpos($basename, '*') === false) {
				// no wildcards, take the path as such
				$result[] = $path;
			} else {
				try {
					$dir = $userFolder->get($dirname);
				} catch (NotFoundException $e) {
					$dir = null;
				}
				if (!($dir instanceof Folder)) {
					$output->writeln("  Invalid directory path <error>$dirname</error>");
				} else {
					$matches = [];
					foreach ($dir->getDirectoryListing() as $node) {
						if ($node instanceof File && self::fileMatchesPattern($node, $basename)) {
							$matches[] = $userFolder->getRelativePath($node->getPath());
						}
					}
					if (\count($matches) == 0) {
						$output->writeln("  The path pattern <error>$path</error> matched no files");
					} else {
						$result = \array_merge($result, $matches);
					}
				}
			}
		}

		return $result;
	}

	private static function fileMatchesPattern(File $file, string $pattern) : bool {
		// convert the pattern to regex
		$pattern = \preg_quote($pattern);				// escape regex meta characters
		$pattern = \str_replace('\*', '.*', $pattern);	// convert * to its regex equivalent
		$pattern = \str_replace('\?', '.', $pattern);	// convert ? to its regex equivalent
		$pattern = "/^$pattern$/";						// the pattern should match the name from begin to end

		return (\preg_match($pattern, $file->getName()) === 1);
	}
}
