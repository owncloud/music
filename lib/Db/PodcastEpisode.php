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

namespace OCA\Music\Db;

use OCA\Music\Utility\Util;
use OCP\IURLGenerator;

/**
 * @method int getChannelId()
 * @method void setChannelId(int $id)
 * @method ?string getStreamUrl()
 * @method setStreamUrl(?string $url)
 * @method ?string getMimetype()
 * @method setMimetype(?string $mime)
 * @method ?int getSize()
 * @method void setSize(?int $size)
 * @method ?int getDuration()
 * @method void setDuration(?int $duration)
 * @method string getGuid()
 * @method void setGuid(string $guid)
 * @method string getGuidHash()
 * @method void setGuidHash(string $guidHash)
 * @method ?string getTitle()
 * @method void setTitle(?string $title)
 * @method ?int getEpisode()
 * @method void setEpisode(?int $episode)
 * @method ?int getSeason()
 * @method void setSeason(?int $season)
 * @method ?string getLinkUrl()
 * @method void setLinkUrl(?string $url)
 * @method ?string getPublished()
 * @method void setPublished(?string $timestamp)
 * @method ?string getKeywords()
 * @method void setKeywords(?string $keywords)
 * @method ?string getCopyright()
 * @method void setCopyright(?string $copyright)
 * @method ?string getAuthor()
 * @method void setAuthor(?string $author)
 * @method ?string getDescription()
 * @method void setDescription(?string $description)
 * @method ?string getStarred()
 * @method void setStarred(?string $timestamp)
 * @method ?int getRating()
 * @method setRating(?int $rating)
 */
class PodcastEpisode extends Entity {
	public $channelId;
	public $streamUrl;
	public $mimetype;
	public $size;
	public $duration;
	public $guid;
	public $guidHash;
	public $title;
	public $episode;
	public $season;
	public $linkUrl;
	public $published;
	public $keywords;
	public $copyright;
	public $author;
	public $description;
	public $starred;
	public $rating;

	public function __construct() {
		$this->addType('channelId', 'int');
		$this->addType('size', 'int');
		$this->addType('duration', 'int');
		$this->addType('episode', 'int');
		$this->addType('rating', 'int');
	}

	public function toApi(IURLGenerator $urlGenerator) : array {
		return [
			'id' => $this->getId(),
			'title' => $this->getTitle(),
			'ordinal' => $this->getEpisodeWithSeason(),
			'stream_url' => $urlGenerator->linkToRoute('music.podcastApi.episodeStream', ['id' => $this->id]),
			'mimetype' => $this->getMimetype()
		];
	}

	public function detailsToApi() : array {
		return [
			'id' => $this->getId(),
			'title' => $this->getTitle(),
			'episode' => $this->getEpisode(),
			'season' => $this->getSeason(),
			'description' => $this->getDescription(),
			'channel_id' => $this->getChannelId(),
			'link_url' => $this->getLinkUrl(),
			'stream_url' => $this->getStreamUrl(),
			'mimetype' => $this->getMimetype(),
			'author' => $this->getAuthor(),
			'copyright' => $this->getCopyright(),
			'duration' => $this->getDuration(),
			'size' => $this->getSize(),
			'bit_rate' => $this->getBitrate(),
			'guid' => $this->getGuid(),
			'keywords' => $this->getKeywords(),
			'published' => $this->getPublished(),
		];
	}

	public function toAmpacheApi(callable $createImageUrl, ?callable $createStreamUrl) : array {
		$imageUrl = $createImageUrl($this);
		return [
			'id' => (string)$this->getId(),
			'name' => $this->getTitle(),
			'title' => $this->getTitle(),
			'description' => $this->getDescription(),
			'author' => $this->getAuthor(),
			'author_full' => $this->getAuthor(),
			'website' => $this->getLinkUrl(),
			'pubdate' => Util::formatDateTimeUtcOffset($this->getPublished()),
			'state' => 'Completed',
			'filelength' => Util::formatTime($this->getDuration()),
			'filesize' => Util::formatFileSize($this->getSize(), 2) . 'B',
			'bitrate' => $this->getBitrate(),
			'stream_bitrate' => $this->getBitrate(),
			'time' => $this->getDuration(),
			'size' => $this->getSize(),
			'mime' => $this->getMimetype(),
			'url' => $createStreamUrl ? $createStreamUrl($this) : $this->getStreamUrl(),
			'art' => $imageUrl,
			'has_art' => !empty($imageUrl),
			'flag' => !empty($this->getStarred()),
			'rating' => $this->getRating() ?? 0,
			'preciserating' => $this->getRating() ?? 0,
		];
	}

