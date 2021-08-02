<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2021
 */

namespace OCA\Music\Command;

use OCA\Music\BusinessLayer\PodcastChannelBusinessLayer;
use OCA\Music\BusinessLayer\PodcastEpisodeBusinessLayer;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\Instantiator\Exception\InvalidArgumentException;

class PodcastAdd extends BaseCommand {

	/** @var PodcastChannelBusinessLayer */
	private $channelBusinessLayer;
	/** @var PodcastEpisodeBusinessLayer */
	private $episodeBusinessLayer;

	public function __construct(
			\OCP\IUserManager $userManager,
			\OCP\IGroupManager $groupManager,
			PodcastChannelBusinessLayer $channelBusinessLayer,
			PodcastEpisodeBusinessLayer $episodeBusinessLayer) {
		$this->channelBusinessLayer = $channelBusinessLayer;
		$this->episodeBusinessLayer = $episodeBusinessLayer;
		parent::__construct($userManager, $groupManager);
	}

	protected function doConfigure() {
		$this
			->setName('music:podcast-add')
			->setDescription('add a podcast channel from an RSS feed')
			->addOption(
					'rss',
					null,
					InputOption::VALUE_REQUIRED,
					'URL to an RSS feed'
			)
		;
	}

	protected function doExecute(InputInterface $input, OutputInterface $output, $users) {
		$rss = $input->getOption('rss');

		if (!$rss) {
			throw new \InvalidArgumentException("The named argument <error>rss</error> must be given!");
		}

		if ($input->getOption('all')) {
			$users = $this->userManager->callForAllUsers(function($user) use ($output, $rss) {
				$this->addPodcast($user->getUID(), $rss, $output);
			});
		} else {
			foreach ($users as $userId) {
				$this->addPodcast($userId, $rss, $output);
			}
		}
	}

	private function addPodcast(string $userId, string $rss, OutputInterface $output) {
		$content = \file_get_contents($rss);
		if ($content === false) {
			throw new \InvalidArgumentException("Invalid URL <error>$rss</error>!");
		}

		$xmlTree = \simplexml_load_string($content, \SimpleXMLElement::class, LIBXML_NOCDATA);
		if ($xmlTree === false || !$xmlTree->channel) {
			throw new \InvalidArgumentException("The document at URL <error>$rss</error> is not a valid podcast RSS feed!");
		}

		$output->writeln("Adding podcast feed <info>$rss</info> for user <info>$userId</info>");
		try {
			$channel = $this->channelBusinessLayer->create($userId, $rss, $content, $xmlTree->channel);

			foreach ($xmlTree->channel->item as $episodeNode) {
				$this->episodeBusinessLayer->create($userId, $channel->getId(), $episodeNode);
			}
		} catch (\OCA\Music\AppFramework\Db\UniqueConstraintViolationException $ex) {
			$output->writeln('User already has this podcast channel, skipping');
		}
	}
}
