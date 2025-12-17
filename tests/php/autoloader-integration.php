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
 * @copyright Pauli Järvinen 2020 - 2025
 */

// to execute without a host cloud, we need to create our own classloader
\spl_autoload_register(function ($className) {

	$classPath = \str_replace('\\', '/', $className) . '.php';

	if (\strpos($classPath, 'OCA/Music/Tests/Utility') === 0) {
		$path = 'tests/php/utility' . \substr($classPath, 23);
	} elseif (\strpos($classPath, 'OCA/Music') === 0) {
		$path = 'lib' . \substr($classPath, 9);
	} elseif (\strpos($classPath, 'OCP/') === 0) {
		$path = '../../lib/public' . \substr($classPath, 3);
	} elseif (\strpos($classPath, 'OC_') === 0) {
		$path = '../../lib/private/' . \substr($classPath, 3);
	} elseif (\strpos($classPath, 'OC/') === 0) {
		$path = '../../lib/private' . \substr($classPath, 2);
	} elseif (\strpos($classPath, 'Test/') === 0) {
		$path = '../../lib/private' . \substr($classPath, 4);
	} else {
		// not handled by this autoloader
	}

	if (!empty($path)) {
		$musicAppPath = __DIR__ . '/../../';
		$path = $musicAppPath . $path;

		if (\file_exists($path)) {
			require_once $path;
		}
	}
});
