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

/**
 * add navigation
 */
\OC::$server->getNavigationManager()->add(function () use($c) {
	return [
		'id' => $c->query('AppName'),
		'order' => 10,
		'name' => $c->query('L10N')->t('Music'),
		'href' => $c->query('URLGenerator')->linkToRoute('music.page.index'),
		'icon' => $c->query('URLGenerator')->imagePath($c->query('AppName'), 'music.svg')
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
\OCP\App::registerPersonal($c->query('AppName'), 'settings/user');

/**
 * load styles and scripts
 */
// fileactions
\OCP\Util::addScript($c->query('AppName'), 'public/fileactions');
// file player for public sharing page
\OCP\Util::addScript($c->query('AppName'), 'public/musicFilePlayer');
