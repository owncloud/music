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
	/**
	 * @var \OCP\IGroupManager $groupManager
	 */
	protected $groupManager;

	public function __construct(\OCP\IUserManager $userManager,
			\OCP\IGroupManager $groupManager) {
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
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
			->addOption(
				'group',
				null,
				InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
				'specify a targeted group to include all users of that group'
			)
		;
		$this->doConfigure();
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$users = array();
		if (!$input->getOption('all')) {
			if (count($input->getArgument('user_id'))===0
					&& count($input->getOption('group'))===0) {
				$output->writeln("Specify either the target user(s), --group or --all");
				return;
			}
			else {
				$users = $input->getArgument('user_id');
				if (!$this->validateUsers($output, $users))
					return;
				if ($input->hasOption('group')) {
					if (!$this->validateGroups($output, $input->getOption('group'), $groups_users))
						return;
					$users = array_merge($users, $groups_users);
				}
				$users = array_unique($users);
				if (count($users) === 0) {
					$output->writeln("No users in selected groups");
					return;
				}
			}
		}
		$this->doExecute($input, $output, $users);
	}

	private function validateUsers($output, $users) {
		foreach ($users as $user) {
			if (!$this->userManager->userExists($user)) {
				$output->writeln("User <error>$user</error> does not exist!");
				return false;
			}
		}
		return true;
	}

	private function validateGroups($output, $groups, &$users) {
		$users = array();
		foreach (array_unique($groups) as $group) {
			if (!$this->groupManager->groupExists($group)) {
				$output->writeln("Group <error>$group</error> does not exist!");
				return false;
			}
			else {
				foreach ($this->groupManager->get($group)->getUsers() as $user) {
					array_push($users, $user->getUID());
				}
			}
		}
		return true;
	}

	abstract protected function doConfigure();
	abstract protected function doExecute(InputInterface $input, OutputInterface $output, $users);
}
