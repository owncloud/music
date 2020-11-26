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
 * register regular task
 */
\OC::$server->getJobList()->add('OC\BackgroundJob\Legacy\RegularJob', ['OCA\Music\Backgroundjob\Cleanup', 'run']);

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
 * Set content security policy to allow streaming media from any external source
 */
function adjustCsp() {
	$policy = new \OCP\AppFramework\Http\ContentSecurityPolicy();
	$policy->addAllowedMediaDomain('http://*:*');
	$policy->addAllowedMediaDomain('https://*:*');
	// the next 5 rules are needed to use hls.js to stream from HLS type sources
	$policy->addAllowedMediaDomain('data:');
	$policy->addAllowedMediaDomain('blob:');
	$policy->addAllowedChildSrcDomain('blob:');
	$policy->addAllowedConnectDomain('http://*:*');
	$policy->addAllowedConnectDomain('https://*:*');
	\OC::$server->getContentSecurityPolicyManager()->addDefaultPolicy($policy);
}

/**
 * Load embedded music player for Files and Sharing apps
 *
 * The nice way to do this would be
 * \OC::$server->getEventDispatcher()->addListener('OCA\Files::loadAdditionalScripts', $loadEmbeddedMusicPlayer);
 * \OC::$server->getEventDispatcher()->addListener('OCA\Files_Sharing::loadAdditionalScripts', $loadEmbeddedMusicPlayer);
 * ... but this doesn't work for shared files on ownCloud 10.0, at least. Hence, we load the scripts
 * directly if the requested URL seems to be for Files or Sharing.
 */
function loadEmbeddedMusicPlayer() {
	\OCA\Music\Utility\HtmlUtil::addWebpackScript('files_music_player');
	\OCA\Music\Utility\HtmlUtil::addWebpackStyle('files_music_player');
}

$request = \OC::$server->getRequest();
if (isset($request->server['REQUEST_URI'])) {
	$url = $request->server['REQUEST_URI'];
	$url = \explode('?', $url)[0]; // get rid of any query args
	$isFilesUrl = \preg_match('%/apps/files(/.*)?%', $url);
	$isShareUrl = \preg_match('%/s/.+%', $url)
		&& !\preg_match('%/apps/.*%', $url)
		&& !\preg_match('%.*/authenticate%', $url);
	$isMusicUrl = \preg_match('%/apps/music(/.*)?%', $url);

	if ($isFilesUrl || $isShareUrl) {
		adjustCsp();
		loadEmbeddedMusicPlayer();
	} elseif ($isMusicUrl) {
		adjustCsp();
	}
}
