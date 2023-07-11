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
	'$scope', '$rootScope', '$timeout', 'libraryService', 'gettextCatalog',
	function ($scope, $rootScope, $timeout, libraryService, gettextCatalog) {

		$scope.allGenres = libraryService.getAllGenres();
		$scope.allArtists = libraryService.getAllArtists();

		$scope.playRate = OCA.Music.Storage.get('smartlist_play_rate') || '';
		$scope.genres = OCA.Music.Storage.get('smartlist_genres')?.split(',') || [];
		$scope.artists = OCA.Music.Storage.get('smartlist_artists')?.split(',') || [];
		$scope.fromYear = parseInt(OCA.Music.Storage.get('smartlist_from_year')) || '';
		$scope.toYear = parseInt(OCA.Music.Storage.get('smartlist_to_year')) || '';
		$scope.listSize = parseInt(OCA.Music.Storage.get('smartlist_size') || 300);

		$scope.fieldsValid = allFieldsValid();

		$timeout(() => {
			$('#filter-genres').chosen();
			$('#filter-artists').chosen();
			$('#app-sidebar #smartlist-filters .chosen-container').css('width', ''); // purge the inline rule to let the CSS define the width
		});

		function allFieldsValid() {
			let valid = true;
			$('#smartlist-filters input[type=number]').each((_index, elem) =>
				valid &&= elem.checkValidity()
			);
			// the size field must not be empty
			valid &&= ($('#filters-size').val().length > 0);

			return valid;
		}

		$scope.$watchGroup(['fromYear', 'toYear', 'listSize'], () => $scope.fieldsValid = allFieldsValid());

		$scope.onUpdateButton = function() {
			if ($scope.fieldsValid) {
				OCA.Music.Storage.set('smartlist_play_rate', $scope.playRate);
				OCA.Music.Storage.set('smartlist_genres', $scope.genres);
				OCA.Music.Storage.set('smartlist_artists', $scope.artists);
				OCA.Music.Storage.set('smartlist_from_year', $scope.fromYear || '');
				OCA.Music.Storage.set('smartlist_to_year', $scope.toYear || '');
				OCA.Music.Storage.set('smartlist_size', $scope.listSize || '');

				$scope.reloadSmartList();
				// also navigate to the Smart Playlist view if not already open
				$scope.navigateTo('#smartlist');
			} else {
				OC.Notification.showTemporary(gettextCatalog.getString('Check the filter values'));
			}
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
