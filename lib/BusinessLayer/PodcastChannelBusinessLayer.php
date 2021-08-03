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

namespace OCA\Music\BusinessLayer;

use \OCA\Music\AppFramework\BusinessLayer\BusinessLayer;
use \OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use \OCA\Music\AppFramework\Core\Logger;

use \OCA\Music\Db\BaseMapper;
use \OCA\Music\Db\PodcastChannelMapper;
use \OCA\Music\Db\PodcastChannel;

use \OCA\Music\Utility\Util;


/**
 * Base class functions with the actually used inherited types to help IDE and Scrutinizer:
 * @method PodcastChannel find(int $channelId, string $userId)
 * @method PodcastChannel[] findAll(
 *			string $userId, int $sortBy=SortBy::None, int $limit=null, int $offset=null,
 *			?string $createdMin=null, ?string $createdMax=null, ?string $updatedMin=null, ?string $updatedMax=null)
 * @method PodcastChannel[] findAllByName(
 *			string $name, string $userId, bool $fuzzy=false, int $limit=null, int $offset=null,
 *			?string $createdMin=null, ?string $createdMax=null, ?string $updatedMin=null, ?string $updatedMax=null)
 */
class PodcastChannelBusinessLayer extends BusinessLayer {
	protected $mapper; // eclipse the definition from the base class, to help IDE and Scrutinizer to know the actual type
	private $logger;

	public function __construct(PodcastChannelMapper $mapper, Logger $logger) {
		parent::__construct($mapper);
		$this->mapper = $mapper;
		$this->logger = $logger;
	}

	public function create(string $userId, string $rssUrl, string $rssContent, \SimpleXMLElement $xmlNode) : PodcastChannel {
		$channel = new PodcastChannel();
		self::parseChannelDataFromXml($xmlNode, $channel);

		$channel->setUserId( $userId );
		$channel->setRssUrl( Util::truncate($rssUrl, 2048) );
		$channel->setRssHash( \hash('md5', $rssUrl) );
		$channel->setContentHash( \hash('md5', $rssContent) );
		$channel->setUpdateChecked( \date(BaseMapper::SQL_DATE_FORMAT) );

		return $this->mapper->insert($channel);
	}

	/**
	 * @param PodcastChannel $channel Input/output parameter for the channel
	 * @param string $rssContent Raw content of the RSS feed
	 * @param \SimpleXMLElement $xmlNode <channel> node parsed from the RSS feed
	 * @return boolean true if the new content differed from the previously cached content
	 */
	public function updateChannel(PodcastChannel &$channel, string $rssContent, \SimpleXMLElement $xmlNode) {
		$contentChanged = false;
		$contentHash = \hash('md5', $rssContent);

		if ($channel->getContentHash() !== $contentHash) {
			$contentChanged = true;
			self::parseChannelDataFromXml($xmlNode, $channel);
			$channel->setContentHash($contentHash);
		}
		$channel->setUpdateChecked( \date(BaseMapper::SQL_DATE_FORMAT) );

		$this->update($channel);
		return $contentChanged;
	}

	private static function parseChannelDataFromXml(\SimpleXMLElement $xmlNode, PodcastChannel &$channel) : void {
		$itunesNodes = $xmlNode->children('http://www.itunes.com/dtds/podcast-1.0.dtd');

		// TODO: handling for invalid data
		$channel->setSourceUpdated( \date(BaseMapper::SQL_DATE_FORMAT,
				\strtotime((string)($xmlNode->lastBuildDate ?: $xmlNode->pubDate))) );
		$channel->setTitle( Util::truncate((string)$xmlNode->title, 256) );
		$channel->setLinkUrl( Util::truncate((string)$xmlNode->link, 2048) );
		$channel->setLanguage( Util::truncate((string)$xmlNode->language, 32) );
		$channel->setCopyright( Util::truncate((string)$xmlNode->copyright, 256) );
		$channel->setAuthor( Util::truncate((string)($xmlNode->author ?: $itunesNodes->author), 256) );
		$channel->setDescription( (string)($xmlNode->description ?: $itunesNodes->summary) );
		$channel->setImageUrl( (string)$xmlNode->image->url );
		$channel->setCategory( \implode(', ', \array_map(function ($category) {
			return $category->attributes()['text'];
		}, \iterator_to_array($itunesNodes->category, false))) );
	}

}
