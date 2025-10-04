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
 * @copyright Pauli Järvinen 2017 - 2025
 */

namespace OCA\Music\Hooks;

use OCA\Music\AppInfo\Application;
use OCA\Music\Service\Scanner;
use OCP\Files\Folder;
use OCP\IGroupManager;

class ShareHooks {

	// NC32 removed the legacy constants under OCP\Share::SHARE_TYPE_* but the alternative OCP\Share\IShare::TYPE_*
	// are not present on OC (or NC < 17). The numeric values are compatible, though, so redefine them here.
	const SHARE_TYPE_USER = 0;
	const SHARE_TYPE_GROUP = 1;

	private static function removeSharedItem(
			string $itemType, int $nodeId, string $owner, array $removeFromUsers) : void {
		/** @var Scanner $scanner */
		$scanner = self::inject(Scanner::class);

		if ($itemType === 'folder') {
			$ownerHome = $scanner->resolveUserFolder($owner);
			$nodes = $ownerHome->getById($nodeId);
			$sharedFolder = $nodes[0] ?? null;
			if ($sharedFolder instanceof Folder) {
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
	public static function itemUnshared(array $params) : void {
		$shareType = $params['shareType'];

		// react only on user and group shares
		if ($shareType == self::SHARE_TYPE_USER) {
			$receivingUserIds = [ $params['shareWith'] ];
		} elseif ($shareType == self::SHARE_TYPE_GROUP) {
			$groupManager = self::inject(IGroupManager::class);
			$groupMembers = $groupManager->displayNamesInGroup($params['shareWith']);
			$receivingUserIds = \array_keys($groupMembers);
			// remove the item owner from the list of targeted users if present
			$receivingUserIds = \array_diff($receivingUserIds, [ $params['uidOwner'] ]);
		}

		if (!empty($receivingUserIds)) {
			self::removeSharedItem($params['itemType'], $params['itemSource'], $params['uidOwner'], $receivingUserIds);
		}
	}

	/**
	 * Invoke auto update of music database after item gets unshared by the share recipient
	 * @param array $params contains the params of the removed share
	 */
	public static function itemUnsharedFromSelf(array $params) : void {
		// The share recipient may be an individual user or a group, but the item is always removed from
		// the current user alone.
		$removeFromUsers = [ self::inject('userId') ];

		self::removeSharedItem($params['itemType'], $params['itemSource'], $params['uidOwner'], $removeFromUsers);
	}

	/**
	 * Invoke auto update of music database after item gets shared
	 * @param array $params contains the params of the added share
	 */
	public static function itemShared(array $params) : void {
		// Do not auto-update database when a folder is shared. The folder might contain
		// thousands of audio files, and indexing them could take minutes or hours. The sharee
		// user will be prompted to update database the next time she opens the Music app.
		// Similarly, do not auto-update on group shares.
		if ($params['itemType'] === 'file' && $params['shareType'] == self::SHARE_TYPE_USER) {
			$scanner = self::inject(Scanner::class);

			$sharingUser = self::inject('userId');
			$sharingUserFolder = $scanner->resolveUserFolder($sharingUser);
			$file = $sharingUserFolder->getById($params['itemSource'])[0]; // file object with sharing user path

			$receivingUserId = $params['shareWith'];
			$receivingUserFolder = $scanner->resolveUserFolder($receivingUserId);
			$receivingUserFilePath = $receivingUserFolder->getPath() . $params['itemTarget'];
			$scanner->update($file, $receivingUserId, $receivingUserFilePath);
		}
	}

	/**
	 * Get the dependency identified by the given name
	 * @return mixed
	 */
	private static function inject(string $id) {
		$app = \OC::$server->query(Application::class);
		return $app->get($id);
	}

	public function register() : void {
		// FIXME: this is temporarily static because core emitters are not future
		// proof, therefore legacy code in here
		\OCP\Util::connectHook('OCP\Share', 'post_unshare', __CLASS__, 'itemUnshared');
		\OCP\Util::connectHook('OCP\Share', 'post_unshareFromSelf', __CLASS__, 'itemUnsharedFromSelf');
		\OCP\Util::connectHook('OCP\Share', 'post_shared', __CLASS__, 'itemShared');
	}
}
