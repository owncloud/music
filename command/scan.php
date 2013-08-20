<?php
/**
 * Copyright (c) 2013 Thomas Müller <thomas.mueller@tmit.eu>
 * Copyright (c) 2013 Bart Visscher <bartv@thisnet.nl>
 * Copyright (c) 2014 Leizh <leizh@free.fr>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OCA\Music\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use \OCA\Music\DependencyInjection\DIContainer;

class Scan extends Command {
	/**
	 * @var \OC\User\Manager $userManager
	 */
	private $userManager;
	private $container;

	public function __construct(\OC\User\Manager $userManager) {
		$this->userManager = $userManager;
		$this->container = new DIContainer();
		parent::__construct();
	}

	protected function configure() {
		$this
			->setName('music:scan')
			->setDescription('rescan music')
			->addArgument(
					'user_id',
					InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
					'will rescan all music files of the given user(s)'
			)
			->addOption(
					'all',
					null,
					InputOption::VALUE_NONE,
					'will rescan all music files of all known users'
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$scanner = $this->container['Scanner'];

		$scanner->listen('\OCA\Music\Utility\Scanner', 'update', function($path) use ($output) {
			$output->writeln("Scanning <info>$path</info>");
		});

		if ($input->getOption('all')) {
			$users = $this->userManager->search('');
		} else {
			$users = $input->getArgument('user_id');
		}

		foreach ($users as $user) {
			if (is_object($user)) {
				$user = $user->getUID();
			}
			\OC_Util::tearDownFS();
			\OC_Util::setupFS($user);
			$output->writeln("Start scan for <info>$user</info>");
			$scanner->rescan($user, true);
		}
	}
}
