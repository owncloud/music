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
$navConfig = array(
	'id' => $c->query('AppName'),
	'order' => 10,
	'name' => $c->query('L10N')->t('Music'),
	'href' => $c->query('URLGenerator')->linkToRoute('music.page.index'),
	'icon' => $c->query('URLGenerator')->imagePath($c->query('AppName'), 'music.svg')
);

$c->query('ServerContainer')->getNavigationManager()->add($navConfig);

/**
 * register regular task
 */

// TODO: this is temporarily static because core jobs are not public
// yet, therefore legacy code
\OCP\Backgroundjob::addRegularTask('OCA\Music\Backgroundjob\CleanUp', 'run');

/**
 * register hooks
 */

// FIXME: this is temporarily static because core emitters are not future
// proof, therefore legacy code in here
\OCP\Util::connectHook( // also called after file creation
	\OC\Files\Filesystem::CLASSNAME, \OC\Files\Filesystem::signal_post_write,
	'OCA\Music\Hooks\File', 'updated'
);
\OCP\Util::connectHook(
	\OC\Files\Filesystem::CLASSNAME, \OC\Files\Filesystem::signal_delete,
	'OCA\Music\Hooks\File', 'deleted'
);
\OCP\Util::connectHook(
	'OCP\Share', 'post_unshare',
	'OCA\Music\Hooks\Share', 'itemUnshared'
);
\OCP\Util::connectHook(
	'OCP\Share', 'post_shared',
	'OCA\Music\Hooks\Share', 'itemShared'
);

/**
 * register search provider
 */
\OC_Search::registerProvider('OCA\Music\Utility\Search');

/**
 * register settings
 */
\OCP\App::registerPersonal($c->query('AppName'), 'settings/user');

/**
 * load styles and scripts
 */
// fileactions
$c->query('API')->addScript('public/fileactions', $c->query('AppName'));
// file player for public sharing page
$c->query('API')->addScript('public/musicFilePlayer', $c->query('AppName'));
