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

	static private function removeSharedItem($app, $itemType, $nodeId, $owner, $removeFromUser) {
		$scanner = $app->getContainer()->query('Scanner');

		if ($itemType === 'folder') {
			$ownerHome = $scanner->resolveUserFolder($owner);
			$nodes = $ownerHome->getById($nodeId);
			if (count($nodes) > 0) {
				$sharedFolder = $nodes[0];
				$scanner->deleteFolder($sharedFolder, $removeFromUser);
			}
		}
		else if ($itemType === 'file') {
			$scanner->delete($nodeId, $removeFromUser);
		}
	}

	/**
	 * Invoke auto update of music database after item gets unshared
	 * @param array $params contains the params of the removed share
	 */
	static public function itemUnshared($params) {
		// react only on user and group shares
		$shareType = $params['shareType'];
		if ($shareType == \OCP\Share::SHARE_TYPE_USER || $shareType == \OCP\Share::SHARE_TYPE_GROUP) {
			// When a group share is removed, delete the track(s) from all users. This is suboptimal
			// because the file gets removed also from the file owner and possible unrelated sharees,
			// but at least no-one is left with dangling references to unavailable tracks.
			// The users having still access to the files are prompted to rescan the next time they
			// open the Music app and the database gets fixed.
			$removeFromUser = ($shareType == \OCP\Share::SHARE_TYPE_GROUP) ? null : $params['shareWith'];

			self::removeSharedItem(new Music(), $params['itemType'], $params['itemSource'], $params['uidOwner'], $removeFromUser);
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
			$removeFromUser = $app->getContainer()->query('UserId');

			self::removeSharedItem($app, $params['itemType'], $params['itemSource'], $params['uidOwner'], $removeFromUser);
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
