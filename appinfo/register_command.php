<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Leizh <leizh@free.fr>
 * @copyright Leizh 2014
 */

$application->add(new OCA\Music\Command\Scan(OC_User::getManager()));
