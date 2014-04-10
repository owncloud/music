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
 * Path of the music collection
 */
$this->create('music_settings_user_path', '/settings/user/path')->post()->action(
        function($params){
                App::main('SettingController', 'userPath', $params, new DIContainer());
        }
);

/**
 * Add API key
 */
$this->create('music_settings_user_add', '/settings/userkey/add')->post()->action(
	function($params){
		App::main('SettingController', 'addUserKey', $params, new DIContainer());
	}
);

/**
 * Remove API key
 */
$this->create('music_settings_user_remove', '/settings/userkey/remove')->post()->action(
	function($params){
		App::main('SettingController', 'removeUserKey', $params, new DIContainer());
	}
);
