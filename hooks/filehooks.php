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

use \OCP\Files\FileInfo;

use \OCA\Music\App\Music;

class FileHooks {

	private $filesystemRoot;

	public function __construct($filesystemRoot){
		$this->filesystemRoot = $filesystemRoot;
	}

	/**
	 * Invoke auto update of music database after file or folder deletion
	 * @param \OCP\Files\Node $node pointing to the file or folder
	 */
	public static function deleted($node){
		$app = new Music();
		$container = $app->getContainer();
		$scanner = $container->query('Scanner');

		if ($node->getType() == FileInfo::TYPE_FILE) {
			$scanner->delete($node->getId());
		} else {
			$scanner->deleteFolder($node);
		}
	}

	/**
	 * Invoke auto update of music database after file update or file creation
	 * @param \OCP\Files\Node $node pointing to the file
	 */
	public static function updated($node){
		// we are interested only about updates on files, not on folders
		if ($node->getType() == FileInfo::TYPE_FILE) {
			$app = new Music();
			$container = $app->getContainer();
			$scanner = $container->query('Scanner');
			$userId = $container->query('UserId');
			$userFolder = $container->query('UserFolder');

			// When a file is uploaded to a folder shared by link, we end up here without current user.
			// In that case, fall back to using file owner (available from Node in OC >= 9.0)
			if (!$userId) {
				$version = \OCP\Util::getVersion();
				if ($version[0] >= 9) {
					$userId = $node->getOwner()->getUID();
					$userFolder = $scanner->resolveUserFolder($userId);
				}
			}

			if ($userId) {
				$scanner->update($node, $userId, $userFolder);
			}
		}
	}

	public function register() {
		$this->filesystemRoot->listen('\OC\Files', 'postWrite', array(__CLASS__, 'updated'));
		$this->filesystemRoot->listen('\OC\Files', 'preDelete', array(__CLASS__, 'deleted'));
	}
}
