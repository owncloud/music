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

$installedVersion = \OCP\Config::getAppValue('music', 'installed_version');

if (version_compare($installedVersion, '0.1.3-alpha', '<')) {
	$sqls = array(
		'DELETE FROM *PREFIX*music_artists;',
		'DELETE FROM *PREFIX*music_albums;',
		'DELETE FROM *PREFIX*music_album_artists;',
		'DELETE FROM *PREFIX*music_tracks;',
		'DELETE FROM *PREFIX*music_scanned_users;'
	);
	foreach ($sqls as $sql) {
		$query = \OCP\DB::prepare($sql);
		$query->execute();
	}
}