	public function toSubsonicApi() : array {
		return [
			'id' => 'podcast_episode-' . $this->getId(),
			'streamId' => 'podcast_episode-' . $this->getId(),
			'channelId' => 'podcast_channel-' . $this->getChannelId(),
			'title' => $this->getTitle(),
			'artist' => $this->getAuthor(),
			'track' => $this->getEpisode(),
			'description' => $this->getDescription(),
			'publishDate' => Util::formatZuluDateTime($this->getPublished()),
			'status' => 'completed',
			'parent' => 'podcast_channel-' . $this->getChannelId(),
			'isDir' => false,
			'year' => $this->getYear(),
			'genre' => 'Podcast',
			'coverArt' => 'podcast_channel-' . $this->getChannelId(),
			'size' => $this->getSize(),
			'contentType' => $this->getMimetype(),
			'suffix' => $this->getSuffix(),
			'duration' => $this->getDuration(),
			'bitRate' => empty($this->getBitrate()) ? 0 : (int)\round($this->getBitrate()/1000), // convert bps to kbps
			'type' => 'podcast',
			'created' => Util::formatZuluDateTime($this->getCreated()),
			'starred' => Util::formatZuluDateTime($this->getStarred()),
			'userRating' => $this->getRating() ?: null,
			'averageRating' => $this->getRating() ?: null,
		];
	}

	public function getEpisodeWithSeason() : ?string {
		$result = (string)$this->getEpisode();
		// the season is considered only if there actually is an episode
		$season = $this->getSeason();
		if ($season !== null) {
			$result = "$season-$result";
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

	/** @return ?float bits per second (bps) */
	public function getBitrate() : ?float {
		if (empty($this->size) || empty($this->duration)) {
			return null;
		} else {
			return $this->size / $this->duration * 8;
		}
	}

	public function getSuffix() : ?string {
		return self::mimeToSuffix($this->mimetype) ?? self::extractSuffixFromUrl($this->streamUrl);
	}

	private static function mimeToSuffix(?string $mime) : ?string {
		// a relevant subset from https://stackoverflow.com/a/53662733/4348850 wit a few additions
		$mime_map = [
			'audio/x-acc'					=> 'aac',
			'audio/ac3'						=> 'ac3',
			'audio/x-aiff'					=> 'aif',
			'audio/aiff'					=> 'aif',
			'audio/x-au'					=> 'au',
			'audio/x-flac'					=> 'flac',
			'audio/x-m4a'					=> 'm4a',
			'audio/mp4'						=> 'm4a',
			'audio/midi'					=> 'mid',
			'audio/mpeg'					=> 'mp3',
			'audio/mpg'						=> 'mp3',
			'audio/mpeg3'					=> 'mp3',
			'audio/mp3'						=> 'mp3',
			'audio/ogg'						=> 'ogg',
			'application/ogg'				=> 'ogg',
			'audio/x-realaudio'				=> 'ra',
			'audio/x-pn-realaudio'			=> 'ram',
			'audio/x-wav'					=> 'wav',
			'audio/wave'					=> 'wav',
			'audio/wav'						=> 'wav',
			'audio/x-ms-wma'				=> 'wma',
			'audio/m4b'						=> 'm4b',
			'application/vnd.apple.mpegurl'	=> 'm3u',
			'audio/mpegurl'					=> 'm3u',
		];

		return $mime_map[$mime] ?? null;
	}

	private static function extractSuffixFromUrl(?string $url) : ?string {
		if ($url === null) {
			return null;
		} else {
			$path = \parse_url($url, PHP_URL_PATH);
			$ext = (string)\pathinfo($path, PATHINFO_EXTENSION);
			return !empty($ext) ? $ext : null;
		}
	}
}
