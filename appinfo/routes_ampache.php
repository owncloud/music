<?php

/**
 * ownCloud - Music app
 *
 * @author Morris Jobke
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


namespace OCA\Music;

use \OCA\Music\AppFramework\App;
use \OCA\Music\DependencyInjection\DIContainer;

/**
 * Ampache API http://ampache.org/wiki/dev:xmlapi
 */

$this->create('music_ampache', '/ampache')->get()->action(
	function($params){
		App::main('AmpacheController', 'ampache', $params, new DIContainer());
	}
);

$this->create('music_ampache_alternative', '/ampache/server/xml.server.php')->get()->action(
	function($params){
		App::main('AmpacheController', 'ampache', $params, new DIContainer());
	}
);

/**
 * Ampache API http://ampache.org/wiki/dev:xmlapi - POST version. Dirty fix for JustPlayer
 */

$this->create('music_ampache_post', '/ampache')->post()->action(
	function($params){
		App::main('AmpacheController', 'ampache', $params, new DIContainer());
	}
);

$this->create('music_ampache_alternative_post', '/ampache/server/xml.server.php')->post()->action(
	function($params){
		App::main('AmpacheController', 'ampache', $params, new DIContainer());
	}
);
