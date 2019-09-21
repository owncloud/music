<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2019
 */

namespace OCA\Music\Utility;

use \OCP\Files\Folder;
use \OCP\Files\IRootFolder;
use \OCP\IConfig;

use \OCA\Music\AppFramework\Core\Logger;

/**
 * Manage the user-specific music folder setting
 */
class UserMusicFolder {

	private $appName;
	private $configManager;
	private $rootFolder;
	private $logger;

	public function __construct(
			$appName,
			IConfig $configManager,
			IRootFolder $rootFolder,
			Logger $logger) {
		$this->appName = $appName;
		$this->configManager = $configManager;
		$this->rootFolder = $rootFolder;
		$this->logger = $logger;
	}

	/**
	 * @param string $userId
	 * @param string $path
	 */
	public function setPath($userId, $path) {
		$success = false;

		$userHome = $this->rootFolder->getUserFolder($userId);
		$element = $userHome->get($path);
		if ($element instanceof \OCP\Files\Folder) {
			if ($path[0] !== '/') {
				$path = '/' . $path;
			}
			if ($path[\strlen($path)-1] !== '/') {
				$path .= '/';
			}
			$this->configManager->setUserValue($userId, $this->appName, 'path', $path);
			$success = true;
		}

		return $success;
	}

	/**
	 * @param string $userId
	 * @return string
	 */
	public function getPath($userId) {
		$path = $this->configManager->getUserValue($userId, $this->appName, 'path');
		return $path ?: '/';
	}

	/**
	 * @param string $userId
	 * @return Folder
	 */
	public function getFolder($userId) {
		$userHome = $this->rootFolder->getUserFolder($userId);
		$path = $this->getPath($userId);
		return Util::getFolderFromRelativePath($userHome, $path);
	}
}
