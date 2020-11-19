<?php
/**
 * ownCloud - music
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2014
 * @copyright Pauli Järvinen 2020
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// to execute without owncloud, we need to create our own classloader
\spl_autoload_register(function ($className) {
	if (\strpos($className, 'OCA\\Music') === 0) {
		$path = \str_replace('\\', '/', \substr($className, 9)) . '.php';
		$relPath = __DIR__ . '/../../lib' . $path;
		if (\file_exists($relPath)) {
			require_once $relPath; // don't support lower-case names as the cloud doesn't support them here, either
		}
	} elseif (\strpos($className, 'OCP\\') === 0) {
		$path = \str_replace('\\', '/', \substr($className, 3)) . '.php';
		$relPath = __DIR__ . '/../../../../lib/public' . $path;

		if (\file_exists($relPath)) {
			require_once $relPath;
		} elseif (\file_exists(\strtolower($relPath))) {
			require_once \strtolower($relPath);
		}
	} elseif (\strpos($className, 'OC_') === 0) {
		$path = \str_replace('_', '/', \substr($className, 3)) . '.php';
		$relPath = __DIR__ . '/../../../../lib/private/legacy/' . $path;
		$alterRelPath = __DIR__ . '/../../../../lib/private/' . $path;

		if (\file_exists($relPath)) {
			require_once $relPath;
		} elseif (\file_exists(\strtolower($relPath))) {
			require_once \strtolower($relPath);
		} elseif (\file_exists($alterRelPath)) {
			require_once $alterRelPath;
		} elseif (\file_exists(\strtolower($alterRelPath))) {
			require_once \strtolower($alterRelPath);
		}
	} elseif (\strpos($className, 'OC\\') === 0) {
		$path = \str_replace('\\', '/', \substr($className, 2)) . '.php';
		$relPath = __DIR__ . '/../../../../lib/private' . $path;

		if (\file_exists($relPath)) {
			require_once $relPath;
		} elseif (\file_exists(\strtolower($relPath))) {
			require_once \strtolower($relPath);
		}
	} elseif (\strpos($className, 'Test\\') === 0) {
		$path = \str_replace('\\', '/', \substr($className, 4)) . '.php';
		echo $path;
		$relPath = __DIR__ . '/../../../../tests/lib' . $path;
		if (\file_exists($relPath)) {
			require_once $relPath;
		} elseif (\file_exists(\strtolower($relPath))) {
			require_once \strtolower($relPath);
		}
	}
});
