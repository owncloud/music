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

/**
 * @method ?string getName()
 * @method void setName(?string $name)
 * @method ?int getCoverFileId()
 * @method void setCoverFileId(?int $coverFileId)
 * @method ?string getMbid()
 * @method void setMbid(?string $mbid)
 * @method string getHash()
 * @method void setHash(string $hash)
 * @method ?string getStarred()
 * @method void setStarred(?string $timestamp)
 * @method ?int getRating()
 * @method setRating(?int $rating)
 */
class Artist extends Entity {
	public $name;
	public $coverFileId;
	public $mbid;
	public $hash;
	public $starred;
	public $rating;

	// not part of the standard content, injected separately when needed
	private ?string $lastfmUrl = null;
	private ?array $albums = null;
	private ?array $tracks = null;

	public function __construct() {
		$this->addType('coverFileId', 'int');
		$this->addType('rating', 'int');
	}

	public function getLastfmUrl() : ?string {
		return $this->lastfmUrl;
	}

	public function setLastfmUrl(?string $lastfmUrl) : void {
		$this->lastfmUrl = $lastfmUrl;
	}

	/**
	 * @return ?Album[]
	 */
	public function getAlbums() : ?array {
		return $this->albums;
	}

	/**
	 * @param Album[] $albums
	 */
	public function setAlbums(array $albums) : void {
		$this->albums = $albums;
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

	public function getUri(IURLGenerator $urlGenerator) : string {
		return $urlGenerator->linkToRoute(
			'music.shivaApi.artist',
			['id' => $this->id]
		);
	}

	public function getNameString(IL10N $l10n) : string {
		return $this->getName() ?: self::unknownNameString($l10n);
	}

	/**
	 * Return the cover URL to be used in the API
	 */
	public function coverToAPI(IURLGenerator $urlGenerator) : ?string {
		$coverUrl = null;
		if ($this->getCoverFileId() > 0) {
			$coverUrl = $urlGenerator->linkToRoute('music.coverApi.artistCover',
					['artistId' => $this->getId()]);
		}
		return $coverUrl;
	}

	/**
	 * @param array $albums in the "toCollection" format
	 */
	public function toCollection(IURLGenerator $urlGenerator, IL10N $l10n, array $albums) : array {
		return [
			'id' => $this->getId(),
			'name' => $this->getNameString($l10n),
			'albums' => $albums,
			'cover' => $this->coverToAPI($urlGenerator)
		];
	}

	public function toShivaApi(IURLGenerator $urlGenerator, IL10N $l10n) : array {
		return [
			'id' => $this->getId(),
			'name' => $this->getNameString($l10n),
			'image' => $this->coverToAPI($urlGenerator),
			'slug' => $this->slugify('name'),
			'uri' => $this->getUri($urlGenerator)
		];
	}

	public static function unknownNameString(IL10N $l10n) : string {
		return (string) $l10n->t('Unknown artist');
	}
}
