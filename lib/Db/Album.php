<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2013, 2014
 * @copyright Pauli Järvinen 2017 - 2025
 */

namespace OCA\Music\Db;

use OCP\IL10N;
use OCP\IURLGenerator;

use OCA\Music\Utility\Util;

/**
 * @method ?string getName()
 * @method void setName(?string $name)
 * @method ?string getMbid()
 * @method void setMbid(?string $mbid)
 * @method ?string getMbidGroup()
 * @method void setMbidGroup(?string $mbidGroup)
 * @method ?int getCoverFileId()
 * @method void setCoverFileId(?int $coverFileId)
 * @method int getAlbumArtistId()
 * @method void setAlbumArtistId(int $albumArtistId)
 * @method string getHash()
 * @method void setHash(string $hash)
 * @method ?string getStarred()
 * @method void setStarred(?string $timestamp)
 * @method ?int getRating()
 * @method setRating(?int $rating)
 * @method ?string getAlbumArtistName()
 */
class Album extends Entity {
	public $name;
	public $mbid;
	public $mbidGroup;
	public $coverFileId;
	public $albumArtistId;
	public $hash;
	public $starred;
	public $rating;
	public $albumArtistName; // not from music_albums table but still part of the standard content
	public $disk; // deprecated

	// extra fields injected separately by AlbumBusinessLayer
	private ?array $years = null;
	private ?array $genres = null; // *partial* Genre objects, not all properties are set
	private ?array $artistIds = null;
	private ?int $numberOfDisks = null;

	// injected separately when needed
	private $tracks;

	public function __construct() {
		$this->addType('disk', 'int');
		$this->addType('coverFileId', 'int');
		$this->addType('albumArtistId', 'int');
		$this->addType('rating', 'int');
	}

	public function getYears() : ?array {
		return $this->years;
	}

	public function setYears(?array $years) : void {
		$this->years = $years;
	}

	public function getGenres() : ?array {
		return $this->genres;
	}

	public function setGenres(?array $genres) : void {
		$this->genres = $genres;
	}

	public function getArtistIds() : ?array {
		return $this->artistIds;
	}

	public function setArtistIds(?array $artistIds) : void {
		$this->artistIds = $artistIds;
	}

	public function getNumberOfDisks() : ?int {
		return $this->numberOfDisks;
	}

	public function setNumberOfDisks(?int $count) : void {
		$this->numberOfDisks = $count;
	}

	/**
	 * @return ?Track[]
	 */
	public function getTracks() : ?array {
		return $this->tracks;
	}

	/**
	 * @param Track[] $tracks
	 */
	public function setTracks(array $tracks) : void {
		$this->tracks = $tracks;
	}

	/**
	 * Generates URL to album
	 * @param \OCP\IURLGenerator $urlGenerator
	 * @return string the url
	 */
	public function getUri(IURLGenerator $urlGenerator) : string {
		return $urlGenerator->linkToRoute(
			'music.shivaApi.album',
			['id' => $this->id]
		);
	}

	/**
	 * Returns an array of all artists - each with ID and URL for that artist
	 * @param \OCP\IURLGenerator $urlGenerator URLGenerator
	 * @return array
	 */
	public function getArtists(IURLGenerator $urlGenerator) : array {
		$artists = [];
		foreach ($this->artistIds as $artistId) {
			$artists[] = [
				'id' => $artistId,
				'uri' => $urlGenerator->linkToRoute(
					'music.shivaApi.artist',
					['id' => $artistId]
				)
			];
		}
		return $artists;
	}

	/**
	 * Returns the years(s) of the album.
	 * The album may have zero, one, or multiple years as people may tag tracks of
	 * collection albums with their original release dates. The respective formatted
	 * year ranges could be e.g. null, '2016', and '1995 - 2000'.
	 * @return string|null
	 */
	public function getYearRange() : ?string {
		$count = empty($this->years) ? 0 : \count($this->years);

		if ($count == 0) {
			return null;
		} elseif ($count == 1) {
			return (string)$this->years[0];
		} else {
			return \min($this->years) . ' - ' . \max($this->years);
		}
	}

