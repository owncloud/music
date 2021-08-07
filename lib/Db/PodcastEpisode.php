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

namespace OCA\Music\Db;

use \OCP\AppFramework\Db\Entity;
use \OCA\Music\Utility\Util;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method int getChannelId()
 * @method void setChannelId(int $id)
 * @method string getStreamUrl()
 * @method setStreamUrl(string $url)
 * @method string getMimetype()
 * @method setMimetype(string $mime)
 * @method int getSize()
 * @method void setSize(int $size)
 * @method int getDuration()
 * @method void setDuration(int $duration)
 * @method string getGuid()
 * @method void setGuid(string $guid)
 * @method string getGuidHash()
 * @method void setGuidHash(string $guidHash)
 * @method string getTitle()
 * @method void setTitle(string $title)
 * @method int getEpisode()
 * @method void setEpisode(int $episode)
 * @method string getLinkUrl()
 * @method void setLinkUrl(string $url)
 * @method string getPublished()
 * @method void setPublished(string $timestamp)
 * @method string getKeywords()
 * @method void setKeywords(string $keywords)
 * @method string getCopyright()
 * @method void setCopyright(string $copyright)
 * @method string getAuthor()
 * @method void setAuthor(string $author)
 * @method string getDescription()
 * @method void setDescription(string $description)
 * @method string getStarred()
 * @method void setStarred(string $timestamp)
 * @method string getCreated()
 * @method void setCreated(string $timestamp)
 * @method string getUpdated()
 * @method void setUpdated(string $timestamp)
 */
class PodcastEpisode extends Entity {
	public $userId;
	public $channelId;
	public $streamUrl;
	public $mimetype;
	public $size;
	public $duration;
	public $guid;
	public $guidHash;
	public $title;
	public $episode;
	public $linkUrl;
	public $published;
	public $keywords;
	public $copyright;
	public $author;
	public $description;
	public $starred;
	public $created;
	public $updated;

	public function __construct() {
		$this->addType('channelId', 'int');
		$this->addType('size', 'int');
		$this->addType('duration', 'int');
		$this->addType('episode', 'int');
	}

	public function toApi() : array {
		return [
			'id' => $this->getId(),
			'title' => $this->getTitle(),
			'stream_url' => $this->getStreamUrl(),
			'mimetype' => $this->getMimetype()
		];
	}

	public function toAmpacheApi() : array {
		return [
			'id' => (string)$this->getId(),
			'name' => $this->getTitle(),
			'title' => $this->getTitle(),
			'description' => $this->getDescription(),
			'author' => $this->getAuthor(),
			'author_full' => $this->getAuthor(),
			'website' => $this->getLinkUrl(),
			'pubdate' => $this->getPublished(), // TODO: format?
			'state' => 'Completed',
			'filelength' => Util::formatTime($this->getDuration()),
			'filesize' => Util::formatFileSize($this->getSize(), 2) . 'B',
			'mime' => $this->getMimetype(),
			'url' => $this->getStreamUrl(),
			'flag' => empty($this->getStarred()) ? 0 : 1,
		];
	}

	public function toSubsonicApi() : array {
		$result = [
			'id' => 'podcast_episode-' . $this->getId(),
			'streamId' => 'podcast_episode-' . $this->getId(),
			'channelId' => 'podcast_channel-' . $this->getChannelId(),
			'title' => $this->getTitle(),
			'description' => $this->getDescription(),
			'publishDate' => Util::formatZuluDateTime($this->getPublished()),
			'status' => 'completed',
			'parent' => $this->getChannelId(),
			'isDir' => false,
			'year' => $this->getYear(),
			'genre' => 'Podcast',
			'coverArt' => 'podcast_channel-' . $this->getChannelId(),
			'size' => $this->getSize(),
			'contentType' => $this->getMimetype(),
			//'suffix' => 'mp3',
			'duration' => $this->getDuration(),
			//'bitRate' => 128
		];

		if (!empty($this->starred)) {
			$result['starred'] = Util::formatZuluDateTime($this->starred);
		}

		return $result;
	}

	public function getYear() : ?int {
		$matches = null;
		if (\is_string($this->published) && \preg_match('/^(\d\d\d\d)-\d\d-\d\d.*/', $this->published, $matches) === 1) {
			return (int)$matches[1];
		} else {
			return null;
		}
	}
}
