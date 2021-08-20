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
 * @copyright Pauli Järvinen 2017 - 2021
 */

namespace OCA\Music\App;

use \OCP\AppFramework\IAppContainer;

$app = \OC::$server->query(Music::class);

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
 * register regular tasks
 */
\OC::$server->getJobList()->add('OC\BackgroundJob\Legacy\RegularJob', ['OCA\Music\Backgroundjob\Cleanup', 'run']);
\OC::$server->getJobList()->add('OC\BackgroundJob\Legacy\RegularJob', ['OCA\Music\Backgroundjob\PodcastUpdateCheck', 'run']);

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
 * Set content security policy to allow streaming media from the configured external sources
 */
function adjustCsp(IAppContainer $container) {
	/** @var \OCP\IConfig $config */
	$config = $container->query('Config');
	$radioSources = $config->getSystemValue('music.allowed_radio_src', ['http://*:*', 'https://*:*']);
	$radioHlsSources = $config->getSystemValue('music.allowed_radio_hls_src', []);

	if (\is_string($radioSources)) {
		$radioSources = [$radioSources];
	}
	if (\is_string($radioHlsSources)) {
		$radioHlsSources = [$radioHlsSources];
	}

	if (!empty($radioSources) || !empty($radioHlsSources)) {
		$policy = new \OCP\AppFramework\Http\ContentSecurityPolicy();

		foreach ($radioSources as $source) {
			$policy->addAllowedMediaDomain($source);
		}

		foreach ($radioHlsSources as $source) {
			$policy->addAllowedConnectDomain($source);
		}

		// Also the media sources data: and blob: are needed if there are any allowed HLS sources
		if (!empty($radioHlsSources)) {
			$policy->addAllowedMediaDomain('data:');
			$policy->addAllowedMediaDomain('blob:');
		}

		// Allow loading (podcast cover) images from external sources
		$policy->addAllowedImageDomain('http://*:*');
		$policy->addAllowedImageDomain('https://*:*');

		$container->getServer()->getContentSecurityPolicyManager()->addDefaultPolicy($policy);
	}
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

	if ($isFilesUrl) {
		adjustCsp($c);
		loadEmbeddedMusicPlayer();
	} elseif ($isShareUrl) {
		loadEmbeddedMusicPlayer();
	} elseif ($isMusicUrl) {
		adjustCsp($c);
	}
}
