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

use OCP\AppFramework\IAppContainer;
use OCP\Files\IRootFolder;
use OCP\Files\FileInfo;
use OCP\Files\Node;

use OCA\Music\AppInfo\Application;

class FileHooks {
	private IRootFolder $filesystemRoot;

	public function __construct(IRootFolder $filesystemRoot) {
		$this->filesystemRoot = $filesystemRoot;
	}

	/**
	 * Invoke auto update of music database after file or folder deletion
	 * @param Node $node pointing to the file or folder
	 */
	public static function deleted(Node $node) : void {
		$container = self::getContainer();
		$scanner = $container->query('Scanner');

		if ($node->getType() == FileInfo::TYPE_FILE) {
			$scanner->delete($node->getId());
		} else {
			$scanner->deleteFolder($node);
		}
	}

	/**
	 * Invoke auto update of music database after file update or file creation
	 * @param Node $node pointing to the file
	 */
	public static function updated(Node $node) : void {
		// At least on Nextcloud 13, it sometimes happens that this hook is triggered
		// when the core creates some temporary file and trying to access the provided
		// node throws an exception, probably because the temp file is already removed
		// by the time the execution gets here. See #636.
		// Furthermore, when the core opens a file in stream mode for writing using
		// File::fopen, this hook gets triggered immediately after the opening succeeds,
		// before anything is actually written and while the file is *exclusively locked
		// because of the write mode*. See #638.
		$container = self::getContainer();
		try {
			self::handleUpdated($node, $container);
		} catch (\OCP\Files\NotFoundException $e) {
			$logger = $container->query('Logger');
			$logger->log('FileHooks::updated triggered for a non-existing file', 'warn');
		} catch (\OCP\Lock\LockedException $e) {
			$logger = $container->query('Logger');
			$logger->log('FileHooks::updated triggered for a locked file ' . $node->getName(), 'warn');
		}
	}

	private static function handleUpdated(Node $node, IAppContainer $container) : void {
		// we are interested only about updates on files, not on folders
		if ($node->getType() == FileInfo::TYPE_FILE) {
			$scanner = $container->query('Scanner');
			$userId = self::getUser($node, $container);

			// Ignore event if we got no user or folder or the user has not yet scanned the music
			// collection. The last condition is especially to prevent problems when creating new user
			// and the default file set contains one or more audio files (see the discussion in #638).
			if (!empty($userId) && self::userHasMusicLib($userId, $container)) {
				$scanner->update($node, $userId, $node->getPath());
			}
		}
	}

	private static function moved(Node $node) : void {
		$container = self::getContainer();
		try {
			self::handleMoved($node, $container);
		} catch (\OCP\Files\NotFoundException $e) {
			$logger = $container->query('Logger');
			$logger->log('FileHooks::moved triggered for a non-existing file', 'warn');
		} catch (\OCP\Lock\LockedException $e) {
			$logger = $container->query('Logger');
			$logger->log('FileHooks::moved triggered for a locked file ' . $node->getName(), 'warn');
		}
	}

	private static function handleMoved(Node $node, IAppContainer $container) : void {
		$scanner = $container->query('Scanner');
		$userId = self::getUser($node, $container);

		if (!empty($userId) && self::userHasMusicLib($userId, $container)) {
			if ($node->getType() == FileInfo::TYPE_FILE) {
				$scanner->fileMoved($node, $userId);
			} else {
				$scanner->folderMoved($node, $userId);
			}
		}
	}

	private static function getUser(Node $node, IAppContainer $container) : ?string {
		$userId = $container->query('UserId');

		// When a file is uploaded to a folder shared by link, we end up here without current user.
		// In that case, fall back to using file owner
		if (empty($userId)) {
			// At least some versions of NC may violate their PhpDoc and return null owner, hence we need to aid PHPStan a bit about the type.
			/** @var \OCP\IUser|null $owner */
			$owner = $node->getOwner();
			$userId = $owner ? $owner->getUID() : null;
		}

		return $userId;
	}

	private static function getContainer() : IAppContainer {
		$app = \OC::$server->query(Application::class);
		return $app->getContainer();
	}

	/**
	 * Check if user has any scanned tracks in his/her music library
	 * @param string $userId
	 * @param IAppContainer $container
	 */
	private static function userHasMusicLib(string $userId, IAppContainer $container) : bool {
		$trackBusinessLayer = $container->query('TrackBusinessLayer');
		return 0 < $trackBusinessLayer->count($userId);
	}

	public static function postRenamed(Node $source, Node $target) : void {
		// Beware: the $source describes the past state of the file and some of its functions will throw upon calling

		if ($source->getParent()->getId() != $target->getParent()->getId()) {
			self::moved($target);
		} else {
			self::updated($target);
		}
	}

	public function register() {
		$this->filesystemRoot->listen('\OC\Files', 'postWrite', [__CLASS__, 'updated']);
		$this->filesystemRoot->listen('\OC\Files', 'preDelete', [__CLASS__, 'deleted']);
		$this->filesystemRoot->listen('\OC\Files', 'postRename', [__CLASS__, 'postRenamed']);
	}
}
