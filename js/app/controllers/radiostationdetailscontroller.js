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
	'$scope', '$rootScope', '$timeout', 'Restangular', 'libraryService',
	function ($scope, $rootScope, $timeout, Restangular, libraryService) {

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

		$scope.$watch('station.updated', function(updated) {
			$scope.updatedDate = formatTimestamp(updated);
		});

		var initialName = null;
		var initialStreamUrl = null;

		// Enter the edit mode
		$scope.startEdit = function(targetEditor) {
			if (!$scope.editing) {
				$scope.editing = true;
				initialName = $scope.station.name;
				initialStreamUrl = $scope.station.stream_url;
				// Move the focus to the clicked input field
				$timeout(function() {
					$(targetEditor).focus();
				});
			}
		};

		// Commit the edited content
		$scope.commitEdit = function() {
			// do not allow committing if the stream URL is empty
			if ($scope.station.stream_url.length > 0) {
				// push the change to the server only if the data has actually changed
				if (initialName !== $scope.station.name || initialStreamUrl !== $scope.station.stream_url) {
					Restangular.one('radio', $scope.station.id).put(_.pick($scope.station, 'name', 'stream_url')).then(
						function (result) {
							$scope.station.updated = result.updated;
						}
					);
				}
				$scope.editing = false;
				libraryService.sortRadioStations();
				$rootScope.$emit('playlistUpdated', 'radio', /*onlyReorder=*/true);
			}
		};

		// Rollback any edited content
		$scope.cancelEdit = function() {
			$scope.station.name = initialName;
			$scope.station.stream_url = initialStreamUrl;
			$scope.editing = false;
		};
	}
]);
