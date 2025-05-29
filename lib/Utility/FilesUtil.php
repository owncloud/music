<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2025
 */

namespace OCA\Music\Utility;

use OCP\Files\Folder;

/**
 * Miscellaneous static utility functions for working with the cloud file system
 */
class FilesUtil {

	/**
	 * Get a Folder object using a parent Folder object and a relative path
	 */
	public static function getFolderFromRelativePath(Folder $parentFolder, string $relativePath) : Folder {
		if ($relativePath !== '/' && $relativePath !== '') {
			$node = $parentFolder->get($relativePath);
			if ($node instanceof Folder) {
				return $node;
			} else {
				throw new \InvalidArgumentException('Path points to a file while folder expected');
			}
		} else {
			return $parentFolder;
		}
	}

	/**
	 * Create relative path from the given working dir (CWD) to the given target path
	 * @param string $cwdPath Absolute CWD path
	 * @param string $targetPath Absolute target path
	 */
	public static function relativePath(string $cwdPath, string $targetPath) : string {
		$cwdParts = \explode('/', $cwdPath);
		$targetParts = \explode('/', $targetPath);

		// remove the common prefix of the paths
		while (\count($cwdParts) > 0 && \count($targetParts) > 0 && $cwdParts[0] === $targetParts[0]) {
			\array_shift($cwdParts);
			\array_shift($targetParts);
		}

		// prepend up-navigation from CWD to the closest common parent folder with the target
		for ($i = 0, $count = \count($cwdParts); $i < $count; ++$i) {
			\array_unshift($targetParts, '..');
		}

		return \implode('/', $targetParts);
	}

	/**
	 * Given a current working directory path (CWD) and a relative path (possibly containing '..' parts),
	 * form an absolute path matching the relative path. This is a reverse operation for FilesUtil::relativePath().
	 */
	public static function resolveRelativePath(string $cwdPath, string $relativePath) : string {
		$cwdParts = \explode('/', $cwdPath);
		$relativeParts = \explode('/', $relativePath);

		// get rid of the trailing empty part of CWD which appears when CWD has a trailing '/'
		if ($cwdParts[\count($cwdParts)-1] === '') {
			\array_pop($cwdParts);
		}

		foreach ($relativeParts as $part) {
			if ($part === '..') {
				\array_pop($cwdParts);
			} else {
				\array_push($cwdParts, $part);
			}
		}

		return \implode('/', $cwdParts);
	}
}