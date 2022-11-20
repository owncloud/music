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
 * @copyright Pauli Järvinen 2020, 2021
 */

// to execute without owncloud, we need to create our own classloader
\spl_autoload_register(function ($className) {

	$classPath = \str_replace('\\', '/', $className) . '.php';

	if (\strpos($classPath, 'OCA/Music/Tests/Utility') === 0) {
		$path = 'tests/php/utility' . \substr($classPath, 23);
	} elseif (\strpos($classPath, 'OCA/Music') === 0) {
		$path = 'lib' . \substr($classPath, 9);
	} elseif (\strpos($classPath, 'OCP/') === 0) {
		$path = 'vendor/nextcloud/ocp/' . $classPath;
	} else {
		$path = 'stubs/' . $classPath;
	}

	$musicAppPath = __DIR__ . '/../../';
	$path = $musicAppPath . $path;

	if (\file_exists($path)) {
		require_once $path;
	}
});
