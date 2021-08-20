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
 * @method string getRssUrl()
 * @method void setRssUrl(string $url)
 * @method string getRssHash()
 * @method void setRssHash(string $hash)
 * @method string getContentHash()
 * @method void setContentHash(string $hash)
 * @method string getUpdateChecked()
 * @method void setUpdateChecked(string $timestamp)
 * @method string getPublished()
 * @method void setPublished(string $timestamp)
 * @method string getLastBuildDate()
 * @method void setLastBuildDate(string $timestamp)
 * @method string getTitle()
 * @method void setTitle(string $title)
 * @method string getLinkUrl()
 * @method void setLinkUrl(string $url)
 * @method string getLanguage()
 * @method void setLanguage(string $language)
 * @method string getCopyright()
 * @method void setCopyright(string $copyright)
 * @method string getAuthor()
 * @method void setAuthor(string $author)
 * @method string getDescription()
 * @method void setDescription(string $description)
 * @method string getImageUrl()
 * @method void setImageUrl(string $url)
 * @method string getCategory()
 * @method void setCategory(string $category)
 * @method string getStarred()
 * @method void setStarred(string $timestamp)
 * @method string getCreated()
 * @method void setCreated(string $timestamp)
 * @method string getUpdated()
 * @method void setUpdated(string $timestamp)
 * @method PodcastEpisode[] getEpisodes()
 * @method void setEpisodes(PodcastEpisode[] $episodes)
 */
class PodcastChannel extends Entity {
	public $userId;
	public $rssUrl;
	public $rssHash;
	public $contentHash;
	public $updateChecked;
	public $published;
	public $lastBuildDate;
	public $title;
	public $linkUrl;
	public $language;
	public $copyright;
	public $author;
	public $description;
	public $imageUrl;
	public $category;
	public $starred;
	public $created;
	public $updated;

	// not part of the default content, may be injected separately
	public $episodes;

	public function toApi() : array {
		$result = [
			'id' => $this->getId(),
			'title' => $this->getTitle(),
			'image' => $this->getImageUrl(),
			'hash' => $this->getContentHash()
		];

		if ($this->episodes !== null) {
			$result['episodes'] = Util::arrayMapMethod($this->episodes, 'toApi');
		}

		return $result;
	}

	public function detailsToApi() : array {
		return [
			'id' => $this->getId(),
			'title' => $this->getTitle(),
			'description' => $this->getDescription(),
			'image' => $this->getImageUrl(),
			'link_url' =>  $this->getLinkUrl(),
			'rss_url' => $this->getRssUrl(),
			'language' => $this->getLanguage(),
			'copyright' => $this->getCopyright(),
			'author' => $this->getAuthor(),
			'category' => $this->getCategory(),
			'published' => $this->getPublished(),
			'last_build_date' => $this->getLastBuildDate(),
			'update_checked' => $this->getUpdateChecked(),
		];
	}

	public function toAmpacheApi() : array {
		$result = [
			'id' => (string)$this->getId(),
			'name' => $this->getTitle(),
			'description' => $this->getDescription(),
			'language' => $this->getLanguage(),
			'copyright' => $this->getCopyright(),
			'feed_url' => $this->getRssUrl(),
			'build_date' => Util::formatDateTimeUtcOffset($this->getLastBuildDate()),
			'sync_date' => Util::formatDateTimeUtcOffset($this->getUpdateChecked()),
			'public_url' => $this->getLinkUrl(),
			'website' => $this->getLinkUrl(),
			'art' => $this->getImageUrl(),
			'flag' => empty($this->getStarred()) ? 0 : 1,
		];

		if ($this->episodes !== null) {
			$result['podcast_episode'] = Util::arrayMapMethod($this->episodes, 'toAmpacheApi');
		}

		return $result;
	}

	public function toSubsonicApi() : array {
		$result = [
			'id' => 'podcast_channel-' . $this->getId(),
			'url' => $this->getRssUrl(),
			'title' => $this->getTitle(),
			'description' => $this->getDescription(),
			'coverArt' => 'podcast_channel-' . $this->getId(),
			'originalImageUrl' => $this->getImageUrl(),
			'status' => 'completed'
		];

		if (!empty($this->starred)) {
			$result['starred'] = Util::formatZuluDateTime($this->starred);
		}

		if ($this->episodes !== null) {
			$result['episode'] = Util::arrayMapMethod($this->episodes, 'toSubsonicApi');
		}

		return $result;
	}
}
