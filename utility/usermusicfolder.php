<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2019, 2020
 */

namespace OCA\Music\Utility;

use \OCP\Files\Folder;
use \OCP\Files\IRootFolder;
use \OCP\IConfig;

use \OCA\Music\AppFramework\Core\Logger;
use \OCA\Music\Utility\Util;

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
	 * @return bool
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
	 * @param string[] $paths
	 * @return bool
	 */
	public function setExcludedPaths($userId, $paths) {
		$this->configManager->setUserValue($userId, $this->appName, 'excluded_paths', \json_encode($paths));
		return true;
	}

	/**
	 * @param string $userId
	 * @return string[]
	 */
	public function getExcludedPaths($userId) {
		$paths = $this->configManager->getUserValue($userId, $this->appName, 'excluded_paths');
		if (empty($paths)) {
			return [];
		} else {
			return \json_decode($paths);
		}
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

	/**
	 * @param string $filePath
	 * @param string $userId
	 * @return boolean
	 */
	public function pathBelongsToMusicLibrary($filePath, $userId) {
		$filePath = self::normalizePath($filePath);
		$musicPath = self::normalizePath($this->getFolder($userId)->getPath());

		return Util::startsWith($filePath, $musicPath)
				&& !$this->pathIsExcluded($filePath, $musicPath, $userId);
	}

	private function pathIsExcluded($filePath, $musicPath, $userId) {
		$userRootPath = $this->rootFolder->getUserFolder($userId)->getPath();
		$excludedPaths = $this->getExcludedPaths($userId);

		foreach ($excludedPaths as $excludedPath) {
			if (Util::startsWith($excludedPath, '/')) {
				$excludedPath = $userRootPath . $excludedPath;
			} else {
				$excludedPath = $musicPath . '/' . $excludedPath;
			}
			if (self::pathMatchesPattern($filePath, $excludedPath)) {
				return true;
			}
		}

		return false;
	}

	private static function pathMatchesPattern($path, $pattern) {
		// normalize the pattern so that there is no trailing '/'
		$pattern = \rtrim($pattern, '/');

		if (\strpos($pattern, '*') === false && \strpos($pattern, '?') === false) {
			// no wildcards, begininning of the path should match the pattern exactly
			// and the next character after the matching part (if any) should be '/'
			$patternLen = \strlen($pattern);
			return Util::startsWith($path, $pattern)
				&& (\strlen($path) === $patternLen || $path[$patternLen] === '/');
		}
		else {
			// some wildcard characters in the pattern, convert the pattern into regex:
			// - '?' matches exactly one arbitrary character except the directory separator '/'
			// - '*' matches zero or more arbitrary characters except the directory separator '/'
			// - '**' matches zero or more arbitrary characters including directory separator '/'
			$pattern = \preg_quote($pattern, '/');				// escape regex meta characters
			$pattern = \str_replace('\*\*', '.*', $pattern);	// convert ** to its regex equivaleant
			$pattern = \str_replace('\*', '[^\/]*', $pattern);	// convert * to its regex equivaleant
			$pattern = \str_replace('\?', '[^\/]', $pattern);	// convert ? to its regex equivaleant
			$pattern = $pattern . '(\/.*)?$';					// after given pattern, there should be '/' or nothing
			$pattern = '/' . $pattern . '/';

			return (1 === \preg_match($pattern, $path));
		}
	}

	private static function normalizePath($path) {
		// The file system may create paths where there are two consecutive
		// path seprator characters (/). This was seen with an external local
		// folder on NC13, but may apply to other cases, too. Normalize such paths.
		return \str_replace('//', '/', $path);
	}
}
