<?php

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
 * @copyright Pauli Järvinen 2018 - 2020
 */

namespace OCA\Music\Search;

use \OCA\Music\App\Music;

class Provider extends \OCP\Search\Provider {

	private $artistMapper;
	private $albumMapper;
	private $trackMapper;
	private $urlGenerator;
	private $userId;
	private $l10n;
	private $resultTypeNames;
	private $resultTypePaths;

	public function __construct() {
		$app = new Music();
		$c = $app->getContainer();

		$this->artistMapper = $c->query('ArtistMapper');
		$this->albumMapper = $c->query('AlbumMapper');
		$this->trackMapper = $c->query('TrackMapper');
		$this->urlGenerator = $c->query('URLGenerator');
		$this->userId = $c->query('UserId');
		$this->l10n = $c->query('L10N');

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

		$artists = $this->artistMapper->findAllByName($query, $this->userId, true);
		foreach ($artists as $artist) {
			$results[] = $this->createResult($artist, $artist->name, 'music_artist');
		}

		$albums = $this->albumMapper->findAllByName($query, $this->userId, true);
		foreach ($albums as $album) {
			$results[] = $this->createResult($album, $album->name, 'music_album');
		}

		$tracks = $this->trackMapper->findAllByName($query, $this->userId, true);
		foreach ($tracks as $track) {
			$results[] = $this->createResult($track, $track->title, 'music_track');
		}

		return $results;
	}

}
