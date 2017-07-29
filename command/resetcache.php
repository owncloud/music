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

use OCA\Music\Db\Cache;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class ResetCache extends Command {

	/** @var Cache */
	private $cache;

	public function __construct($cache) {
		$this->cache = $cache;
		parent::__construct();
	}

	protected function configure() {
		$this
			->setName('music:reset-cache')
			->setDescription('will drop data cached by the music app for performance reasons')
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
			$output->writeln("Drop cache for <info>all users</info>");
			$this->cache->remove();
		} else {
			$users = $input->getArgument('user_id');
			foreach($users as $user) {
				$output->writeln("Drop cache for <info>$user</info>");
				$this->cache->remove($user);
			}
		}
	}

}
