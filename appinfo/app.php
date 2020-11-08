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
 * @copyright Pauli Järvinen 2017 - 2020
 */

namespace OCA\Music\App;

$app = new Music();

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
 * Set default content security policy to allow loading media from data URL.
 */
$policy = new \OCP\AppFramework\Http\ContentSecurityPolicy();
$policy->addAllowedMediaDomain('data:');
\OC::$server->getContentSecurityPolicyManager()->addDefaultPolicy($policy);

/**
 * register regular task
 */
\OC::$server->getJobList()->add('OC\BackgroundJob\Legacy\RegularJob', ['OCA\Music\Backgroundjob\CleanUp', 'run']);

/**
 * register hooks
 */
$c->query('FileHooks')->register();
$c->query('ShareHooks')->register();
$c->query('UserHooks')->register();

/**
 * register search provider
 */
$c->getServer()->getSearch()->registerProvider(
		'OCA\Music\Search\Provider',
		['app' => $appName, 'apps' => ['files']]
);

/**
 * Load embedded music player for Files and Sharing apps
 *
 * The nice way to do this would be
 * \OC::$server->getEventDispatcher()->addListener('OCA\Files::loadAdditionalScripts', $loadEmbeddedMusicPlayer);
 * \OC::$server->getEventDispatcher()->addListener('OCA\Files_Sharing::loadAdditionalScripts', $loadEmbeddedMusicPlayer);
 * ... but this doesn't work for shared files on ownCloud 10.0, at least. Hence, we load the scripts
 * directly if the requested URL seems to be for Files or Sharing.
 */
$loadEmbeddedMusicPlayer = function () {
	\OCA\Music\Utility\HtmlUtil::addWebpackScript('files_music_player');
	\OCA\Music\Utility\HtmlUtil::addWebpackStyle('files_music_player');
};

$request = \OC::$server->getRequest();
if (isset($request->server['REQUEST_URI'])) {
	$url = $request->server['REQUEST_URI'];
	$url = \explode('?', $url)[0]; // get rid of any query args
	$isFilesUrl = \preg_match('%/apps/files(/.*)?%', $url);
	$isShareUrl = \preg_match('%/s/.+%', $url)
		&& !\preg_match('%/apps/.*%', $url)
		&& !\preg_match('%.*/authenticate%', $url);
	if ($isFilesUrl || $isShareUrl) {
		$loadEmbeddedMusicPlayer();
	}
}
