<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @copyright Morris Jobke 2014
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
