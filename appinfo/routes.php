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


/**
 * Webinterface
 */
$this->create('music_index', '/')->get()->action(
	function($params){
		App::main('PageController', 'index', $params, new DIContainer());
	}
);

/**
 * Log
 */
$this->create('music_log', '/api/log')->post()->action(
	function($params){
		App::main('LogController', 'log', $params, new DIContainer());
	}
);

// include external API
require_once __DIR__ . '/api.php';

// include settings routes
require_once __DIR__ . '/settings.php';

// include ampache routes
require_once __DIR__ . '/routes_ampache.php';
