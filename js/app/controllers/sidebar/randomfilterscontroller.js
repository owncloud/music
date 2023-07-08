/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2023
 */


angular.module('Music').controller('RandomFiltersController', [
	'$scope', '$timeout', 'Restangular', 'libraryService',
	function ($scope, $timeout, Restangular, libraryService) {

		$scope.genres = libraryService.getAllGenres();

		$timeout(() => {
			$('#filter-genres').chosen();
			$('#app-sidebar #random-filters .chosen-container').css('width', ''); // purge the inline rule to let the CSS define the width
		});
	}
]);
