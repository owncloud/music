<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Leizh <leizh@free.fr>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2013, 2014
 * @copyright Leizh 2014
 * @copyright Pauli Järvinen 2018 - 2021
 */

namespace OCA\Music\Search;

use OCA\Music\App\Music;

class Provider extends \OCP\Search\Provider {

	/* Limit the maximum number of matches because the Provider API is limited and does
	 * not support pagination. The core paginates the results to 30 item pages, but it
	 * obtains all the items from the Providers again on creation of each page.
	 * If there were thousands of mathces, we would end up doing lot of unnecessary work.
	 */
	const MAX_RESULTS_PER_TYPE = 100;

	private $artistMapper;
	private $albumMapper;
	private $trackMapper;
	private $urlGenerator;
	private $userId;
	private $l10n;
	private $resultTypeNames;
	private $resultTypePaths;
	private $logger;

	public function __construct() {
		$app = \OC::$server->query(Music::class);
		$c = $app->getContainer();

		$this->artistMapper = $c->query('ArtistMapper');
		$this->albumMapper = $c->query('AlbumMapper');
		$this->trackMapper = $c->query('TrackMapper');
		$this->urlGenerator = $c->query('URLGenerator');
		$this->userId = $c->query('UserId');
		$this->l10n = $c->query('L10N');
		$this->logger = $c->query('Logger');

		$this->resultTypeNames = [
			'music_artist' => $this->l10n->t('Artist'),
			'music_album' => $this->l10n->t('Album'),
			'music_track' => $this->l10n->t('Track')
		];

		$basePath = $this->urlGenerator->linkToRoute('music.page.index');
		$this->resultTypePaths = [
			'music_artist' => $basePath . "#/artist/",
			'music_album' => $basePath . "#/album/",
			'music_track' => $basePath . "#/track/"
		];
	}

	private function createResult($entity, $title, $type) {
		$link = $this->resultTypePaths[$type] . $entity->id;
		$titlePrefix = $this->l10n->t('Music') . ' - ' . $this->resultTypeNames[$type] . ': ';
		return new Result($entity->id, $titlePrefix . $title, $link, $type);
	}

	public function search($query) {
		$results=[];

		$artists = $this->artistMapper->findAllByName($query, $this->userId, true, self::MAX_RESULTS_PER_TYPE);
		foreach ($artists as $artist) {
			$results[] = $this->createResult($artist, $artist->name, 'music_artist');
		}

		$albums = $this->albumMapper->findAllByName($query, $this->userId, true, self::MAX_RESULTS_PER_TYPE);
		foreach ($albums as $album) {
			$results[] = $this->createResult($album, $album->name, 'music_album');
		}

		$tracks = $this->trackMapper->findAllByName($query, $this->userId, true, self::MAX_RESULTS_PER_TYPE);
		foreach ($tracks as $track) {
			$results[] = $this->createResult($track, $track->title, 'music_track');
		}

		return $results;
	}
}
