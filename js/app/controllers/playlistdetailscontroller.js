/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2020, 2021
 */


angular.module('Music').controller('PlaylistDetailsController', [
	'$scope', '$timeout', 'Restangular', 'libraryService',
	function ($scope, $timeout, Restangular, libraryService) {

		function resetContents() {
			$scope.playlist = null;
			$scope.totalLength = null;
			$scope.createdDate = null;
			$scope.updatedDate = null;
			$scope.editing = false;
		}
		resetContents();

		function formatTimestamp(timestamp) {
			var date = new Date(timestamp + 'Z');
			return date.toLocaleString();
		}

		$scope.$watch('contentId', function(playlistId) {
			if (!$scope.playlist || playlistId != $scope.playlist.id) {
				resetContents();
				$scope.playlist = libraryService.getPlaylist(playlistId);

				$scope.createdDate = formatTimestamp($scope.playlist.created);
				$scope.updatedDate = formatTimestamp($scope.playlist.updated);
			}
		});

		$scope.$watch('playlist.updated', function(updated) {
			$scope.updatedDate = formatTimestamp(updated);
		});

		$scope.$watchCollection('playlist.tracks', function() {
			$scope.totalLength = _.reduce($scope.playlist.tracks, function(sum, item) {
				return sum + (item.track ? item.track.length : 0); // be prepared for invalid playist entries
			}, 0);
		});

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
	}
]);
