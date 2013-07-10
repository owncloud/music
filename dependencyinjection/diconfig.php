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



namespace OCA\Music\DependencyInjection;

use \OCA\Music\Controller\ApiController;
use \OCA\Music\Controller\PageController;
use \OCA\Music\BusinessLayer\TrackBusinessLayer;
use \OCA\Music\BusinessLayer\ArtistBusinessLayer;
use \OCA\Music\BusinessLayer\AlbumBusinessLayer;
use \OCA\Music\Db\TrackMapper;
use \OCA\Music\Db\ArtistMapper;
use \OCA\Music\Db\AlbumMapper;

/**
 * Delete the following twig config to use ownClouds default templates
 */
// use this to specify the template directory
$this['TwigTemplateDirectory'] = __DIR__ . '/../templates';


/**
 * CONTROLLERS
 */
$this['ApiController'] = $this->share(function($c){
	return new ApiController($c['API'], $c['Request'],
		$c['TrackBusinessLayer'], $c['ArtistBusinessLayer'], $c['AlbumBusinessLayer']);
});

$this['PageController'] = $this->share(function($c){
	return new PageController($c['API'], $c['Request']);
});

$this['TrackMapper'] = $this->share(function($c){
	return new TrackMapper($c['API']);
});

$this['TrackBusinessLayer'] = $this->share(function($c){
	return new TrackBusinessLayer($c['TrackMapper']);
});

$this['ArtistMapper'] = $this->share(function($c){
	return new ArtistMapper($c['API']);
});

$this['ArtistBusinessLayer'] = $this->share(function($c){
	return new ArtistBusinessLayer($c['ArtistMapper']);
});

$this['AlbumMapper'] = $this->share(function($c){
	return new AlbumMapper($c['API']);
});

$this['AlbumBusinessLayer'] = $this->share(function($c){
	return new AlbumBusinessLayer($c['AlbumMapper']);
});