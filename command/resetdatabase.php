<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2014
 * @copyright Pauli Järvinen 2017
 */

namespace OCA\Music\Command;

use OCA\Music\Db\Maintenance;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class ResetDatabase extends Command {

	/** @var Maintenance */
	private $maintenance;

	public function __construct($maintenance) {
		$this->maintenance = $maintenance;
		parent::__construct();
	}

	protected function configure() {
		$this
			->setName('music:reset-database')
			->setDescription('drop metadata indexed by the music app (artists, albums, tracks, playlists)')
			->addArgument(
				'user_id',
				InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
				'specify the targeted user(s)'
			)
			->addOption(
				'all',
				null,
				InputOption::VALUE_NONE,
				'target all known users'
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		if ($input->getOption('all')) {
			$output->writeln("Drop tables for <info>all users</info>");
			$this->maintenance->resetDb(null, true);
		} else {
			$users = $input->getArgument('user_id');
			if (count($users) === 0) {
				$output->writeln("Specify either the target user(s) or --all");
			}
			foreach($users as $user) {
				$output->writeln("Drop tables for <info>$user</info>");
				$this->maintenance->resetDb($user);
			}
		}
	}

}
