<?php
/**
 * Copyright (c) 2014 Leizh <leizh@free.fr>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

$application->add(new OCA\Music\Command\Scan(OC_User::getManager()));
