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

class File {

	/**
	 * Invoke auto update of music database after file deletion
	 * @param array $params contains a key value pair for the path of the file/dir
	 */
	static public function deleted($params){
		$app = new Music();

		$container = $app->getContainer();
		$container->query('Scanner')->deleteByPath($params['path']);
	}

	/**
	 * Invoke auto update of music database after file update or file creation
	 * @param array $params contains a key value pair for the path of the file/dir
	 */
	static public function updated($params){
		$app = new Music();

		$container = $app->getContainer();
		$container->query('Scanner')->updateByPath($params['path']);
	}
}
