<?php
/**
 * Copyright (c) 2014 Volkan Gezer <volkangezer@gmail.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */


OCP\JSON::checkLoggedIn();
OCP\JSON::checkAppEnabled('music');



// $data = json_decode(file_get_contents("php://input"));
// $plist = $data->p;
$plist = $_POST['plist'][0];
$tmpl = new OCP\Template("music", "partials/plistedit");
$tmpl->assign('new', false);
$tmpl->assign('plist', $plist);
$tmpl->printPage();
