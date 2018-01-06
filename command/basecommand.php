<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2018
 */

namespace OCA\Music\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


abstract class BaseCommand extends Command {
	/**
	 * @var \OCP\IUserManager $userManager
	 */
	protected $userManager;

	public function __construct(\OCP\IUserManager $userManager) {
		$this->userManager = $userManager;
		parent::__construct();
	}

	protected function configure() {
		$this
			->addArgument(
				'user_id',
				InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
				'specify one or more targeted users'
			)
			->addOption(
				'all',
				null,
				InputOption::VALUE_NONE,
				'target all known users'
			)
		;
		$this->doConfigure();
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$argsValid = true;
		if (!$input->getOption('all')) {
			$users = $input->getArgument('user_id');

			if (count($users) === 0) {
				$output->writeln("Specify either the target user(s) or --all");
				$argsValid = false;
			}
			else {
				foreach ($users as $user) {
					if (!$this->userManager->userExists($user)) {
						$output->writeln("User <error>$user</error> does not exist!");
						$argsValid = false;
					}
				}
			}
		}

		if ($argsValid) {
			$this->doExecute($input, $output);
		}
	}

	abstract protected function doConfigure();
	abstract protected function doExecute(InputInterface $input, OutputInterface $output);
}
