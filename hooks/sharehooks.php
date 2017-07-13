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
	static public function itemUnshared($params) {
		$app = new Music();

		$scanner = $app->getContainer()->query('Scanner');
		$sharedNodeId = $params['itemSource'];
		$shareWithUser = $params['shareWith'];

		if ($params['itemType'] === 'folder') {
			$ownerHome = $scanner->resolveUserFolder($params['uidOwner']);
			$nodes = $ownerHome->getById($sharedNodeId);
			if (count($nodes) > 0) {
				$sharedFolder = $nodes[0];
				$scanner->deleteFolder($sharedFolder, $shareWithUser);
			}
		}
		else if ($params['itemType'] === 'file') {
			$scanner->delete((int)$sharedNodeId, $shareWithUser);
		}
	}

	/**
	 * Invoke auto update of music database after item gets unshared by the share recipient
	 * @param array $params contains the params of the removed share
	 */
	static public function itemUnsharedFromSelf($params) {
		// In Share 1.0 used before OC 9.0, the parameter data of this signal
		// did not contain all the needed fields, and updating our database based
		// on such signal would be basically impossible. Handle the signal only
		// if the needed fields are present.
		// See: https://github.com/owncloud/core/issues/28337
		if (array_key_exists('itemSource', $params)
			&& array_key_exists('shareWith', $params)
			&& array_key_exists('itemType', $params)
			&& array_key_exists('uidOwner', $params)) {
			self::itemUnshared($params);
		}
	}

	/**
	 * Invoke auto update of music database after item gets shared
	 * @param array $params contains the params of the added share
	 */
	static public function itemShared($params) {
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

	public function register() {
		// FIXME: this is temporarily static because core emitters are not future
		// proof, therefore legacy code in here
		\OCP\Util::connectHook('OCP\Share', 'post_unshare',         __CLASS__, 'itemUnshared');
		\OCP\Util::connectHook('OCP\Share', 'post_unshareFromSelf', __CLASS__, 'itemUnsharedFromSelf');
		\OCP\Util::connectHook('OCP\Share', 'post_shared',          __CLASS__, 'itemShared');
	}
}
