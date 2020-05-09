/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <morris.jobke@gmail.com>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2013
 * @copyright Pauli Järvinen 2016 - 2020
 */

// fix SVGs in IE because the scaling is a real PITA
// https://github.com/owncloud/music/issues/126
if ($('html').hasClass('ie')) {
	var replaceSVGs = function() {
		replaceSVG();
		// call them periodically to keep track of possible changes in the artist view
		setTimeout(replaceSVG, 10000);
	};
	replaceSVG();
	setTimeout(replaceSVG, 1000);
	setTimeout(replaceSVGs, 5000);
}

angular.module('Music', ['restangular', 'duScroll', 'gettext', 'ngRoute', 'ang-drag-drop'])
	.config(['RestangularProvider', '$routeProvider', '$locationProvider', '$compileProvider',
		function (RestangularProvider, $routeProvider, $locationProvider, $compileProvider) {

			// disable debug info for performance gains
			$compileProvider.debugInfoEnabled(false);

			// configure RESTAngular path
			RestangularProvider.setBaseUrl('api');

			var albumsControllerConfig = {
				controller:'AlbumsViewController',
				templateUrl:'albumsview.html'
			};

			var playlistControllerConfig = {
				controller:'PlaylistViewController',
				templateUrl:'playlistview.html'
			};

			var allTracksControllerConfig = {
				controller:'AllTracksViewController',
				templateUrl:'alltracksview.html'
			};

			var foldersControllerConfig = {
				controller:'FoldersViewController',
				templateUrl:'foldersview.html'
			};

			var genresControllerConfig = {
				controller:'GenresViewController',
				templateUrl:'genresview.html'
			};

			var settingsControllerConfig = {
				controller:'SettingsViewController',
				templateUrl:'settingsview.html'
			};

			/**
			 * @see https://stackoverflow.com/questions/38455077/angular-force-an-undesired-exclamation-mark-in-url/41223197#41223197
			 */
			$locationProvider.hashPrefix('');

			$routeProvider
				.when('/',                     albumsControllerConfig)
				.when('/artist/:id',           albumsControllerConfig)
				.when('/album/:id',            albumsControllerConfig)
				.when('/track/:id',            albumsControllerConfig)
				.when('/file/:id',             albumsControllerConfig)
				.when('/playlist/:playlistId', playlistControllerConfig)
				.when('/alltracks',            allTracksControllerConfig)
				.when('/folders',              foldersControllerConfig)
				.when('/genres',               genresControllerConfig)
				.when('/settings',             settingsControllerConfig);
		}
	])
	.run(['Token', 'Restangular',
		function(Token, Restangular) {
			// add CSRF token
			Restangular.setDefaultHeaders({requesttoken: Token});
		}
	]);
