<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2021 - 2024
 */

namespace OCA\Music\Command;

use OCA\Music\BusinessLayer\PodcastChannelBusinessLayer;
use OCA\Music\BusinessLayer\PodcastEpisodeBusinessLayer;
use OCA\Music\Utility\HttpUtil;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PodcastAdd extends BaseCommand {

	private PodcastChannelBusinessLayer $channelBusinessLayer;
	private PodcastEpisodeBusinessLayer $episodeBusinessLayer;

	public function __construct(
			\OCP\IUserManager $userManager,
			\OCP\IGroupManager $groupManager,
			PodcastChannelBusinessLayer $channelBusinessLayer,
			PodcastEpisodeBusinessLayer $episodeBusinessLayer) {
		$this->channelBusinessLayer = $channelBusinessLayer;
		$this->episodeBusinessLayer = $episodeBusinessLayer;
		parent::__construct($userManager, $groupManager);
	}

	protected function doConfigure() : void {
		$this
			->setName('music:podcast-add')
			->setDescription('add a podcast channel from an RSS feed')
			->addOption(
					'rss',
					null,
					InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
					'URL to an RSS feed'
			)
		;
	}

	protected function doExecute(InputInterface $input, OutputInterface $output, array $users) : void {
		$rssUrls = $input->getOption('rss');

		if (!$rssUrls) {
			throw new \InvalidArgumentException("The named argument <error>rss</error> must be given!");
		}
		\assert(\is_array($rssUrls));

		foreach ($rssUrls as $rss) {
			$this->addPodcast($rss, $input, $output, $users);
		}
	}

	private function addPodcast(string $rss, InputInterface $input, OutputInterface $output, array $users) : void {
		$rssData = HttpUtil::loadFromUrl($rss);
		$content = $rssData['content'];
		if ($content === false) {
			throw new \InvalidArgumentException("Invalid URL <error>$rss</error>! {$rssData['status_code']} {$rssData['message']}");
		}

		$xmlTree = \simplexml_load_string($content, \SimpleXMLElement::class, LIBXML_NOCDATA);
		if ($xmlTree === false || !$xmlTree->channel) {
			throw new \InvalidArgumentException("The document at URL <error>$rss</error> is not a valid podcast RSS feed!");
		}

		if ($input->getOption('all')) {
			$this->userManager->callForAllUsers(function($user) use ($output, $rss, $content, $xmlTree) {
				$this->addPodcastForUser($user->getUID(), $rss, $content, $xmlTree->channel, $output);
			});
		} else {
			foreach ($users as $userId) {
				$this->addPodcastForUser($userId, $rss, $content, $xmlTree->channel, $output);
			}
		}
	}

	private function addPodcastForUser(string $userId, string $rss, string $content, \SimpleXMLElement $xmlNode, OutputInterface $output) : void {
		$output->writeln("Adding podcast channel <info>{$xmlNode->title}</info> for user <info>$userId</info>");
		try {
			$channel = $this->channelBusinessLayer->create($userId, $rss, $content, $xmlNode);

			// loop the episodes from XML in reverse order to get chronological order
			$items = $xmlNode->item;
			for ($count = \count($items), $i = $count-1; $i >= 0; --$i) {
				if ($items[$i] !== null) {
					$this->episodeBusinessLayer->addOrUpdate($userId, $channel->getId(), $items[$i]);
				}
			}
		} catch (\OCA\Music\AppFramework\Db\UniqueConstraintViolationException $ex) {
			$output->writeln('User already has this podcast channel, skipping');
		}
	}
}
