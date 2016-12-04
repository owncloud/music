
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


angular.module('Music').controller('PlaylistController',
	['$rootScope', '$scope', '$routeParams', 'playlistService', 'gettextCatalog', 'Restangular', '$timeout',
	function ($rootScope, $scope, $routeParams, playlistService, gettextCatalog, Restangular , $timeout) {

		$scope.playlists = [];

		$scope.newPlaylistName = null;

		// holds the state of the editor (visible or not)
		$scope.showCreateForm = false;
		// same as above, but for the playlist renaming. Holds the number of the playlist, which is currently edited
		$scope.showEditForm = null;

		// create playlist
		$scope.create = function(playlist) {
			Restangular.all('playlists').post({name: $scope.newPlaylistName}).then(function(playlist){
				$scope.playlists.push(playlist);
				$scope.newPlaylistName = null;
			});

			$scope.showCreateForm = false;
		};

		// load all playlists
		$scope.load = function() {
			Restangular.all('playlists').getList().then(function(playlists){
				$scope.playlists = playlists;
			});
		};

		// fetch playlist and its songs
		$scope.getPlaylist = function(id) {
			Restangular.one('playlists', id).get().then(function(playlist){
				$scope.currentPlaylist = playlist;
				$rootScope.currentView = 'playlist' + playlist.id;
			});
		};

		// Rename playlist
		$scope.update = function(playlist) {
			// change of the attribute happens in form
			playlist.put();

			$scope.showEditForm = false;
		};

		// Remove playlist
		$scope.remove = function(playlist) {
			playlist.remove();

			// remove the elemnt also from the AngularJS list
			$scope.playlists.splice($scope.playlists.indexOf(playlist), 1);
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

		// Remove chosen track from the list
		$scope.removeTrack = function(track) {
			$scope.currentPlaylist.all("remove").post({trackIds: track.id}).then(function() {
				// remove the element also from the JS array
				$scope.currentPlaylist.trackIds.splice($scope.currentPlaylist.trackIds.indexOf(track), 1);
			});
		};

		// Call playlistService to play all songs in the current playlist from the beginning
		$scope.playAll = function() {
			playlistService.setPlaylist($scope.currentPlaylist.trackIds);
			playlistService.publish('play');
		};

		// Play the list, starting from a specific track
		$scope.playTrack = function(track) {
			playlistService.setPlaylist($scope.currentPlaylist.trackIds, track);
			playlistService.publish('play');
		};

		if($routeParams.playlistId) {
			// load playlist in playlist route
			$scope.getPlaylist($routeParams.playlistId);
		}

		// Emitted by MainController after dropping a track/album/artist on a playlist
		$scope.$on('droppedOnPlaylist', function(event, droppedItem, playlist) {
			if ('files' in droppedItem) {
				$scope.addTrack(playlist, droppedItem);
			} else if ('tracks' in droppedItem) {
				$scope.addAlbum(playlist, droppedItem);
			} else if ('albums' in droppedItem) {
				$scope.addArtist(playlist, droppedItem);
			} else {
				console.error("Unknwon entity dropped on playlist");
			}
		});

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
			playlist.all("add").post({trackIds: trackIds.join(',')}).then(function() {
				playlist.trackIds = playlist.trackIds.concat(trackIds);
			});
		}

		// load all playlists in sidebar
		$scope.load();

}]);
