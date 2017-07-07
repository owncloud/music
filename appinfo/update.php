<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2017
 */

/*
 * This update.php file is the legacy way of running the migration code on
 * application version update. Starting from OC 9.1.0, the migration logic
 * should be wrapped in a class implementing OCP\Migration\IRepairStep and 
 * registered in info.xml, and the use of update.php is deprecated.
 * 
 * This file is just a thin wrapper for the new migration mechanism and needed
 * to support OC versions older than 9.1.0. This should be removed once support
 * for the old server versions is dropped.
 */

$migration = new OCA\Music\Migration\PreMigration();
$migration->run(null);
