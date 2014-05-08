<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Leizh <leizh@free.fr>
 * @copyright Morris Jobke 2013, 2014
 * @copyright Leizh 2014
 */

namespace OCA\Music\Utility;

use \OCA\Music\App\Music;

class Search extends \OC_Search_Provider {
	public function search($query) {
		$app = new Music();
		$c = $app->getContainer();
		$artistMapper = $c->query('ArtistMapper');
		$albumMapper = $c->query('AlbumMapper');
		$trackMapper = $c->query('TrackMapper');
		$urlGenerator = $c->getServer()->getURLGenerator();
		$userId = $c->query('UserId');
		$l10n = $c->query('L10N');
		$pattern = $query;

		$results=array();
		$artists = $artistMapper->findAllByName($pattern, $userId, true);

		$container = '';
		$text = '';

		foreach($artists as $artist) {
			$name = $artist->name;
			$link = $urlGenerator->linkToRoute('music.page.index') . '#/artist/' . $artist->id;
			$type = (string)$l10n->t('Artists');
			$results[] = new \OC_Search_Result($name, $text, $link, $type, $container);
		}

		$albums = $albumMapper->findAllByName($pattern, $userId, true);
		foreach($albums as $album) {
			$name = $album->name;
			$link = $urlGenerator->linkToRoute('music.page.index') . '#/album/' . $album->id;
			$type = (string)$l10n->t('Albums');
			$results[] = new \OC_Search_Result($name, $text, $link, $type, $container);
		}

		$tracks = $trackMapper->findAllByName($pattern, $userId, true);
		foreach($tracks as $track) {
			$name = $track->title;
			$link = $urlGenerator->linkToRoute('music.page.index') . '#/track/' . $track->id;
			$type = (string)$l10n->t('Tracks');
			$results[] = new \OC_Search_Result($name, $text, $link, $type, $container);
		}
		return $results;
	}
}
