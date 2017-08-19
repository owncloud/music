<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @copyright Morris Jobke 2013, 2014
 */

namespace OCA\Music\App;

$app = new Music();

$c = $app->getContainer();
$appName = $c->query('AppName');

/**
 * add navigation
 */
\OC::$server->getNavigationManager()->add(function () use($c, $appName) {
	return [
		'id' => $appName,
		'order' => 10,
		'name' => $c->query('L10N')->t('Music'),
		'href' => $c->query('URLGenerator')->linkToRoute('music.page.index'),
		'icon' => $c->query('URLGenerator')->imagePath($appName, 'music.svg')
	];
});

/**
 * register regular task
 */
\OC::$server->getJobList()->add('OC\BackgroundJob\Legacy\RegularJob', ['OCA\Music\Backgroundjob\CleanUp', 'run']);

/**
 * register hooks
 */
$c->query('FileHooks')->register();
$c->query('ShareHooks')->register();

/**
 * register search provider
 */
\OC::$server->getSearch()->registerProvider('OCA\Music\Utility\Search');

/**
 * register settings
 */
\OCP\App::registerPersonal($appName, 'settings/user');

/**
 * load styles and scripts
 */

// Load embedded player for Files and Sharing apps
$request = \OC::$server->getRequest();

if (isset($request->server['REQUEST_URI'])) {
	$url = $request->server['REQUEST_URI'];
	if (preg_match('%/apps/files(/.*)?%', $url)	|| preg_match('%/s/.+%', $url)) {
		\OCP\Util::addScript($appName, 'vendor/soundmanager/script/soundmanager2-jsmin');
		\OCP\Util::addScript($appName, 'vendor/aurora/aurora-bundle.min');
		\OCP\Util::addScript($appName, 'vendor/javascript-detect-element-resize/jquery.resize');
		\OCP\Util::addScript($appName, 'vendor/jquery-initialize/jquery.initialize.min');
		\OCP\Util::addScript($appName, 'app/playerwrapper');
		\OCP\Util::addScript($appName, 'public/files-music-player');

		\OCP\Util::addStyle($appName, 'files-music-player');
	}
}
