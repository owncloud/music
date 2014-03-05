
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

var Application = angular.module('Music', ['restangular', 'gettext', 'ngRoute', 'ngAnimate', 'ngTouch'])

.config(function($provide){
	//getting the current app_path and define this path as global variable "app_path"
	var parts = window.location.pathname.split('/');
	var apps_index = parts.lastIndexOf('apps');
	var app_name = parts[apps_index + 1];
	var app_prefix = parts.slice(0, apps_index + 2).join('/') + '/';
	
	$provide.constant('AppBasePath', app_prefix);
})

.config(
		['$routeProvider', '$interpolateProvider', 'RestangularProvider', '$locationProvider', 'AppBasePath',
		function ($routeProvider, $interpolateProvider, RestangularProvider, $locationProvider, AppBasePath) {
		
		$routeProvider.when(AppBasePath, {
			templateUrl: 'list.html'
		}).when(AppBasePath + 'file/:fileid', {
			templateUrl: 'list.html'
		}).when(AppBasePath + 'artist/:artistId', {
			templateUrl: 'artist-detail.html',
		}).when(AppBasePath + 'playing', {
			templateUrl: 'playing.html',
		}).otherwise({
			redirectTo: AppBasePath
		});
		
		if(window.history && window.history.pushState){
			$locationProvider.html5Mode(true);
		}
		// configure RESTAngular path
		RestangularProvider.setBaseUrl(AppBasePath + 'api');
}]).run();