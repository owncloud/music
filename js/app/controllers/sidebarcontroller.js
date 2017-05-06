
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


angular.module('Music').controller('SidebarController',
	['$rootScope', '$scope', 'Restangular', '$timeout', 'playlistService',
	function ($rootScope, $scope, Restangular, $timeout, playlistService) {

		$scope.newPlaylistName = null;

		// holds the state of the editor (visible or not)
		$scope.showCreateForm = false;
		// same as above, but for the playlist renaming. Holds the number of the playlist, which is currently edited
		$scope.showEditForm = null;

		// create playlist
		$scope.create = function(playlist) {
			Restangular.all('playlists').post({name: $scope.newPlaylistName}).then(function(playlist){
				$scope.$parent.playlists.push(playlist);
				$scope.newPlaylistName = null;
			});

			$scope.showCreateForm = false;
		};

		// Start renaming playlist
		$scope.startEdit = function(playlist) {
			$scope.showEditForm = playlist.id;
		};

		// Commit renaming of playlist
		$scope.commitEdit = function(playlist) {
			// change of the attribute happens in form
			playlist.put();

			$scope.showEditForm = null;
		};

		// Remove playlist
		$scope.remove = function(playlist) {
			playlist.remove();

			// remove the elemnt also from the AngularJS list
			$scope.$parent.playlists.splice($scope.$parent.playlists.indexOf(playlist), 1);
		};

		// Add track to the playlist
		$scope.addTrack = function(playlist, song) {
			addTracks(playlist, [song.id]);
		};

		// Add all tracks on an album to the playlist
		$scope.addAlbum = function(playlist, album) {
			addTracks(playlist, trackIdsFromAlbum(album));
		};

		// Add all tracks on all albums by an artist to the playlist
		$scope.addArtist = function(playlist, artist) {
			addTracks(playlist, trackIdsFromArtist(artist));
		};

		// Navigate to a view selected from the sidebar
		$scope.navigateTo = function(destination) {
			if ($rootScope.currentView != destination) {
				$rootScope.loading = true;
				$timeout(function() {
					window.location.hash = destination;
				}, 100); // Firefox requires here a small delay to correctly show the loading animation
			}
		};

		// An item dragged and dropped on a sidebar playlist item
		$scope.dropOnPlaylist = function(droppedItem, playlist) {
			if ('track' in droppedItem) {
				$scope.addTrack(playlist, droppedItem.track);
			} else if ('album' in droppedItem) {
				$scope.addAlbum(playlist, droppedItem.album);
			} else if ('artist' in droppedItem) {
				$scope.addArtist(playlist, droppedItem.artist);
			} else {
				console.error("Unknwon entity dropped on playlist");
			}
		};

		$scope.allowDrop = function(playlist) {
			// Don't allow dragging a track from a playlist back to the same playlist
			return $rootScope.currentView != '#/playlist/' + playlist.id;
		};

		function trackIdsFromAlbum(album) {
			var ids = [];
			for (var i = 0, count = album.tracks.length; i < count; ++i) {
				ids.push(album.tracks[i].id);
			}
			return ids;
		}

		function trackIdsFromArtist(artist) {
			var ids = [];
			for (var i = 0, count = artist.albums.length; i < count; ++i) {
				ids = ids.concat(trackIdsFromAlbum(artist.albums[i]));
			}
			return ids;
		}

		function addTracks(playlist, trackIds) {
			playlist.all("add").post({trackIds: trackIds.join(',')}).then(function(updatedList) {
				$scope.$parent.updatePlaylist(updatedList);
				// Update the currently playing list if necessary
				if ($rootScope.playingView == "#/playlist/" + updatedList.id) {
					var newTracks = _.map(trackIds, function(trackId) {
						return { track: $scope.$parent.allTracks[trackId] };
					});
					playlistService.onTracksAdded(newTracks);
				}
			});
		}

}]);
