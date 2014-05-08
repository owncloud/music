<?php

/**
 * ownCloud - Music app
 *
 * @author Leizh
 * @author Morris Jobke
 * @copyright 2013 Leizh <leizh@free.fr>
 * @copyright 2014 Morris Jobke <morris.jobke@gmail.com>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
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
