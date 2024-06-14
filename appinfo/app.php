<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2013, 2014
 * @copyright Pauli Järvinen 2017 - 2024
 */

/**
 * Bootstrapping for ownCloud. The relesea.sh script will remove this file from
 * the Nextcloud releases. See https://github.com/owncloud/music/issues/1043.
 */

namespace OCA\Music\AppInfo;

$app = \OC::$server->query(Application::class);
$app->boot(null);

$c = $app->getContainer();
$appName = $c->query('AppName');

/**
 * add navigation
 */
\OC::$server->getNavigationManager()->add(function () use ($c, $appName) {
	return [
		'id' => $appName,
		'order' => 10,
		'name' => $c->query('L10N')->t('Music'),
		'href' => $c->query('URLGenerator')->linkToRoute('music.page.index'),
		'icon' => \OCA\Music\Utility\HtmlUtil::getSvgPath('music')
	];
});

/**
 * register search provider
 */
$c->getServer()->getSearch()->registerProvider(
		'OCA\Music\Search\Provider',
		['app' => $appName, 'apps' => ['files']]
);
