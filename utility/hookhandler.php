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
	 * Invoke auto update of music database after item gets unshared
	 * @param array $params contains the params of the removed share
	 */
	static public function itemUnshared($params){
		$container = new DIContainer();
		if ($params['itemType'] === 'folder') {
			$backend = new \OC_Share_Backend_Folder();
			foreach ($backend->getChildren($params['itemSource']) as $child) {
				$container['Scanner']->delete((int)$child['source'], $params['shareWith']);
			}
		} else if ($params['itemType'] === 'file') {
			$container['Scanner']->delete((int)$params['itemSource'], $params['shareWith']);
		}
	}

	/**
	 * Invoke auto update of music database after file deletion
	 * @param array $params contains a key value pair for the path of the file/dir
	 */
	static public function fileDeleted($params){
		$container = new DIContainer();
		$container['Scanner']->deleteByPath($params['path']);
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
	 * Invoke auto update of music database after item gets shared
	 * @param array $params contains the params of the added share
	 */
	static public function itemShared($params){
		$container = new DIContainer();
		if ($params['itemType'] === 'folder') {
			$backend = new \OC_Share_Backend_Folder();
			foreach ($backend->getChildren($params['itemSource']) as $child) {
				$filePath = $container['API']->getPath((int)$child['source']);
				$container['Scanner']->update($filePath, $params['shareWith']);
			}
		} else if ($params['itemType'] === 'file') {
			$filePath = $container['API']->getPath((int)$params['itemSource']);
			$container['Scanner']->update($filePath, $params['shareWith']);
		}
	}
}
