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
 * @method setLinkUrl(string $url)
 * @method string getPublished()
 * @method setPublished(string $timestamp)
 * @method string getKeywords()
 * @method setKeywords(string $keywords)
 * @method string getCopyright()
 * @method setCopyright(string $copyright)
 * @method string getAuthor()
 * @method setAuthor(string $author)
 * @method string getDescription()
 * @method setDescription(string $description)
 * @method string getCreated()
 * @method setCreated(string $timestamp)
 * @method string getUpdated()
 * @method setUpdated(string $timestamp)
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
}
