<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2014
 * @copyright Pauli Järvinen 2017 - 2024
 */

namespace OCA\Music\Hooks;

use OCA\Music\AppInfo\Application;;

class ShareHooks {
	private static function removeSharedItem($app, $itemType, $nodeId, $owner, $removeFromUsers) {
		$scanner = $app->getContainer()->query('Scanner');

		if ($itemType === 'folder') {
			$ownerHome = $scanner->resolveUserFolder($owner);
			$nodes = $ownerHome->getById($nodeId);
			if (\count($nodes) > 0) {
				$sharedFolder = $nodes[0];
				$scanner->deleteFolder($sharedFolder, $removeFromUsers);
			}
		} elseif ($itemType === 'file') {
			$scanner->delete($nodeId, $removeFromUsers);
		}
	}

	/**
	 * Invoke auto update of music database after item gets unshared
	 * @param array $params contains the params of the removed share
	 */
	public static function itemUnshared(array $params) {
		$shareType = $params['shareType'];
		$app = \OC::$server->query(Application::class);

		// react only on user and group shares
		if ($shareType == \OCP\Share::SHARE_TYPE_USER) {
			$userIds = [ $params['shareWith'] ];
		} elseif ($shareType == \OCP\Share::SHARE_TYPE_GROUP) {
			$groupManager = $app->getContainer()->query('ServerContainer')->getGroupManager();
			$groupMembers = $groupManager->displayNamesInGroup($params['shareWith']);
			$userIds = \array_keys($groupMembers);
			// remove the item owner from the list of targeted users if present
			$userIds = \array_diff($userIds, [ $params['uidOwner'] ]);
		}

		if (!empty($userIds)) {
			self::removeSharedItem($app, $params['itemType'], $params['itemSource'], $params['uidOwner'], $userIds);
		}
	}

	/**
	 * Invoke auto update of music database after item gets unshared by the share recipient
	 * @param array $params contains the params of the removed share
	 */
	public static function itemUnsharedFromSelf(array $params) {
		// The share recipient may be an individual user or a group, but the item is always removed from
		// the current user alone.
		$app = \OC::$server->query(Application::class);
		$removeFromUsers = [ $app->getContainer()->query('UserId') ];

		self::removeSharedItem($app, $params['itemType'], $params['itemSource'], $params['uidOwner'], $removeFromUsers);
	}

	/**
	 * Invoke auto update of music database after item gets shared
	 * @param array $params contains the params of the added share
	 */
	public static function itemShared(array $params) {
		// Do not auto-update database when a folder is shared. The folder might contain
		// thousands of audio files, and indexing them could take minutes or hours. The sharee
		// user will be prompted to update database the next time she opens the Music app.
		// Similarly, do not auto-update on group shares.
		if ($params['itemType'] === 'file' && $params['shareType'] == \OCP\Share::SHARE_TYPE_USER) {
			$app = \OC::$server->query(Application::class);
			$container = $app->getContainer();
			$scanner = $container->query('Scanner');
			$sharerFolder = $container->query('UserFolder');
			$file = $sharerFolder->getById($params['itemSource'])[0]; // file object with sharer path
			$userId = $params['shareWith'];
			$userFolder = $scanner->resolveUserFolder($userId);
			$filePath = $userFolder->getPath() . $params['itemTarget']; // file path for sharee
			$scanner->update($file, $userId, $filePath);
		}
	}

	public function register() {
		// FIXME: this is temporarily static because core emitters are not future
		// proof, therefore legacy code in here
		\OCP\Util::connectHook('OCP\Share', 'post_unshare', __CLASS__, 'itemUnshared');
		\OCP\Util::connectHook('OCP\Share', 'post_unshareFromSelf', __CLASS__, 'itemUnsharedFromSelf');
		\OCP\Util::connectHook('OCP\Share', 'post_shared', __CLASS__, 'itemShared');
	}
}
