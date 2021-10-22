<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Leizh <leizh@free.fr>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Thomas Müller 2013
 * @copyright Bart Visscher 2013
 * @copyright Leizh 2014
 * @copyright Pauli Järvinen 2017 - 2021
 */

namespace OCA\Music\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use OCA\Music\Utility\Scanner;

class Scan extends BaseCommand {
	/**
	 * @var  Scanner
	 */
	private $scanner;

	public function __construct(\OCP\IUserManager $userManager,
			\OCP\IGroupManager $groupManager, $scanner) {
		$this->scanner = $scanner;
		parent::__construct($userManager, $groupManager);
	}

	protected function doConfigure() : void {
		$this
			->setName('music:scan')
			->setDescription('scan and index any unindexed audio files')
			->addOption(
					'debug',
					null,
					InputOption::VALUE_NONE,
					'will run the scan in debug mode (memory usage)'
			)
			->addOption(
					'clean-obsolete',
					null,
					InputOption::VALUE_NONE,
					'also check availability of any previously scanned tracks, removing obsolete entries'
			)
			->addOption(
					'rescan',
					null,
					InputOption::VALUE_NONE,
					'rescan also any previously scanned tracks'
			)
			->addOption(
					'folder',
					null,
					InputOption::VALUE_OPTIONAL,
					'scan only files within this folder (path is relative to the user home folder)'
			)
		;
	}

	protected function doExecute(InputInterface $input, OutputInterface $output, array $users) : void {
		if (!$input->getOption('debug')) {
			$this->scanner->listen('\OCA\Music\Utility\Scanner', 'update', function ($path) use ($output) {
				$output->writeln("Scanning <info>$path</info>");
			});
		}

		if ($input->getOption('all')) {
			$users = $this->userManager->search('');
			$users = \array_map(function ($u) {
				return $u->getUID();
			}, $users);
		}

		foreach ($users as $user) {
			$this->scanUser(
					$user,
					$output,
					$input->getOption('rescan'),
					$input->getOption('clean-obsolete'),
					$input->getOption('folder'),
					$input->getOption('debug'));
		}
	}

	protected function scanUser(string $user, OutputInterface $output, bool $rescan, bool $cleanObsolete, ?string $folder, bool $debug) : void {
		$userHome = $this->scanner->resolveUserFolder($user);

		if ($cleanObsolete) {
			$output->writeln("Checking availability of previously scanned files of <info>$user</info>...");
			$removedCount = $this->scanner->removeUnavailableFiles($user);
			if ($removedCount > 0) {
				$output->writeln("Removed $removedCount tracks which are no longer within the library of <info>$user</info>");
			}
		}

		$output->writeln("Start scan for <info>$user</info>");
		if ($rescan) {
			$filesToScan = $this->scanner->getAllMusicFileIds($user, $folder);
		} else {
			$filesToScan = $this->scanner->getUnscannedMusicFileIds($user, $folder);
		}
		$output->writeln('Found ' . \count($filesToScan) . ' music files to scan' . ($folder ? " in '$folder'" : ''));

		if (\count($filesToScan)) {
			$processedCount = $this->scanner->scanFiles(
					$user, $userHome, $filesToScan,
					$debug ? $output : null);
			$output->writeln("Added $processedCount files to database of <info>$user</info>");
		}

		$output->writeln("Searching cover images for albums with no cover art set...");
		if ($this->scanner->findAlbumCovers($user)) {
			$output->writeln("Some album cover image(s) were found and added");
		}

		$output->writeln("Searching cover images for artists with no cover art set...");
		if ($this->scanner->findArtistCovers($user)) {
			$output->writeln("Some artist cover image(s) were found and added");
		}
	}
}
