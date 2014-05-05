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

namespace OCA\Music\Hooks;

use \OCA\Music\App\Music;

class Share {

	/**
	 * Invoke auto update of music database after item gets unshared
	 * @param array $params contains the params of the removed share
	 */
	static public function itemUnshared($params){
		$app = new Music();

		$container = $app->getContainer();
		if ($params['itemType'] === 'folder') {
			$backend = new \OC_Share_Backend_Folder();
			foreach ($backend->getChildren($params['itemSource']) as $child) {
				$container->query('Scanner')->delete((int)$child['source'], $params['shareWith']);
			}
		} else if ($params['itemType'] === 'file') {
			$container->query('Scanner')->delete((int)$params['itemSource'], $params['shareWith']);
		}
	}

	/**
	 * Invoke auto update of music database after item gets shared
	 * @param array $params contains the params of the added share
	 */
	static public function itemShared($params){
		$app = new Music();

		$container = $app->getContainer();
		if ($params['itemType'] === 'folder') {
			$backend = new \OC_Share_Backend_Folder();
			foreach ($backend->getChildren($params['itemSource']) as $child) {
				$container->query('Scanner')->updateById((int)$child['source'], $params['shareWith']);
			}
		} else if ($params['itemType'] === 'file') {
			$container->query('Scanner')->updateById((int)$params['itemSource'], $params['shareWith']);
		}
	}
}
