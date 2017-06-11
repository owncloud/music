<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @copyright Morris Jobke 2014
 */

namespace OCA\Music\Command;

use OCA\Music\Utility\Scanner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class ResetDatabase extends Command {

	/** @var Scanner */
	private $scanner;

	public function __construct($scanner) {
		$this->scanner = $scanner;
		parent::__construct();
	}

	protected function configure() {
		$this
			->setName('music:reset-database')
			->setDescription('will drop all metadata gathered by the music app (artists, albums, tracks, playlists)')
			->addArgument(
				'user_id',
				InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
				'specify the user'
			)
			->addOption(
				'all',
				null,
				InputOption::VALUE_NONE,
				'use all known users'
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		if ($input->getOption('all')) {
			$output->writeln("Drop tables for <info>all users</info>");
			$this->scanner->resetDb(null, true);
		} else {
			$users = $input->getArgument('user_id');
			foreach($users as $user) {
				$output->writeln("Drop tables for <info>$user</info>");
				$this->scanner->resetDb($user);
			}
		}
	}

}
