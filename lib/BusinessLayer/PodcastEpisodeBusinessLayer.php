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
use OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use OCA\Music\AppFramework\Core\Logger;

use OCA\Music\Db\BaseMapper;
use OCA\Music\Db\MatchMode;
use OCA\Music\Db\PodcastEpisodeMapper;
use OCA\Music\Db\PodcastEpisode;
use OCA\Music\Db\SortBy;
use OCA\Music\Utility\StringUtil;


/**
 * Base class functions with the actually used inherited types to help IDE and Scrutinizer:
 * @method PodcastEpisode find(int $episodeId, string $userId)
 * @method PodcastEpisode[] findAll(string $userId, int $sortBy=SortBy::Name, int $limit=null, int $offset=null, ?string $createdMin=null, ?string $createdMax=null, ?string $updatedMin=null, ?string $updatedMax=null)
 * @method PodcastEpisode[] findAllByName(string $name, string $userId, int $matchMode=MatchMode::Exact, int $limit=null, int $offset=null, ?string $createdMin=null, ?string $createdMax=null, ?string $updatedMin=null, ?string $updatedMax=null)
 * @property PodcastEpisodeMapper $mapper
 * @phpstan-extends BusinessLayer<PodcastEpisode>
 */
class PodcastEpisodeBusinessLayer extends BusinessLayer {
	private Logger $logger;

	public function __construct(PodcastEpisodeMapper $mapper, Logger $logger) {
		parent::__construct($mapper);
		$this->logger = $logger;
	}

	/**
	 * @param int|int[] $channelIds
	 * @return PodcastEpisode[]
	 */
	public function findAllByChannel($channelIds, string $userId, ?int $limit=null, ?int $offset=null) : array {
		if (!\is_array($channelIds)) {
			$channelIds = [$channelIds];
		}
		return $this->mapper->findAllByChannel($channelIds, $userId, $limit, $offset);
	}

	public function deleteByChannel(int $channelId, string $userId) : void {
		$this->mapper->deleteByChannel($channelId, $userId);
	}

	public function deleteByChannelExcluding(int $channelId, array $excludedIds, string $userId) : void {
		$this->mapper->deleteByChannelExcluding($channelId, $excludedIds, $userId);
	}

	public function addOrUpdate(string $userId, int $channelId, \SimpleXMLElement $xmlNode) : PodcastEpisode {
		$episode = self::parseEpisodeFromXml($xmlNode, $this->logger);

		$episode->setUserId($userId);
		$episode->setChannelId($channelId);

		return $this->mapper->updateOrInsert($episode);
	}

	private static function parseEpisodeFromXml(\SimpleXMLElement $xmlNode, Logger $logger) : PodcastEpisode {
		$episode = new PodcastEpisode();

		$itunesNodes = $xmlNode->children('http://www.itunes.com/dtds/podcast-1.0.dtd');

		if (!$xmlNode->enclosure || !$xmlNode->enclosure->attributes()) {
			$logger->log("No stream URL for the episode " . $xmlNode->title, 'debug');
			$streamUrl = null;
			$mimetype = null;
			$size = null;
		} else {
			$streamUrl = (string)$xmlNode->enclosure->attributes()['url'];
			$mimetype = (string)$xmlNode->enclosure->attributes()['type'];
			$size = (int)$xmlNode->enclosure->attributes()['length'];
		}

		$guid = (string)$xmlNode->guid ?: $streamUrl;
		if (!$guid) {
			throw new BusinessLayerException('Invalid episode, neither <guid> nor <enclosure url> is included');
		}

		$episode->setStreamUrl( StringUtil::truncate($streamUrl, 2048) );
		$episode->setMimetype( StringUtil::truncate($mimetype, 256) );
		$episode->setSize( $size );
		$episode->setDuration( self::parseDuration((string)$itunesNodes->duration) );
		$episode->setGuid( StringUtil::truncate($guid, 2048) );
		$episode->setGuidHash( \hash('md5', $guid) );
		$episode->setTitle( self::parseTitle($itunesNodes->title, $xmlNode->title, $itunesNodes->episode) );
		$episode->setEpisode( (int)$itunesNodes->episode ?: null );
		$episode->setSeason( (int)$itunesNodes->season ?: null );
		$episode->setLinkUrl( StringUtil::truncate((string)$xmlNode->link, 2048) );
		$episode->setPublished( \date(BaseMapper::SQL_DATE_FORMAT, \strtotime((string)($xmlNode->pubDate))) );
		$episode->setKeywords( StringUtil::truncate((string)$itunesNodes->keywords, 256) );
		$episode->setCopyright( StringUtil::truncate((string)$xmlNode->copyright, 256) );
		$episode->setAuthor( StringUtil::truncate((string)($xmlNode->author ?: $itunesNodes->author), 256) );
		$episode->setDescription( (string)($xmlNode->description ?: $itunesNodes->summary) );

		return $episode;
	}

	private static function parseTitle($itunesTitle, $title, $episode) : ?string {
		// Prefer to use the iTunes title over the standard title, because sometimes,
		// the generic title contains the episode number which is also provided separately
		// while the iTunes title does not.
		$result = (string)($itunesTitle ?: $title);

		// If there still is the same episode number prefixed in the beginning of the title
		// as is provided separately, attempt to crop that.
		if ($episode) {
			$matches = null;
			if (\preg_match("/^$episode\s*[\.:-]\s*(.+)$/", $result, $matches) === 1) {
				$result = $matches[1];
			}
		}

		return StringUtil::truncate($result, 256);
	}

	private static function parseDuration(string $data) :?int {
		$matches = null;

		if (\ctype_digit($data)) {
			return (int)$data; // plain seconds
		} elseif (\preg_match('/^(?:(?:(?<hours>\d+):)?(?<minutes>[0-5]?\d):)?(?<seconds>[0-5]\d)(?:\.\d*)?$/', $data, $matches) === 1) {
			return (int)$matches['hours'] * 3600 + (int)$matches['minutes'] * 60 + (int)$matches['seconds']; // HH:MM:SS
		} else {
			return null; // no value or unsupported format
		}
	}
}
