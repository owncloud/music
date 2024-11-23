<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2019 - 2024
 */

namespace OCA\Music\Utility;

use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IConfig;

use OCA\Music\AppFramework\Core\Logger;

/**
 * Manage the user-specific music folder setting
 */
class LibrarySettings {
	private string $appName;
	private IConfig $configManager;
	private IRootFolder $rootFolder;
	private Logger $logger;

	public function __construct(
			string $appName,
			IConfig $configManager,
			IRootFolder $rootFolder,
			Logger $logger) {
		$this->appName = $appName;
		$this->configManager = $configManager;
		$this->rootFolder = $rootFolder;
		$this->logger = $logger;
	}

	public function setScanMetadataEnabled(string $userId, bool $enabled) : void {
		$this->configManager->setUserValue($userId, $this->appName, 'scan_metadata', $enabled ? '1' : '0');
	}

	public function getScanMetadataEnabled(string $userId) : bool {
		$value = $this->configManager->getUserValue($userId, $this->appName, 'scan_metadata', 1);
		return ((int)$value > 0);
	}

	public function setIgnoredArticles(string $userId, array $articles) : void {
		$this->configManager->setUserValue($userId, $this->appName, 'ignored_articles', \json_encode($articles));
	}

	public function getIgnoredArticles(string $userId) : array {
		$default = '["The", "El", "La", "Los", "Las", "Le", "Les"]';
		$value = $this->configManager->getUserValue($userId, $this->appName, 'ignored_articles', $default);
		return \json_decode($value);
	}

	public function setPath(string $userId, string $path) : bool {
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

	public function getPath(string $userId) : string {
		$path = $this->configManager->getUserValue($userId, $this->appName, 'path');
		return $path ?: '/';
	}

	/**
	 * @param string[] $paths
	 */
	public function setExcludedPaths(string $userId, array $paths) : bool {
		$this->configManager->setUserValue($userId, $this->appName, 'excluded_paths', \json_encode($paths));
		return true;
	}

	/**
	 * @return string[]
	 */
	public function getExcludedPaths(string $userId) : array {
		$paths = $this->configManager->getUserValue($userId, $this->appName, 'excluded_paths');
		if (empty($paths)) {
			return [];
		} else {
			return \json_decode($paths);
		}
	}

	public function getFolder(string $userId) : Folder {
		$userHome = $this->rootFolder->getUserFolder($userId);
		$path = $this->getPath($userId);
		return Util::getFolderFromRelativePath($userHome, $path);
	}

	public function pathBelongsToMusicLibrary(string $filePath, string $userId) : bool {
		$filePath = self::normalizePath($filePath);
		$musicPath = self::normalizePath($this->getFolder($userId)->getPath());

		return Util::startsWith($filePath, $musicPath)
				&& !$this->pathIsExcluded($filePath, $musicPath, $userId);
	}

	private function pathIsExcluded(string $filePath, string $musicPath, string $userId) : bool {
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

	private static function pathMatchesPattern(string $path, string $pattern) : bool {
		// normalize the pattern so that there is no trailing '/'
		$pattern = \rtrim($pattern, '/');

		if (\strpos($pattern, '*') === false && \strpos($pattern, '?') === false) {
			// no wildcards, beginning of the path should match the pattern exactly
			// and the next character after the matching part (if any) should be '/'
			$patternLen = \strlen($pattern);
			return Util::startsWith($path, $pattern)
				&& (\strlen($path) === $patternLen || $path[$patternLen] === '/');
		} else {
			// some wildcard characters in the pattern, convert the pattern into regex:
			// - '?' matches exactly one arbitrary character except the directory separator '/'
			// - '*' matches zero or more arbitrary characters except the directory separator '/'
			// - '**' matches zero or more arbitrary characters including directory separator '/'
			$pattern = \preg_quote($pattern, '/');				// escape regex meta characters
			$pattern = \str_replace('\*\*', '.*', $pattern);	// convert ** to its regex equivalent
			$pattern = \str_replace('\*', '[^\/]*', $pattern);	// convert * to its regex equivalent
			$pattern = \str_replace('\?', '[^\/]', $pattern);	// convert ? to its regex equivalent
			$pattern = $pattern . '(\/.*)?$';					// after given pattern, there should be '/' or nothing
			$pattern = '/' . $pattern . '/';

			return (\preg_match($pattern, $path) === 1);
		}
	}

	private static function normalizePath(string $path) : string {
		// The file system may create paths where there are two consecutive
		// path separator characters (/). This was seen with an external local
		// folder on NC13, but may apply to other cases, too. Normalize such paths.
		return \str_replace('//', '/', $path);
	}
}
