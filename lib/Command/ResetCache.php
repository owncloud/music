<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2017 - 2024
 */

namespace OCA\Music\Command;

use OCA\Music\Db\Cache;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ResetCache extends BaseCommand {

	private Cache $cache;

	public function __construct(\OCP\IUserManager $userManager,
			\OCP\IGroupManager $groupManager, Cache $cache) {
		$this->cache = $cache;
		parent::__construct($userManager, $groupManager);
	}

	protected function doConfigure() : void {
		$this
			->setName('music:reset-cache')
			->setDescription('drop data cached by the music app for performance reasons');
	}

	protected function doExecute(InputInterface $input, OutputInterface $output, array $users) : void {
		if ($input->getOption('all')) {
			$output->writeln("Drop cache for <info>all users</info>");
			$this->cache->remove();
		} else {
			foreach ($users as $user) {
				$output->writeln("Drop cache for <info>$user</info>");
				$this->cache->remove($user);
			}
		}
	}
}
