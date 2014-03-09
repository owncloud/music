<?php

/**
 * ownCloud - Music app
 *
 * @author Leizh
 * @copyright 2013 Leizh <leizh@free.fr>
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

use \OCA\Music\DependencyInjection\DIContainer;

class Search extends \OC_Search_Provider {
	function search($query) {
		$l = \OC_L10N::get('lib');
		$c = new DIContainer();
		$api = $c['API'];
		$artistMapper = $c['ArtistMapper'];
		$albumMapper = $c['AlbumMapper'];
		$trackMapper = $c['TrackMapper'];
		$userId = $api->getUserId();
		$pattern = '%' . $query . '%';
		
		$results=array();
		$artists = $artistMapper->findByNameLike($pattern, $userId);
		
		$container = '';
		$text = '';
		
		foreach($artists as $artist) {
			$name = $artist->name;
			$link = $api->linkToRoute('music_index') . '#/artist/' . $artist->id;
			$type = (string)$l->t('Artists');
			$results[] = new \OC_Search_Result($name, $text, $link, $type, $container);
		}

		$albums = $albumMapper->findByNameLike($pattern, $userId);
		foreach($albums as $album) {
			$name = $album->name;
			$link = $api->linkToRoute('music_index') . '#/album/' . $album->id;
			$type = (string)$l->t('Albums');
			$results[] = new \OC_Search_Result($name, $text, $link, $type, $container);
		}
		
		$tracks = $trackMapper->findByTitleLike($pattern, $userId);
		foreach($tracks as $track) {
			$name = $track->title;
			$link = $api->linkToRoute('music_index') . '#/track/' . $track->id;
			$type = (string)$l->t('Tracks');
			$album = $albumMapper->find($track->albumId, $userId);
			$results[] = new \OC_Search_Result($name, $text, $link, $type, $container);
		}
		return $results;
	}
}