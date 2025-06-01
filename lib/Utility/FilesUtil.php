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

use OCA\Music\AppFramework\Utility\FileExistsException;
use OCP\Files\File;
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

	/**
	 * @param ?string[] $validExtensions If defined, the output file is checked to have one of these extensions.
	 * 									If the extension is not already present, the first extension of the array
	 * 									is appended to the filename.
	 * @return string Sanitized file name
	 */
	public static function sanitizeFileName(string $filename, ?array $validExtensions=null) : string {
		// File names cannot contain the '/' character on Linux
		$filename = \str_replace('/', '-', $filename);

		// separate the file extension
		$parts = \pathinfo($filename);
		$ext = $parts['extension'] ?? '';

		// enforce proper extension if defined
		if (!empty($validExtensions)) {
			$validExtensions = \array_map('mb_strtolower', $validExtensions); // normalize to lower case
			if (!\in_array(\mb_strtolower($ext), $validExtensions)) {
				// no extension or invalid extension, append the proper one and keep any original extension in $filename
				$ext = $validExtensions[0];
			} else {
				$filename = $parts['filename']; // without the extension
			}
		}

		// In owncloud/Nextcloud, the whole file name must fit 250 characters, including the file extension.
		$maxLength = 250 - \strlen($ext) - 1;
		$filename = StringUtil::truncate($filename, $maxLength);
		// Reserve another 5 characters to fit the postfix like " (xx)" on name collisions, unless there is such postfix already.
		// If there are more than 100 exports of the same playlist with overly long name, then this function will fail but we can live with that :).
		$matches = null;
		\assert($filename !== null); // for Scrutinizer, cannot be null
		if (\preg_match('/.+\(\d+\)$/', $filename, $matches) !== 1) {
			$maxLength -= 5;
			$filename = StringUtil::truncate($filename, $maxLength);
		}

		return "$filename.$ext";
	}

	/**
	 * @param Folder $targetFolder target parent folder
	 * @param string $filename target file name
	 * @param string $collisionMode action to take on file name collision,
	 *								supported values:
	 *								- 'overwrite' The existing file will be overwritten
	 *								- 'keepboth' The new file is named with a suffix to make it unique
	 *								- 'abort' (default) The operation will fail
	 * @return File the newly created file
	 * @throws FileExistsException on name conflict if $collisionMode == 'abort'
	 * @throws \OCP\Files\NotPermittedException if the user is not allowed to write to the given folder
	 */
	public static function createFile(Folder $targetFolder, string $filename, string $collisionMode) : File {
		if ($targetFolder->nodeExists($filename)) {
			switch ($collisionMode) {
				case 'overwrite':
					$targetFolder->get($filename)->delete();
					break;
				case 'keepboth':
					$filename = $targetFolder->getNonExistingName($filename);
					break;
				default:
					throw new FileExistsException(
						$targetFolder->get($filename)->getPath(),
						$targetFolder->getNonExistingName($filename)
					);
			}
		}
		return $targetFolder->newFile($filename);
	}
}