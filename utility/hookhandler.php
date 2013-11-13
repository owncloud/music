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

namespace OCA\Music\Utility;

use \OCA\Music\DependencyInjection\DIContainer;

class HookHandler {

	/**
	 * Invoke auto update of music database after file deletion
	 * @param array $params contains a key value pair for the path of the file/dir
	 */
	static public function fileDeleted($params){
		$container = new DIContainer();
		$container['Scanner']->delete($params['path']);
	}

	/**
	 * Invoke auto update of music database after file update or file creation
	 * @param array $params contains a key value pair for the path of the file/dir
	 */
	static public function fileUpdated($params){
		$container = new DIContainer();
		$container['Scanner']->update($params['path']);
	}

	/**
	 * Invoke auto update of music database after file update or file creation
	 * @param array $params contains a key value pair for user and password
	 */
	static public function login($params){
		$container = new DIContainer();
		$api = $container['API'];
		// check if ampache is enabled
		if($api->getAppValue('ampacheEnabled') !== '') {
			// check if user has enabled ampache
			if($container['AmpacheUserStatusMapper']->isAmpacheUser($params['uid'])) {
				$container['AmpacheUserMapper']->updatePassphrase($params['uid'], $params['password']);
			}
		}
	}
}
