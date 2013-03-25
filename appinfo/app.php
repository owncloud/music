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
 * License along with this library.  If not, see <http://www.gnu.org/
 *
 */

$l = OC_L10N::get('media');

OC::$CLASSPATH['OCA\Media\Media'] = 'media/lib/media.php';
OC::$CLASSPATH['OCA\Media\Ampache'] = 'media/lib/ampache.php';
OC::$CLASSPATH['OCA\Media\SearchProvider'] = 'media/lib/media.php';
OC::$CLASSPATH['OCA\Media\Collection'] = 'media/lib/collection.php';
OC::$CLASSPATH['OCA\Media\Scanner'] = 'media/lib/scanner.php';
OC::$CLASSPATH['OCA\Media\Extractor'] = 'media/lib/extractor.php';
OC::$CLASSPATH['OCA\Media\Extractor_GetID3'] = 'media/lib/extractor.php';
OC::$CLASSPATH['OCA\Media\Extractable'] = 'media/lib/track.php';
OC::$CLASSPATH['OCA\Media\Track'] = 'media/lib/track.php';

//we need to have the sha256 hash of passwords for ampache
OCP\Util::connectHook('OC_User', 'post_login', 'OCA\Media\Media', 'loginListener');
OCP\Util::connectHook('OC_User', 'post_setPassword', 'OCA\Media\Media', 'passwordChangeListener');

//connect to the filesystem for auto updating
OCP\Util::connectHook('OC_Filesystem', 'post_write', 'OCA\Media\Media', 'updateFile');

//listen for file deletions to clean the database if a song is deleted
OCP\Util::connectHook('OC_Filesystem', 'post_delete', 'OCA\Media\Media', 'deleteFile');

//list for file moves to update the database
OCP\Util::connectHook('OC_Filesystem', 'post_rename', 'OCA\Media\Media', 'moveFile');

OCP\App::registerPersonal('media', 'settings');

OCP\App::addNavigationEntry(array('id' => 'media_index', 'order' => 2, 'href' => OCP\Util::linkTo('media', 'index.php'), 'icon' => OCP\Util::imagePath('core', 'places/music.svg'), 'name' => $l->t('Music')));

OC_Search::registerProvider('OCA\Media\SearchProvider');
