<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Leizh <leizh@free.fr>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @copyright Leizh 2014
 * @copyright Morris Jobke 2014
 */

use \OCA\Music\App\Music;

$app = new Music();
$c = $app->getContainer();
$userManager = $c->getServer()->getUserManager();
$scanner = $c->query('Scanner');
$rootFolder = $c->query('RootFolder');
$db = $c->query('Db');

$application->add(new OCA\Music\Command\Scan($userManager, $scanner, $rootFolder));
$application->add(new OCA\Music\Command\ResetDatabase($db));
