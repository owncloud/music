/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2023, 2024
 */


angular.module('Music').controller('SmartListFiltersController', [
	'$scope', '$rootScope', '$timeout', 'libraryService', 'gettextCatalog',
	function ($scope, $rootScope, $timeout, libraryService, gettextCatalog) {

		$scope.allGenres = libraryService.getAllGenres();
		$scope.allArtists = libraryService.getAllArtists();

		// for artists and genres, the selection box can't use the smartListParams
		// directly as model as we need conversions between string and array formats
		$scope.genres = [];
		$scope.artists = [];

		$scope.$watch('smartListParams', () => {
			if ($scope.smartListParams !== null) {
				$scope.genres = $scope.smartListParams.genres?.split(',') ?? [];
				$scope.artists = $scope.smartListParams.artists?.split(',') ?? [];
			}
		});

		$scope.fieldsValid = allFieldsValid();

		$timeout(() => {
			$('#filter-genres').chosen();
			$('#filter-artists').chosen();
			const $chosenInputs = $('#app-sidebar #smartlist-filters .chosen-container');
			const $filterGenres = $('#filter-genres');
			const $filterSize = $('#filter-size');

			$chosenInputs
				.css('width', '') // purge the inline rule to let the CSS define the width
				.css('--border-input', $filterGenres.css('border')) // copy the border style from the input field
				.css('--border-radius-input', $filterGenres.css('border-radius'));

			$filterSize.trigger('focus');
			$timeout(() => {
				$chosenInputs.css('--color-input-border-hover', $filterSize.css('border-color')); // copy the border color of focused input
			});
		});

		function allFieldsValid() {
			let valid = true;
			$('#smartlist-filters input[type=number]').each((_index, elem) =>
				valid &&= elem.checkValidity()
			);
			// the size field must not be empty
			valid &&= ($scope.smartListParams.size > 0);

			return valid;
		}

		$scope.$watchGroup(['fromYear', 'toYear', 'listSize'], () => $scope.fieldsValid = allFieldsValid());

		$scope.onUpdateButton = function() {
			if ($scope.fieldsValid) {
				$scope.smartListParams.genres = $scope.genres.join(',');
				$scope.smartListParams.artists = $scope.artists.join(',');
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
