/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2021
 */


angular.module('Music').controller('RadioStationDetailsController', [
	'$scope', '$timeout', 'Restangular', 'libraryService',
	function ($scope, $timeout, Restangular, libraryService) {

		function resetContents() {
			$scope.station = null;
			$scope.createdDate = null;
			$scope.updatedDate = null;
			$scope.editing = false;
		}
		resetContents();

		function formatTimestamp(timestamp) {
			var date = new Date(timestamp + 'Z');
			return date.toLocaleString();
		}

		$scope.$watch('contentId', function(stationId) {
			if (!$scope.station || stationId != $scope.station.id) {
				resetContents();
				$scope.station = libraryService.getRadioStation(stationId);

				$scope.createdDate = formatTimestamp($scope.station.created);
				$scope.updatedDate = formatTimestamp($scope.station.updated);
			}
		});

		/* TODO: Editing support; the following commented code is from playlist details but the editing here shall work a bit differently

		var initialComment = null;

		// Start editing the comment
		$scope.startEdit = function() {
			$scope.editing = true;
			initialComment = $scope.playlist.comment;
			// Move the focus to the input field
			$timeout(function() {
				$('#app-sidebar dd textarea').focus();
			});
		};

		// Commit editing the comment
		$scope.commitEdit = function() {
			// push the change to the server only if the comment has actually changed
			if (initialComment !== $scope.playlist.comment) {
				Restangular.one('playlists', $scope.playlist.id).put({comment: $scope.playlist.comment});
			}
			$scope.editing = false;
		};

		// Commit editing when user clicks outside the textarea
		$('#app-sidebar dd textarea').blur(function() {
			$timeout($scope.commitEdit);
		});
		*/
	}
]);
