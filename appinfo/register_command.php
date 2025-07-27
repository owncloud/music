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
$c = $app->getContainer();

$application->add($c->query(Scan::class));
$application->add($c->query(ResetDatabase::class));
$application->add($c->query(ResetCache::class));
$application->add($c->query(Cleanup::class));
$application->add($c->query(RegisterMimeTypes::class));
$application->add($c->query(PodcastAdd::class));
$application->add($c->query(PodcastExport::class));
$application->add($c->query(PodcastImport::class));
$application->add($c->query(PodcastReset::class));
$application->add($c->query(PodcastUpdate::class));
$application->add($c->query(PlaylistExport::class));
$application->add($c->query(PlaylistImport::class));
