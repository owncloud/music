<?php

/**
 * ownCloud - Music app
 *
 * @author Morris Jobke
 * @copyright 2013 Morris Jobke <morris.jobke@gmail.com>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

// TODO move to AppFramework style

namespace OCA\Music;

use \OCA\Music\DependencyInjection\DIContainer;

$c = new DIContainer();

$c['API']->addScript('public/settings-user');
$c['API']->addStyle('settings-user');

if(version_compare(join('.', $c['API']->getVersion()), '6.0.3', '<')){
	$c['API']->addScript('public/stable5-fixes');
}
if(version_compare(join('.', $c['API']->getVersion()), '6.0.0', '<')){
	$c['API']->addStyle('settings-user-stable5-fixes');
}

$tmpl = new \OCP\Template($c['API']->getAppName(), 'settings-user');

$tmpl->assign('path', $c['API']->getUserValue('path'));

$tmpl->assign('ampacheKeys', $c['AmpacheUserMapper']->getAll($c['API']->getUserId()));

return $tmpl->fetchPage();
