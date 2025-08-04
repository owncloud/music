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

namespace OCA\Music\Command;

use OCA\Music\AppInfo\Application;

/** @var Application $app */
$app = \OC::$server->query(Application::class);

$application->add($app->get(Scan::class));
$application->add($app->get(ResetDatabase::class));
$application->add($app->get(ResetCache::class));
$application->add($app->get(Cleanup::class));
$application->add($app->get(RegisterMimeTypes::class));
$application->add($app->get(PodcastAdd::class));
$application->add($app->get(PodcastExport::class));
$application->add($app->get(PodcastImport::class));
$application->add($app->get(PodcastReset::class));
$application->add($app->get(PodcastUpdate::class));
$application->add($app->get(PlaylistExport::class));
$application->add($app->get(PlaylistImport::class));
