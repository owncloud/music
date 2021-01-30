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
 * @copyright Pauli Järvinen 2017 - 2021
 */

namespace OCA\Music\Db;

use \OCP\IL10N;
use \OCP\IURLGenerator;

use \OCP\AppFramework\Db\Entity;

/**
 * @method ?string getName()
 * @method void setName(?string $name)
 * @method ?int getCoverFileId()
 * @method void setCoverFileId(?int $coverFileId)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method ?string getMbid()
 * @method void setMbid(?string $mbid)
 * @method string getHash()
 * @method void setHash(string $hash)
 * @method ?string getStarred()
 * @method void setStarred(?string $timestamp)
 * @method string getCreated()
 * @method setCreated(string $timestamp)
 * @method string getUpdated()
 * @method setUpdated(string $timestamp)
 * @method ?string getLastfmUrl()
 * @method void setLastfmUrl(?string $lastfmUrl)
 */
class Artist extends Entity {
	public $name;
	public $coverFileId;
	public $userId;
	public $mbid;
	public $hash;
	public $starred;
	public $created;
	public $updated;

	// not part of the standard content, injected separately when needed
	public $lastfmUrl;

	public function __construct() {
		$this->addType('coverFileId', 'int');
	}

	public function getUri(IURLGenerator $urlGenerator) {
		return $urlGenerator->linkToRoute(
			'music.api.artist',
			['artistIdOrSlug' => $this->id]
		);
	}

	public function getNameString(IL10N $l10n) {
		return $this->getName() ?: self::unknownNameString($l10n);
	}

	/**
	 * Get initial character of the artist name in upper case.
	 * This is intended to be used as index in a list of aritsts.
	 */
	public function getIndexingChar() {
		// For unknown artists, use '?'
		$char = '?';
		$name = $this->getName();

		if (!empty($name)) {
			$char = \mb_convert_case(\mb_substr($name, 0, 1), MB_CASE_UPPER);
		}
		// Bundle all numeric characters together
		if (\is_numeric($char)) {
			$char = '#';
		}

		return $char;
	}

	/**
	 * Return the cover URL to be used in the Shiva API
	 * @param IURLGenerator $urlGenerator
	 * @return string|null
	 */
	public function coverToAPI(IURLGenerator $urlGenerator) {
		$coverUrl = null;
		if ($this->getCoverFileId() > 0) {
			$coverUrl = $urlGenerator->linkToRoute('music.api.artistCover',
					['artistIdOrSlug' => $this->getId()]);
		}
		return $coverUrl;
	}

	/**
	 * @param IL10N $l10n
	 * @param array $albums in the "toCollection" format
	 * @return array
	 */
	public function toCollection(IL10N $l10n, $albums) {
		return [
			'id' => $this->getId(),
			'name' => $this->getNameString($l10n),
			'albums' => $albums
		];
	}

	public function toAPI(IURLGenerator $urlGenerator, IL10N $l10n) {
		return [
			'id' => $this->getId(),
			'name' => $this->getNameString($l10n),
			'image' => $this->coverToAPI($urlGenerator),
			'slug' => $this->getId() . '-' . $this->slugify('name'),
			'uri' => $this->getUri($urlGenerator)
		];
	}

	public static function unknownNameString(IL10N $l10n) {
		return (string) $l10n->t('Unknown artist');
	}
}
