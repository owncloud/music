<?php

/**
 * ownCloud - Music app
 *
 * @author Morris Jobke
 * @copyright 2013 Morris Jobke <morris.jobke@gmail.com>
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


namespace OCA\Music;

use \OCA\Music\AppFramework\App;
use \OCA\Music\DependencyInjection\DIContainer;


$this->create('music_collection', '/api/collection')->get()->action(
		function($params){
			App::main('ApiController', 'collection', $params, new DIContainer());
		}
);

/**
 * Shiva api https://github.com/tooxie/shiva-server#resources
 */

/**
 * Artist(s)
 */
$this->create('music_artists', '/api/artists')->get()->action(
	function($params){
		App::main('ApiController', 'artists', $params, new DIContainer());
	}
);
$this->create('music_artist', '/api/artist/{artistIdOrSlug}')->get()->action(
	function($params){
		App::main('ApiController', 'artist', $params, new DIContainer());
	}
);
/*$this->create('music_artist_shows', '/api/artist/{id}/shows')->get()->action(
	function($params){
		App::main('ApiController', 'artist-shows', $params, new DIContainer());
	}
);*/

/**
 * Album(s)
 */
$this->create('music_albums', '/api/albums')->get()->action(
	function($params){
		App::main('ApiController', 'albums', $params, new DIContainer());
	}
);
$this->create('music_album', '/api/album/{albumIdOrSlug}')->get()->action(
	function($params){
		App::main('ApiController', 'album', $params, new DIContainer());
	}
);

/**
 * Track(s)
 */
$this->create('music_tracks', '/api/tracks')->get()->action(
	function($params){
		App::main('ApiController', 'tracks', $params, new DIContainer());
	}
);
$this->create('music_track', '/api/track/{trackIdOrSlug}')->get()->action(
	function($params){
		App::main('ApiController', 'track', $params, new DIContainer());
	}
);
$this->create('music_file', '/api/file/{fileId}')->get()->action(
	function($params){
		App::main('ApiController', 'trackByFileId', $params, new DIContainer());
	}
);
/*$this->create('music_track_shows', '/api/track/{id}/shows')->get()->action(
	function($params){
		App::main('ApiController', 'track-lyrics', $params, new DIContainer());
	}
);*/
