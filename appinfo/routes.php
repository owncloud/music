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

use \OCA\Music\App\Music;

$app = new Music();

$app->registerRoutes($this, array('routes' => array(
	// page
	array('name' => 'page#index', 'url' => '/', 'verb' => 'GET'),

	// log
	array('name' => 'log#log', 'url' => '/api/log', 'verb' => 'POST'),

	// api
	array('name' => 'api#collection', 'url' => '/api/collection', 'verb' => 'GET'),

	// Shiva api https://github.com/tooxie/shiva-server#resources
	array('name' => 'api#artists', 'url' => '/api/artists', 'verb' => 'GET'),
	array('name' => 'api#artist', 'url' => '/api/artist/{artistIdOrSlug}', 'verb' => 'GET'),
	// array('name' => 'api#artistShows', 'url' => '/api/artist/{artistIdOrSlug}/shows', 'verb' => 'GET'),
	array('name' => 'api#albums', 'url' => '/api/albums', 'verb' => 'GET'),
	array('name' => 'api#album', 'url' => '/api/album/{albumIdOrSlug', 'verb' => 'GET'),
	array('name' => 'api#cover', 'url' => '/api/album/{albumIdOrSlug}/cover', 'verb' => 'GET'),
	array('name' => 'api#tracks', 'url' => '/api/tracks', 'verb' => 'GET'),
	array('name' => 'api#track', 'url' => '/api/track/{trackIdOrSlug}', 'verb' => 'GET'),
	// array('name' => 'api#trackLyrics', 'url' => '/api/track/{trackIdOrSlug}/lyrics', 'verb' => 'GET'),
	array('name' => 'api#trackByFileId', 'url' => '/api/file/{fileId}', 'verb' => 'GET'),
	array('name' => 'api#download', 'url' => '/api/file/{fileId}/download', 'verb' => 'GET'),
	array('name' => 'api#scan', 'url' => '/api/scan', 'verb' => 'GET'),

	// settings
	array('name' => 'settings#userPath', 'url' => '/settings/user/path', 'verb' => 'POST'),
	array('name' => 'settings#addUserKey', 'url' => '/settings/userkey/add', 'verb' => 'POST'),
	array('name' => 'settings#removeUserKey', 'url' => '/settings/userkey/remove', 'verb' => 'POST'),

	// ampache
	array('name' => 'ampache#ampache', 'url' => '/ampache', 'verb' => 'GET'),
	array('name' => 'ampache#ampache', 'url' => '/ampache/server/xml.server.php', 'verb' => 'GET'),
	// Ampache API http://ampache.org/wiki/dev:xmlapi - POST version. Dirty fix for JustPlayer
	array('name' => 'ampache#ampache', 'url' => '/ampache', 'verb' => 'POST'),
	array('name' => 'ampache#ampache', 'url' => '/ampache/server/xml.server.php', 'verb' => 'POST')


)));
