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
			$sharedFolderNodes = $ownerHome->getById($sharedFileId);
			if (count($sharedFolderNodes) > 0) {
				$sharedFolder = $sharedFolderNodes[0];
				$audioFiles = array_merge(
					$sharedFolder->searchByMime('audio'),
					$sharedFolder->searchByMime('application/ogg')
				);
				foreach ($audioFiles as $child) {
					$scanner->delete($child->getId(), $shareWithUser);
				}
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
		$app = new Music();

		$container = $app->getContainer();
		if ($params['itemType'] === 'folder') {
			// Do not auto-update database when a folder is shared. The folder might contain
			// thousands of audio files, and indexing them could take minutes or hours.
			/*
			$backend = new \OC_Share_Backend_Folder();
			foreach ($backend->getChildren($params['itemSource']) as $child) {
				$container->query('Scanner')->updateById((int)$child['source'], $params['shareWith']);
			}*/
		} else if ($params['itemType'] === 'file') {
			$container->query('Scanner')->updateById((int)$params['itemSource'], $params['shareWith']);
		}
	}
}
