<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2021 - 2025
 */

namespace OCA\Music\BusinessLayer;

use OCA\Music\AppFramework\BusinessLayer\BusinessLayer;
use OCA\Music\AppFramework\Core\Logger;

use OCA\Music\Db\BaseMapper;
use OCA\Music\Db\MatchMode;
use OCA\Music\Db\PodcastChannelMapper;
use OCA\Music\Db\PodcastChannel;
use OCA\Music\Db\SortBy;
use OCA\Music\Utility\StringUtil;


/**
 * Base class functions with the actually used inherited types to help IDE and Scrutinizer:
 * @method PodcastChannel find(int $channelId, string $userId)
 * @method PodcastChannel[] findAll(string $userId, int $sortBy=SortBy::Name, int $limit=null, int $offset=null, ?string $createdMin=null, ?string $createdMax=null, ?string $updatedMin=null, ?string $updatedMax=null)
 * @method PodcastChannel[] findAllByName(string $name, string $userId, int $matchMode=MatchMode::Exact, int $limit=null, int $offset=null, ?string $createdMin=null, ?string $createdMax=null, ?string $updatedMin=null, ?string $updatedMax=null)
 * @property PodcastChannelMapper $mapper
 * @phpstan-extends BusinessLayer<PodcastChannel>
 */
class PodcastChannelBusinessLayer extends BusinessLayer {
	private Logger $logger;

	public function __construct(PodcastChannelMapper $mapper, Logger $logger) {
		parent::__construct($mapper);
		$this->logger = $logger;
	}

	/**
	 * @return int[]
	 */
	public function findAllIdsNotUpdatedForHours(string $userId, float $minAgeHours) : array {
		$minAgeSeconds = (int)($minAgeHours * 3600);
		$timeLimit = new \DateTime();
		$timeLimit->modify("-$minAgeSeconds second");

		return $this->mapper->findAllIdsWithNoUpdateSince($userId, $timeLimit);
	}

	public function create(string $userId, string $rssUrl, string $rssContent, \SimpleXMLElement $xmlNode) : PodcastChannel {
		$channel = new PodcastChannel();
		self::parseChannelDataFromXml($xmlNode, $channel);

		$channel->setUserId( $userId );
		$channel->setRssUrl( StringUtil::truncate($rssUrl, 2048) );
		$channel->setRssHash( \hash('md5', $rssUrl) );
		$channel->setContentHash( self::calculateContentHash($rssContent) );
		$channel->setUpdateChecked( \date(BaseMapper::SQL_DATE_FORMAT) );

		return $this->mapper->insert($channel);
	}

	/**
	 * @param PodcastChannel $channel Input/output parameter for the channel
	 * @param string $rssContent Raw content of the RSS feed
	 * @param \SimpleXMLElement $xmlNode <channel> node parsed from the RSS feed
	 * @param boolean $force Value true will cause the channel to be updated to the DB even
	 * 						if there appears to be no changes since the previous update
	 * @return boolean true if the new content differed from the previously cached content or update was forced
	 */
	public function updateChannel(PodcastChannel &$channel, string $rssContent, \SimpleXMLElement $xmlNode, bool $force = false) {
		$contentChanged = false;
		$contentHash = self::calculateContentHash($rssContent);

		if ($channel->getContentHash() !== $contentHash || $force) {
			$contentChanged = true;
			self::parseChannelDataFromXml($xmlNode, $channel);
			$channel->setContentHash($contentHash);
		}
		$channel->setUpdateChecked( \date(BaseMapper::SQL_DATE_FORMAT) );

		$this->update($channel);
		return $contentChanged;
	}

	/**
	 * Indicate that the channel has been checked for updates without updating any content.
	 * This may be used e.g. in case the channel RSS feed cannot be reached.
	 */
	public function markUpdateChecked(PodcastChannel &$channel) : void {
		$channel->setUpdateChecked( \date(BaseMapper::SQL_DATE_FORMAT) );
		$this->update($channel);
	}

	private static function calculateContentHash(string $rssContent) : string {
		// Exclude the tag <lastBuildDate> from the calculation. This is because many podcast feeds update that
		// very often, e.g. every 15 minutes, even when nothing else has changed. Including such a volatile field
		// on the hash would cause a lot of unnecessary updating of the database contents.
		$ctx = \hash_init('md5');

		$head = \strstr($rssContent, '<lastBuildDate>', true);
		$tail = ($head === false) ? false : \strstr($rssContent, '</lastBuildDate>', false);

		if ($tail === false) {
			// tag not found, just calculate the hash from the whole content
			\hash_update($ctx, $rssContent);
		} else {
			\hash_update($ctx, $head);
			\hash_update($ctx, $tail);
		}

		return \hash_final($ctx);
	}

	private static function parseChannelDataFromXml(\SimpleXMLElement $xmlNode, PodcastChannel &$channel) : void {
		$itunesNodes = $xmlNode->children('http://www.itunes.com/dtds/podcast-1.0.dtd');

		$channel->setPublished( self::parseDateTime($xmlNode->pubDate) );
		$channel->setLastBuildDate( self::parseDateTime($xmlNode->lastBuildDate) );
		$channel->setTitle( StringUtil::truncate((string)$xmlNode->title, 256) );
		$channel->setLinkUrl( StringUtil::truncate((string)$xmlNode->link, 2048) );
		$channel->setLanguage( StringUtil::truncate((string)$xmlNode->language, 32) );
		$channel->setCopyright( StringUtil::truncate((string)$xmlNode->copyright, 256) );
		$channel->setAuthor( StringUtil::truncate((string)($xmlNode->author ?: $itunesNodes->author), 256) );
		$channel->setDescription( (string)($xmlNode->description ?: $itunesNodes->summary) );
		$channel->setImageUrl( (string)$xmlNode->image->url );
		$channel->setCategory( \implode(', ', \array_map(
			fn($category) => $category->attributes()['text'],
			\iterator_to_array($itunesNodes->category, false)
		)) );
	}

	private static function parseDateTime(?\SimpleXMLElement $xmlNode) : ?string {
		return $xmlNode ? \date(BaseMapper::SQL_DATE_FORMAT, \strtotime((string)$xmlNode)) : null;
	}
}
