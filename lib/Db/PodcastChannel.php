<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2021 - 2023
 */

namespace OCA\Music\Db;

use OCA\Music\Utility\Util;
use OCP\IURLGenerator;

/**
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
 * @method ?int getRating()
 * @method setRating(?int $rating)
 */
class PodcastChannel extends Entity {
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
	public $rating;

	// not part of the default content, may be injected separately
	private $episodes;

	public function __construct() {
		$this->addType('rating', 'int');
	}

	/**
	 * @return ?PodcastEpisode[]
	 */
	public function getEpisodes() : ?array {
		return $this->episodes;
	}

	/**
	 * @param PodcastEpisode[] $episodes
	 */
	public function setEpisodes(array $episodes) : void {
		$this->episodes = $episodes;
	}

	public function toApi(IURLGenerator $urlGenerator) : array {
		$result = [
			'id' => $this->getId(),
			'title' => $this->getTitle(),
			'image' => $this->createImageUrl($urlGenerator),
			'hash' => $this->getContentHash()
		];

		if ($this->episodes !== null) {
			$result['episodes'] = \array_map(fn($e) => $e->toApi($urlGenerator), $this->episodes);
		}

		return $result;
	}

	public function detailsToApi(IURLGenerator $urlGenerator) : array {
		return [
			'id' => $this->getId(),
			'title' => $this->getTitle(),
			'description' => $this->getDescription(),
			'image' => $this->createImageUrl($urlGenerator) . '?originalSize=true',
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
			'has_art' => !empty($this->getImageUrl()),
			'flag' => !empty($this->getStarred()),
			'rating' => $this->getRating() ?? 0,
			'preciserating' => $this->getRating() ?? 0,
		];

		if ($this->episodes !== null) {
			$createImageUrl = fn($e) => $this->getImageUrl();
			$result['podcast_episode'] = \array_map(fn($e) => $e->toAmpacheApi($createImageUrl, null), $this->episodes);
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
			'status' => 'completed',
			'starred' => Util::formatZuluDateTime($this->getStarred())
		];

		if ($this->episodes !== null) {
			$result['episode'] = \array_map(fn($e) => $e->toSubsonicApi(), $this->episodes);
		}

		return $result;
	}

	/**
	 * Create URL which loads the channel image via the cloud server. The URL handles down-scaling and caching automatically.
	 */
	private function createImageUrl(IURLGenerator $urlGenerator) : string {
		return $urlGenerator->linkToRoute('music.coverApi.podcastCover', ['channelId' => $this->getId()]);
	}
}
