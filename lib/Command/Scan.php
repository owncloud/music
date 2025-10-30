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
 * @copyright Pauli Järvinen 2017 - 2025
 */

namespace OCA\Music\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use OCP\IGroupManager;
use OCP\IUserManager;

use OCA\Music\Service\Scanner;

class Scan extends BaseCommand {

	private Scanner $scanner;

	public function __construct(IUserManager $userManager, IGroupManager $groupManager, Scanner $scanner) {
		$this->scanner = $scanner;
		parent::__construct($userManager, $groupManager);
	}

	protected function doConfigure() : void {
		$this
			->setName('music:scan')
			->setDescription('scan and index any unindexed or dirty audio files')
			->addOption(
					'debug',
					null,
					InputOption::VALUE_NONE,
					'will run the scan in debug mode, showing memory and time consumption'
			)
			->addOption(
					'clean-obsolete',
					null,
					InputOption::VALUE_NONE,
					'also remove any obsolete file references from the library'
			)
			->addOption(
					'rescan',
					null,
					InputOption::VALUE_NONE,
					'rescan also any previously scanned tracks'
			)
			->addOption(
					'skip-dirty',
					null,
					InputOption::VALUE_NONE,
					'do not rescan the files marked "dirty" or having timestamp after the latest scan time'
			)
			->addOption(
					'skip-art',
					null,
					InputOption::VALUE_NONE,
					'do not search for album and artist cover art images'
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
			$this->scanner->listen(Scanner::class, 'update', fn($path) => $output->writeln("Scanning <info>$path</info>"));
			$this->scanner->listen(Scanner::class, 'exclude', fn($path) => $output->writeln("!! Removing <info>$path</info>"));
		}

		if ($input->getOption('rescan') && $input->getOption('skip-dirty')) {
			throw new \InvalidArgumentException('The options <error>rescan</error> and <error>skip-dirty</error> are mutually exclusive');
		}

		if ($input->getOption('all')) {
			$users = $this->userManager->search('');
			$users = \array_map(fn($u) => $u->getUID(), $users);
		}

		foreach ($users as $user) {
			$this->scanUser(
					$user,
					$output,
					$input->getOption('rescan'),
					$input->getOption('skip-dirty'),
					$input->getOption('skip-art'),
					$input->getOption('clean-obsolete'),
					$input->getOption('folder'),
					$input->getOption('debug')
			);
		}
	}

	protected function scanUser(
			string $user, OutputInterface $output, bool $rescan, bool $skipDirty, bool $skipArt,
			bool $cleanObsolete, ?string $folder, bool $debug) : void {

		$output->writeln("Check library scan status for <info>$user</info>"  . ($folder ? " in path '$folder'..." : '...'));
		$startTime = \hrtime(true);
		\extract($this->scanner->getStatusOfLibraryFiles($user, $folder)); // populate $unscannedFiles, $obsoleteFiles, $dirtyFiles, $scannedCount
		$statusTime = (int)((\hrtime(true) - $startTime) / 1000000);
		$unscannedCount = \count($unscannedFiles);
		$dirtyCount = \count($dirtyFiles);
		$obsoleteCount = \count($obsoleteFiles);

		$output->writeln("  Status got in $statusTime ms");
		$output->writeln("  Scanned files: $scannedCount");
		$output->writeln("  Unscanned files: $unscannedCount");
		$output->writeln("  Dirty files: $dirtyCount" . (($dirtyCount && $skipDirty) ? ' (skipped)' : ''));
		$output->writeln("  Obsolete files: $obsoleteCount" . (($obsoleteCount && !$cleanObsolete) ? ' (use --clean-obsolete to remove)' : ''));
		$output->writeln("");

		if ($cleanObsolete && !empty($obsoleteFiles)) {
			if ($this->scanner->deleteAudio($obsoleteFiles, [$user])) {
				$output->writeln("The obsolete files no longer available in the the library of <info>$user</info> were removed");
			} else {
				$output->writeln("<error>Failed</error> to remove any obsolete files of <info>$user</info>!");
			}
		}

		if ($rescan) {
			$filesToScan = $this->scanner->getAllMusicFileIds($user, $folder);
		} else {
			$filesToScan = $unscannedFiles;
			if (!$skipDirty) {
				$filesToScan = \array_merge($filesToScan, $dirtyFiles);
			}
		}
		$output->writeln('Total ' . \count($filesToScan) . ' files to scan' . ($folder ? " in '$folder'" : ''));

		if (\count($filesToScan)) {
			$stats = $this->scanner->scanFiles($user, $filesToScan, $debug ? $output : null);
			$output->writeln("Added or updated {$stats['count']} files in database of <info>$user</info>");
			$output->writeln('  Time consumed to analyze files: ' . ($stats['anlz_time'] / 1000) . ' s');
			$output->writeln('  Time consumed to update DB: ' . ($stats['db_time'] / 1000) . ' s');
		}

		if ($skipArt) {
			$output->writeln("Cover art search skipped");
		} else {
			$this->searchArt($user, $folder, $output);
		}
	}

	private function searchArt(string $user, ?string $folder, OutputInterface $output) : void {
		$output->writeln("");
		$output->writeln("Searching cover images for albums with no cover art set...");
		$startTime = \hrtime(true);
		if ($this->scanner->findAlbumCovers($user, $folder)) {
			$output->writeln("  Some album cover image(s) were found and added");
		}
		$albumCoverTime = (int)((\hrtime(true) - $startTime) / 1000000);
		$output->writeln("  Search took $albumCoverTime ms");

		$output->writeln("Searching cover images for artists with no cover art set...");
		$startTime = \hrtime(true);
		if ($this->scanner->findArtistCovers($user)) {
			$output->writeln("  Some artist cover image(s) were found and added");
		}
		$artistCoverTime = (int)((\hrtime(true) - $startTime) / 1000000);
		$output->writeln("  Search took $artistCoverTime ms");
	}
}
