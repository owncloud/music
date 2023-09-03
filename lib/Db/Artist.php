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
 * @copyright Pauli Järvinen 2017 - 2023
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
	private $lastfmUrl;
	private $albums;
	private $tracks;

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
			['artistId' => $this->id]
		);
	}

	public function getNameString(IL10N $l10n) : string {
		return $this->getName() ?: self::unknownNameString($l10n);
	}

	/**
	 * Return the cover URL to be used in the Shiva API
	 */
	public function coverToAPI(IURLGenerator $urlGenerator) : ?string {
		$coverUrl = null;
		if ($this->getCoverFileId() > 0) {
			$coverUrl = $urlGenerator->linkToRoute('music.api.artistCover',
					['artistId' => $this->getId()]);
		}
		return $coverUrl;
	}

	/**
	 * @param array $albums in the "toCollection" format
	 */
	public function toCollection(IL10N $l10n, array $albums) : array {
		return [
			'id' => $this->getId(),
			'name' => $this->getNameString($l10n),
			'albums' => $albums
		];
	}

	public function toAPI(IURLGenerator $urlGenerator, IL10N $l10n) : array {
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
