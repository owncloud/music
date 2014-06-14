<?php
/**
 * Copyright (c) 2014 Volkan Gezer <volkangezer@gmail.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */


OCP\JSON::checkLoggedIn();
OCP\JSON::checkAppEnabled('music');

$tmpl = new OCP\Template("music", "partials/new-plist");
$tmpl->printPage();
