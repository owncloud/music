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
	'$scope', '$timeout', 'libraryService',
	function ($scope, $timeout, libraryService) {

		$scope.allGenres = libraryService.getAllGenres();
		// TODO: be prepared for genres to be loaded later

		$scope.playRate = localStorage.getItem('oc_music_smartlist_play_rate');
		$scope.genres = localStorage.getItem('oc_music_smartlist_genres')?.split(',') || [];
		$scope.fromYear = localStorage.getItem('oc_music_smartlist_from_year');
		$scope.toYear = localStorage.getItem('oc_music_smartlist_to_year');
		$scope.listSize = localStorage.getItem('oc_music_smartlist_size') || 300;

		$timeout(() => {
			$('#filter-genres').chosen();
			$('#app-sidebar #smartlist-filters .chosen-container').css('width', ''); // purge the inline rule to let the CSS define the width
		});

		$scope.onUpdateButton = function() {
			localStorage.setItem('oc_music_smartlist_play_rate', $scope.playRate);
			localStorage.setItem('oc_music_smartlist_genres', $scope.genres);
			localStorage.setItem('oc_music_smartlist_from_year', $scope.fromYear);
			localStorage.setItem('oc_music_smartlist_to_year', $scope.toYear);
			localStorage.setItem('oc_music_smartlist_size', $scope.listSize);

			$scope.reloadSmartList();
			// also navigate to the Smart Playlist view if not already open
			$scope.navigateTo('#smartlist');
		};
	}
]);
