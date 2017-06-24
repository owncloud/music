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

class ShareHooks {

	/**
	 * Invoke auto update of music database after item gets unshared
	 * @param array $params contains the params of the removed share
	 */
	static public function itemUnshared($params){
		$app = new Music();

		$container = $app->getContainer();
		$scanner = $container->query('Scanner');
		$sharedFileId = $params['itemSource'];
		$shareWithUser = $params['shareWith'];

		if ($params['itemType'] === 'folder') {
			$ownerHome = $container->query('UserFolder');
			$nodes = $ownerHome->getById($sharedFileId);
			if (count($nodes) > 0) {
				$sharedFolder = $nodes[0];
				$scanner->deleteFolder($sharedFolder, $shareWithUser);
			}
		}
		else if ($params['itemType'] === 'file') {
			$scanner->delete((int)$sharedFileId, $shareWithUser);
		}
	}

	/**
	 * Invoke auto update of music database after item gets shared
	 * @param array $params contains the params of the added share
	 */
	static public function itemShared($params){
		if ($params['itemType'] === 'folder') {
			// Do not auto-update database when a folder is shared. The folder might contain
			// thousands of audio files, and indexing them could take minutes or hours. The sharee
			// user will be prompted to update database the next time she opens the Music app.
		} else if ($params['itemType'] === 'file') {
			$app = new Music();
			$container = $app->getContainer();
			$scanner = $container->query('Scanner');
			$sharerFolder = $container->query('UserFolder');
			$file = $sharerFolder->getById($params['itemSource'])[0]; // file object with sharer path
			$userId = $params['shareWith'];
			$userFolder = $scanner->resolveUserFolder($userId);
			$filePath = $userFolder->getPath() . $params['itemTarget']; // file path for sharee
			$scanner->update($file, $userId, $userFolder, $filePath);
		}
	}
}
