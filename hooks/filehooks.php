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
	 * Invoke auto update of music database after file deletion
	 * @param \OCP\Files\Node $node pointing to the file
	 */
	public static function deleted($node){
		$app = new Music();

		$container = $app->getContainer();
		if ($node->getType() == FileInfo::TYPE_FILE) {
			$container->query('Scanner')->delete($node->getId(), null);
		}
		else {
			foreach ($node->getDirectoryListing() as $child) {
				FileHooks::deleted($child);
			}
		}
	}

	/**
	 * Invoke auto update of music database after file update or file creation
	 * @param \OCP\Files\Node $node pointing to the file
	 */
	public static function updated($node){
		$app = new Music();

		$container = $app->getContainer();
		$container->query('Scanner')->update($node, null);
	}

	public function register() {

		$this->filesystemRoot->listen('\OC\Files', 'postWrite', array(__CLASS__, 'updated'));
		$this->filesystemRoot->listen('\OC\Files', 'preDelete', array(__CLASS__, 'deleted'));
	}
}
