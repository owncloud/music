
/**
 * ownCloud - Music app
 *
 * @author Morris Jobke
 * @copyright 2013 Morris Jobke <morris.jobke@gmail.com>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */


angular.module('Music').controller('PlaylistViewController',
	['$rootScope', '$scope', '$routeParams', 'playlistService', 'gettextCatalog', 'Restangular', '$timeout',
	function ($rootScope, $scope, $routeParams, playlistService, gettextCatalog, Restangular , $timeout) {

		$scope.tracks = [];
		$rootScope.currentView = window.location.hash;

		// Remove chosen track from the list
		$scope.removeTrack = function(trackIndex) {
			// Remove the element first from our internal array, without recreating the whole array.
			// Doing this before the HTTP request improves the perceived performance.
			$scope.tracks.splice(trackIndex, 1);

			$scope.playlist.all("remove").post({indices: trackIndex}).then(function(updatedList) {
				$scope.$parent.updatePlaylist(updatedList);
			});
		};

		// Call playlistService to play all songs in the current playlist from the beginning
		$scope.playAll = function() {
			playlistService.setPlaylist($scope.tracks);
			playlistService.publish('play');
		};

		// Play the list, starting from a specific track
		$scope.playTrack = function(track) {
			playlistService.setPlaylist($scope.tracks, track);
			playlistService.publish('play');
		};

		$scope.getDraggable = function(index) {
			return {
				track: $scope.tracks[index],
				srcIndex: index
			};
		};

		$scope.reorderDrop = function(event, draggable, dstIndex) {
			if ($scope.playlist && draggable.srcIndex != dstIndex) {
				moveArrayElement($scope.tracks, draggable.srcIndex, dstIndex);
				$scope.playlist.all("reorder").post({fromIndex: draggable.srcIndex, toIndex: dstIndex}).then(
					function(updatedList) {
						$scope.$parent.updatePlaylist(updatedList);
					}
				);
			}
		};

		$rootScope.$on('scrollToTrack', function(event, trackId) {
			if ($scope.$parent) {
				$scope.$parent.scrollToItem('track-' + trackId);
			}
		});

		// Init happens either immediately (after making the loading animation visible)
		// or once both aritsts and playlists have been loaded
		$timeout(function() {
			initViewFromRoute();
		});
		$rootScope.$on('artistsLoaded', function () {
			initViewFromRoute();
		});
		$rootScope.$on('playlistsLoaded', function () {
			initViewFromRoute();
		});

		function initViewFromRoute() {
			if ($scope.$parent.artists && $scope.$parent.playlists) {
				if ($routeParams.playlistId) {
					var playlist = findPlaylist($routeParams.playlistId);
					$scope.playlist = playlist;
					$scope.tracks = createTracksArray(playlist.trackIds);
				}
				else {
					$scope.playlist = null;
					$scope.tracks = createAllTracksArray();
				}

				$timeout(function() {
					$rootScope.loading = false;
				});
			}
		}

		function moveArrayElement(array, from, to) {
			array.splice(to, 0, array.splice(from, 1)[0]);
		}

		function findPlaylist(id) {
			return _.find($scope.$parent.playlists, function(pl) { return pl.id == id; });
		}

		function createTracksArray(trackIds) {
			var tracks = null;
			if ($scope.$parent.allTracks) {
				tracks = new Array(trackIds.length);
				for (var i = 0; i < trackIds.length; ++i) {
					tracks[i] = $scope.$parent.allTracks[trackIds[i]];
				}
			}
			return tracks;
		}

		function createAllTracksArray() {
			var tracks = null;
			if ($scope.$parent.allTracks) {
				tracks = [];
				for (var trackId in $scope.$parent.allTracks) {
					tracks.push($scope.$parent.allTracks[trackId]);
				}

				tracks = _.sortBy(tracks, function(t) { return t.title.toLowerCase(); });
				tracks = _.sortBy(tracks, function(t) { return t.artistName.toLowerCase(); });
			}
			return tracks;
		}

}]);