	/**
	 * The Shiva and Ampache API definitions require the year to be a single numeric value.
	 * In case the album has multiple years, output the largest of these in the API.
	 * @return int|null
	 */
	public function yearToAPI() : ?int {
		return empty($this->years) ? null : (int)\max($this->years);
	}

	/**
	 * Returns the name of the album - if empty it returns the translated
	 * version of "Unknown album"
	 * @param IL10N $l10n
	 * @return string
	 */
	public function getNameString(IL10N $l10n) : string {
		return $this->getName() ?: self::unknownNameString($l10n);
	}

	/**
	 * Returns the name of the album artist - if empty it returns the translated
	 * version of "Unknown artist"
	 * @param IL10N $l10n
	 * @return string
	 */
	public function getAlbumArtistNameString(IL10N $l10n) : string {
		return $this->getAlbumArtistName() ?: Artist::unknownNameString($l10n);
	}

	/**
	 * Return the cover URL to be used in the Shiva API
	 * @param IURLGenerator $urlGenerator
	 * @return string|null
	 */
	public function coverToAPI(IURLGenerator $urlGenerator) : ?string {
		$coverUrl = null;
		if ($this->getCoverFileId() > 0) {
			$coverUrl = $urlGenerator->linkToRoute('music.coverApi.albumCover',
					['albumId' => $this->getId()]);
		}
		return $coverUrl;
	}

	/**
	 * If the cover image is already cached, the cover is presented with a link containing the image hash.
	 * Otherwise, the collection contains an URL which triggers the caching and then redirects to the
	 * URL with image hash.
	 * @param  IURLGenerator $urlGenerator URL Generator
	 * @param  string|null $cachedCoverHash Cached cover image hash if available
	 * @return string|null
	 */
	public function coverToCollection(IURLGenerator $urlGenerator, ?string $cachedCoverHash) : ?string {
		if (!empty($cachedCoverHash)) {
			return $urlGenerator->linkToRoute('music.coverApi.cachedCover', ['hash' => $cachedCoverHash]);
		} elseif ($this->getCoverFileId() > 0) {
			return $this->coverToAPI($urlGenerator);
		} else {
			return null;
		}
	}

	/**
	 * Creates object used for collection API (array with name, year, cover URL and ID)
	 * @param  IURLGenerator $urlGenerator URL Generator
	 * @param  IL10N $l10n Localization handler
	 * @param  string|null $cachedCoverHash Cached cover image hash if available
	 * @param  Track[] $tracks Tracks of the album in the "toCollection" format
	 * @return array collection API object
	 */
	public function toCollection(IURLGenerator $urlGenerator, IL10N $l10n, ?string $cachedCoverHash, array $tracks) : array {
		return [
			'name'      => $this->getNameString($l10n),
			'year'      => $this->getYearRange(),
			'cover'     => $this->coverToCollection($urlGenerator, $cachedCoverHash),
			'id'        => $this->getId(),
			'diskCount' => $this->getNumberOfDisks(),
			'tracks'    => $tracks
		];
	}

	/**
	 * Creates object used by the Shiva API (array with name, year, cover URL, ID, slug, URI and artists Array)
	 * @param  IURLGenerator $urlGenerator URL Generator
	 * @param  IL10N $l10n Localization handler
	 * @return array shiva API object
	 */
	public function toShivaApi(IURLGenerator $urlGenerator, IL10N $l10n) : array {
		return [
			'name'          => $this->getNameString($l10n),
			'year'          => $this->yearToAPI(),
			'cover'         => $this->coverToAPI($urlGenerator),
			'id'            => $this->getId(),
			'uri'           => $this->getUri($urlGenerator),
			'slug'          => $this->slugify('name'),
			'albumArtistId' => $this->getAlbumArtistId(),
			'artists'       => $this->getArtists($urlGenerator)
		];
	}

	public static function compareYearAndName(Album $a, Album $b) : int {
		$yearResult = \strcmp($a->getYearRange() ?? '', $b->getYearRange() ?? '');

		return $yearResult ?: Util::stringCaseCompare($a->getName(), $b->getName());
	}

	public static function unknownNameString(IL10N $l10n) : string {
		return (string) $l10n->t('Unknown album');
	}
}
