<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Leizh <leizh@free.fr>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Leizh 2014
 * @copyright Morris Jobke 2014
 * @copyright Pauli Järvinen 2017 - 2025
 */

use OCA\Music\AppInfo\Application;
use OCA\Music\BusinessLayer\PlaylistBusinessLayer;
use OCA\Music\BusinessLayer\PodcastChannelBusinessLayer;
use OCA\Music\BusinessLayer\PodcastEpisodeBusinessLayer;
use OCA\Music\Db\Cache;
use OCA\Music\Db\Maintenance;
use OCA\Music\Service\PlaylistFileService;
use OCA\Music\Service\PodcastService;
use OCA\Music\Service\Scanner;
use OCP\Files\IMimeTypeLoader;
use OCP\Files\IRootFolder;
use OCP\IGroupManager;
use OCP\IUserManager;

$app = \OC::$server->query(Application::class);
$c = $app->getContainer();

$application->add(new OCA\Music\Command\Scan(
		$c->query(IUserManager::class),
		$c->query(IGroupManager::class),
		$c->query(Scanner::class)
));
$application->add(new OCA\Music\Command\ResetDatabase(
		$c->query(IUserManager::class),
		$c->query(IGroupManager::class),
		$c->query(Maintenance::class)
));
$application->add(new OCA\Music\Command\ResetCache(
		$c->query(IUserManager::class),
		$c->query(IGroupManager::class),
		$c->query(Cache::class)
));
$application->add(new OCA\Music\Command\Cleanup(
		$c->query(Maintenance::class)
));
$application->add(new OCA\Music\Command\RegisterMimeTypes(
		$c->query(IMimeTypeLoader::class)
));
$application->add(new OCA\Music\Command\PodcastAdd(
		$c->query(IUserManager::class),
		$c->query(IGroupManager::class),
		$c->query(PodcastChannelBusinessLayer::class),
		$c->query(PodcastEpisodeBusinessLayer::class)
));
$application->add(new OCA\Music\Command\PodcastExport(
		$c->query(IUserManager::class),
		$c->query(IGroupManager::class),
		$c->query(IRootFolder::class),
		$c->query(PodcastService::class)
));
$application->add(new OCA\Music\Command\PodcastImport(
		$c->query(IUserManager::class),
		$c->query(IGroupManager::class),
		$c->query(IRootFolder::class),
		$c->query(PodcastService::class)
));
$application->add(new OCA\Music\Command\PodcastReset(
		$c->query(IUserManager::class),
		$c->query(IGroupManager::class),
		$c->query(PodcastService::class)
));
$application->add(new OCA\Music\Command\PodcastUpdate(
		$c->query(IUserManager::class),
		$c->query(IGroupManager::class),
		$c->query(PodcastService::class)
));
$application->add(new OCA\Music\Command\PlaylistExport(
		$c->query(IUserManager::class),
		$c->query(IGroupManager::class),
		$c->query(IRootFolder::class),
		$c->query(PlaylistBusinessLayer::class),
		$c->query(PlaylistFileService::class)
));
$application->add(new OCA\Music\Command\PlaylistImport(
		$c->query(IUserManager::class),
		$c->query(IGroupManager::class),
		$c->query(IRootFolder::class),
		$c->query(PlaylistBusinessLayer::class),
		$c->query(PlaylistFileService::class)
));
