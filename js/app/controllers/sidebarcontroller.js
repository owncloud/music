
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


angular.module('Music').controller('SidebarController', [
	'$rootScope', '$scope', 'Restangular', '$timeout', 'playlistService', 'libraryService',
	function ($rootScope, $scope, Restangular, $timeout, playlistService, libraryService) {

		$scope.newPlaylistName = null;

		// holds the state of the editor (visible or not)
		$scope.showCreateForm = false;
		// same as above, but for the playlist renaming. Holds the number of the playlist, which is currently edited
		$scope.showEditForm = null;

		// create playlist
		$scope.create = function(playlist) {
			Restangular.all('playlists').post({name: $scope.newPlaylistName}).then(function(playlist){
				libraryService.addPlaylist(playlist);
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
			Restangular.one('playlists', playlist.id).put({name: playlist.name});
			$scope.showEditForm = null;
		};

		// Remove playlist
		$scope.remove = function(playlist) {
			Restangular.one('playlists', playlist.id).remove();

			// remove the elemnt also from the AngularJS list
			libraryService.removePlaylist(playlist);
		};

		// Play/pause playlist
		$scope.togglePlay = function(destination, playlist) {
			if ($rootScope.playingView == destination) {
				playlistService.publish('togglePlayback');
			}
			else {
				var tracks = null;
				if (destination == '#') {
					tracks = libraryService.getTracksInAlbumOrder();
				} else if (destination == '#/alltracks') {
					tracks = libraryService.getTracksInAlphaOrder();
				} else {
					tracks = playlist.tracks;
				}
				playlistService.setPlaylist(tracks);
				playlistService.publish('play', destination);
			}
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
		var navigationDestination = null;
		$scope.navigateTo = function(destination) {
			if ($rootScope.currentView != destination) {
				$rootScope.currentView = null;
				navigationDestination = destination;
				$rootScope.loading = true;
				// Deactivate the current view. The view emits 'viewDeactivated' once that is done.
				$rootScope.$emit('deactivateView');
			}
		};

		$rootScope.$on('viewDeactivated', function() {
			// carry on with the navigation once the previous view is deactivated
			window.location.hash = navigationDestination;
		});

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
			return _.pluck(album.tracks, 'id');
		}

		function trackIdsFromArtist(artist) {
			return _.flatten(_.map(artist.albums, trackIdsFromAlbum));
		}

		function addTracks(playlist, trackIds) {
			_.forEach(trackIds, function(trackId) {
				libraryService.addToPlaylist(playlist.id, trackId);
			});

			// Update the currently playing list if necessary
			if ($rootScope.playingView == "#/playlist/" + playlist.id) {
				var newTracks = _.map(trackIds, function(trackId) {
					return { track: libraryService.getTrack(trackId) };
				});
				playlistService.onTracksAdded(newTracks);
			}

			Restangular.one('playlists', playlist.id).all("add").post({trackIds: trackIds.join(',')});
		}
	}
]);
