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

	static private function removeSharedItem($app, $itemType, $nodeId, $owner, $removeFromUsers) {
		$scanner = $app->getContainer()->query('Scanner');

		if ($itemType === 'folder') {
			$ownerHome = $scanner->resolveUserFolder($owner);
			$nodes = $ownerHome->getById($nodeId);
			if (count($nodes) > 0) {
				$sharedFolder = $nodes[0];
				$scanner->deleteFolder($sharedFolder, $removeFromUsers);
			}
		}
		else if ($itemType === 'file') {
			$scanner->delete($nodeId, $removeFromUsers);
		}
	}

	/**
	 * Invoke auto update of music database after item gets unshared
	 * @param array $params contains the params of the removed share
	 */
	static public function itemUnshared($params) {
		$shareType = $params['shareType'];
		$app = new Music();

		// react only on user and group shares
		if ($shareType == \OCP\Share::SHARE_TYPE_USER) {
			$userIds = [ $params['shareWith'] ];
		}
		else if ($shareType == \OCP\Share::SHARE_TYPE_GROUP) {
			$groupManager = $app->getContainer()->query('ServerContainer')->getGroupManager();
			$groupMembers = $groupManager->displayNamesInGroup($params['shareWith']);
			$userIds = array_keys($groupMembers);
			// remove the item owner from the list of targeted users if present
			$userIds = array_diff($userIds, [ $params['uidOwner'] ]);
		}

		if (!empty($userIds)) {
			self::removeSharedItem($app, $params['itemType'], $params['itemSource'], $params['uidOwner'], $userIds);
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

			// The share recipient may be an individual user or a group, but the item is always removed from
			// the current user alone.
			$app = new Music();
			$removeFromUsers = [ $app->getContainer()->query('UserId') ];

			self::removeSharedItem($app, $params['itemType'], $params['itemSource'], $params['uidOwner'], $removeFromUsers);
		}
	}

	/**
	 * Invoke auto update of music database after item gets shared
	 * @param array $params contains the params of the added share
	 */
	static public function itemShared($params) {
		// Do not auto-update database when a folder is shared. The folder might contain
		// thousands of audio files, and indexing them could take minutes or hours. The sharee
		// user will be prompted to update database the next time she opens the Music app.
		// Similarly, do not auto-update on group shares.
		if ($params['itemType'] === 'file' && $params['shareType'] == \OCP\Share::SHARE_TYPE_USER) {
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
