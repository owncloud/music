/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2023
 */


angular.module('Music').controller('SmartListFiltersController', [
	'$scope', '$rootScope', '$timeout', 'libraryService',
	function ($scope, $rootScope, $timeout, libraryService) {

		$scope.allGenres = libraryService.getAllGenres();
		$scope.allArtists = libraryService.getAllArtists();

		$scope.playRate = localStorage.getItem('oc_music_smartlist_play_rate') || '';
		$scope.genres = localStorage.getItem('oc_music_smartlist_genres')?.split(',') || [];
		$scope.artists = localStorage.getItem('oc_music_smartlist_artists')?.split(',') || [];
		$scope.fromYear = localStorage.getItem('oc_music_smartlist_from_year') || '';
		$scope.toYear = localStorage.getItem('oc_music_smartlist_to_year') || '';
		$scope.listSize = localStorage.getItem('oc_music_smartlist_size') || 300;

		$timeout(() => {
			$('#filter-genres').chosen();
			$('#filter-artists').chosen();
			$('#app-sidebar #smartlist-filters .chosen-container').css('width', ''); // purge the inline rule to let the CSS define the width
		});

		$scope.onUpdateButton = function() {
			localStorage.setItem('oc_music_smartlist_play_rate', $scope.playRate);
			localStorage.setItem('oc_music_smartlist_genres', $scope.genres);
			localStorage.setItem('oc_music_smartlist_artists', $scope.artists);
			localStorage.setItem('oc_music_smartlist_from_year', $scope.fromYear);
			localStorage.setItem('oc_music_smartlist_to_year', $scope.toYear);
			localStorage.setItem('oc_music_smartlist_size', $scope.listSize);

			$scope.reloadSmartList();
			// also navigate to the Smart Playlist view if not already open
			$scope.navigateTo('#smartlist');
		};

		// $rootScope listeneres must be unsubscribed manually when the control is destroyed
		let unsubFuncs = [];
		function subscribe(event, handler) {
			unsubFuncs.push( $rootScope.$on(event, handler) );
		}
		$scope.$on('$destroy', () => {
			_.each(unsubFuncs, (func) => func());
		});

		// the artists and genres may be (re)loaded after this controller has been initialized
		subscribe('collectionLoaded', () => {
			$scope.allArtists = libraryService.getAllArtists();
			$timeout(() => $('#filter-artists').trigger('chosen:updated'));
		});
		subscribe('genresLoaded', () => {
			$scope.allGenres = libraryService.getAllGenres();
			$timeout(() => $('#filter-genres').trigger('chosen:updated'));
		});
	}
]);
