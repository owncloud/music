
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

// fix SVGs in IE because the scaling is a real PITA
// https://github.com/owncloud/music/issues/126
if($('html').hasClass('ie')) {
	var replaceSVGs = function() {
		replaceSVG();
		// call them periodically to keep track of possible changes in the artist view
		setTimeout(replaceSVG, 10000);
	};
	replaceSVG();
	setTimeout(replaceSVG, 1000);
	setTimeout(replaceSVGs, 5000);
}

var Application = angular.module('Music', ['restangular', 'gettext', 'ngRoute', 'ngAnimate', 'ngTouch']);

Application.config(function($provide){
	//getting the current app_path and define this path as global variable "app_path"
	var isHTML5 = window.history && window.history.pushState;

	var path = window.location.pathname;
	var match = path.match('^(.*)/index.php/apps/([^/]+)');
	var app_name = match[2];
	var app_root = match[0] + '/';
	path  = window.location.href;
	match = path.match('^(.*)/index.php/apps/[^/]+');
	var web_root = match[1];
	

	$provide.constant('isHTML5', isHTML5);
	$provide.constant('AppName', app_name);
	$provide.constant('WebRoot', web_root);
	$provide.constant('AppRoot', app_root);
}).config(
		['$routeProvider', '$interpolateProvider', 'RestangularProvider', '$locationProvider', 'isHTML5', 'AppRoot',
		function ($routeProvider, $interpolateProvider, RestangularProvider, $locationProvider, isHTML5, AppRoot) {
		
		var base_path = isHTML5 ? AppRoot : '/';
		$routeProvider.when(base_path, {
			templateUrl: 'list.html'
		}).when(base_path + 'file/:fileid', {
			templateUrl: 'list.html'
		}).when(base_path + 'artist/:artistId', {
			templateUrl: 'artist-detail.html',
		}).when(base_path + 'playing', {
			templateUrl: 'playing.html',
		}).when(base_path + 'album/:albumId', {
			templateUrl: 'album-detail.html',
		}).otherwise({
			// redirectTo: base_path
		});
		
		$locationProvider.html5Mode(isHTML5);
		// configure RESTAngular path
		RestangularProvider.setBaseUrl(AppRoot + 'api');
}]).run();