/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2020
 */


angular.module('Music').controller('PlaylistDetailsController', [
	'$rootScope', '$scope', 'Restangular', 'gettextCatalog', 'libraryService',
	function ($rootScope, $scope, Restangular, gettextCatalog, libraryService) {

		function resetContents() {
			$scope.playlist = null;
			$scope.totalLength = null;
		}
		resetContents();

		$scope.$watch('contentId', function(playlistId) {
			if (!$scope.playlist || playlistId != $scope.playlist.id) {
				resetContents();
				$scope.playlist = libraryService.getPlaylist(playlistId);
			}
		});

		$scope.$watchCollection('playlist.tracks', function() {
			$scope.totalLength = _.reduce($scope.playlist.tracks, function(sum, item) {
				return sum + item.track.length;
			}, 0);
		});
	}
]);
