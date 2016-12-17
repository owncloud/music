
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

		$scope.currentTracks = [];
		$rootScope.loading = true;

		// Remove chosen track from the list
		$scope.removeTrack = function(track) {
			$scope.currentPlaylist.all("remove").post({trackIds: track.id}).then(function(updatedList) {
				$scope.$parent.updatePlaylist(updatedList);
				// remove the element also from our internal array, without recreating the whole array
				$scope.currentTracks.splice($scope.currentTracks.indexOf(track), 1);
			});
		};

		// Call playlistService to play all songs in the current playlist from the beginning
		$scope.playAll = function() {
			playlistService.setPlaylist($scope.currentTracks);
			playlistService.publish('play');
		};

		// Play the list, starting from a specific track
		$scope.playTrack = function(track) {
			playlistService.setPlaylist($scope.currentTracks, track);
			playlistService.publish('play');
		};

		// Init happens either immediately (after making the loading animation visible)
		// or once both aritsts and playlists have been loaded
		$timeout(function() {
			initViewFromRoute();
		}, 100); // Firefox requires here a small delay to correctly show the laoding animation
		$rootScope.$on('artistsLoaded', function () {
			initViewFromRoute();
		});
		$rootScope.$on('playlistsLoaded', function () {
			initViewFromRoute();
		});

		function initViewFromRoute() {
			if (!$scope.$parent.artists || !$scope.$parent.playlists) {
				return;
			}
			else if ($routeParams.playlistId) {
				var playlist = findPlaylist($routeParams.playlistId);
				$scope.currentPlaylist = playlist;
				$rootScope.currentView = 'playlist' + playlist.id;
				$scope.currentTracks = createTracksArray(playlist.trackIds);
				$timeout(function() {
					$rootScope.loading = false;
				});
			}
			else if (window.location.hash == '#/alltracks') {
				$scope.currentPlaylist = null;
				$rootScope.currentView = 'tracks';
				$scope.currentTracks = createAllTracksArray();
				$timeout(function() {
					$rootScope.loading = false;
				});
			}
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
