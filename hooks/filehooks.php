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

	public function __construct($filesystemRoot) {
		$this->filesystemRoot = $filesystemRoot;
	}

	/**
	 * Invoke auto update of music database after file or folder deletion
	 * @param \OCP\Files\Node $node pointing to the file or folder
	 */
	public static function deleted($node) {
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
	public static function updated($node) {
		// At least on Nextcloud 13, it sometimes happens that this hook is triggered
		// when the core creates some temporary file and trying to access the provided
		// node throws an exception, probably because the temp file is already removed
		// by the time the execution gets here. See #636.
		// Furthermore, when the core opens a file in stream mode for writing using
		// File::fopen, this hook gets triggered immediately after the opening succeeds,
		// before anything is actually written and while the file is *exlusively locked
		// because of the write mode*. See #638.
		$app = new Music();
		try {
			self::handleUpdated($node, $app);
		}
		catch (\OCP\Files\NotFoundException $e) {
			$logger = $app->getContainer()->query('Logger');
			$logger->log('FileHooks::updated triggered for a non-existing file', 'warn');
		}
		catch (\OCP\Lock\LockedException $e) {
			$logger = $app->getContainer()->query('Logger');
			$logger->log('FileHooks::updated triggered for a locked file ' . $node->getName(), 'warn');
		}
	}

	private static function handleUpdated($node, $app) {
		// we are interested only about updates on files, not on folders
		if ($node->getType() == FileInfo::TYPE_FILE) {
			$container = $app->getContainer();
			$scanner = $container->query('Scanner');
			$userId = $container->query('UserId');
			$userFolder = $container->query('UserFolder');

			// When a file is uploaded to a folder shared by link, we end up here without current user.
			// In that case, fall back to using file owner (available from Node in OC >= 9.0)
			if (empty($userId)) {
				$version = \OCP\Util::getVersion();
				if ($version[0] >= 9) {
					$userId = $node->getOwner()->getUID();
					if (!empty($userId)) {
						$userFolder = $scanner->resolveUserFolder($userId);
					}
				}
			}

			// Ignore event if we got not user or folder or the user has not yet scanned the music
			// collection. The last condition is especially to prevent problems when creating new user
			// and the default file set contains one or more audio files (see the discussion in #638).
			if (!empty($userId) && !empty($userFolder) && self::userHasMusicLib($userId, $container)) {
				$scanner->update($node, $userId, $userFolder);
			}
		}
	}

	/**
	 * Check if user has any scanned tracks in his/her music library
	 * @param string $userId
	 * @param IAppContainer $container
	 */
	private static function userHasMusicLib($userId, $container) {
		$trackBusinessLayer = $container->query('TrackBusinessLayer');
		return 0 < $trackBusinessLayer->count($userId);
	}

	public static function preRenamed($source, $target) {
		// We are interested only if the path of the folder of the file changes:
		// that could move a music file out of the scanned folder or remove a
		// cover image file from album folder.
		if ($source->getParent()->getId() != $target->getParent()->getId()) {
			self::deleted($source);
			// $target here doesn't point to an existing file, hence we need also
			// the postRenamed hook
		}
	}

	public static function postRenamed($source, $target) {
		// Renaming/moving file may
		// a) move it into the folder scanned by Music app
		// b) have influence on the display name of the track
		// Both of these cases can be handled like file addition/modification.
		self::updated($target);
	}

	public function register() {
		$this->filesystemRoot->listen('\OC\Files', 'postWrite', [__CLASS__, 'updated']);
		$this->filesystemRoot->listen('\OC\Files', 'preDelete', [__CLASS__, 'deleted']);
		$this->filesystemRoot->listen('\OC\Files', 'preRename', [__CLASS__, 'preRenamed']);
		$this->filesystemRoot->listen('\OC\Files', 'postRename', [__CLASS__, 'postRenamed']);
	}
}
