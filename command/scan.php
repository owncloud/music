<?php

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
 * @copyright Pauli Järvinen 2017, 2018
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

	protected function doConfigure() {
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
		;
	}

	protected function doExecute(InputInterface $input, OutputInterface $output, $users) {
		if (!$input->getOption('debug')) {
			$this->scanner->listen('\OCA\Music\Utility\Scanner', 'update', function($path) use ($output) {
				$output->writeln("Scanning <info>$path</info>");
			});
		}

		if ($input->getOption('all')) {
			$users = $this->userManager->search('');
			$users = array_map(function($u){return $u->getUID();}, $users);
		}

		foreach ($users as $user) {
			$this->scanUser(
					$user,
					$output,
					$input->getOption('clean-obsolete'),
					$input->getOption('debug'));
		}

		$output->writeln("Searching cover images for albums with no cover art set...");
		if ($this->scanner->findCovers()) {
			$output->writeln("Some cover image(s) were found and added");
		}
	}

	protected function scanUser($user, OutputInterface $output, $cleanObsolete, $debug) {
		\OC_Util::tearDownFS();
		\OC_Util::setupFS($user);
		$userHome = $this->scanner->resolveUserFolder($user);

		if ($cleanObsolete) {
			$output->writeln("Checking availability of previously scanned files of <info>$user</info>...");
			$removedCount = $this->scanner->removeUnavailableFiles($user, $userHome);
			if ($removedCount > 0) {
				$output->writeln("Removed $removedCount tracks which are no longer accessible by $user");
			}
		}

		$output->writeln("Start scan for <info>$user</info>");
		$unscanned = $this->scanner->getUnscannedMusicFileIds($user, $userHome);
		$output->writeln('Found ' . count($unscanned) . ' new music files');

		if (count($unscanned)) {
			$processedCount = $this->scanner->scanFiles(
					$user, $userHome, $unscanned,
					$debug ? $output : null);
			$output->writeln("Added $processedCount files to database of <info>$user</info>");
		}
	}
}
