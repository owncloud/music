<?php

/**
 * ownCloud - media plugin
 *
 * @author Robin Appelman
 * @copyright 2010 Robin Appelman icewind1991@gmail.com
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
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

OCP\App::checkAppEnabled('media');

$arguments = $_POST;
if (!isset($_POST['action']) and isset($_GET['action'])) {
	$arguments = $_GET;
}

foreach ($arguments as &$argument) {
	$argument = stripslashes($argument);
}
@ob_clean();

$ampache = new \OCA\Media\Ampache();

if (isset($arguments['action'])) {
	OCP\Util::writeLog('media', 'ampache ' . $arguments['action'] . ' request', OCP\Util::DEBUG);
	switch ($arguments['action']) {
		case 'songs':
			$ampache->songs($arguments);
			break;
		case 'url_to_song':
			$ampache->url_to_song($arguments);
			break;
		case 'play':
			$ampache->play($arguments);
			break;
		case 'handshake':
			$ampache->handshake($arguments);
			break;
		case 'ping':
			$ampache->ping($arguments);
			break;
		case 'artists':
			$ampache->artists($arguments);
			break;
		case 'artist_songs':
			$ampache->artist_songs($arguments);
			break;
		case 'artist_albums':
			$ampache->artist_albums($arguments);
			break;
		case 'albums':
			$ampache->albums($arguments);
			break;
		case 'album_songs':
			$ampache->album_songs($arguments);
			break;
		case 'search_songs':
			$ampache->search_songs($arguments);
			break;
		case 'song':
			$ampache->song($arguments);
			break;
	}
}
