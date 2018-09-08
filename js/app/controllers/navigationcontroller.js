/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2013
 * @copyright Pauli Järvinen 2017
 */


angular.module('Music').controller('NavigationController', [
	'$rootScope', '$scope', 'Restangular', '$timeout', 'playlistService', 'libraryService', 'gettextCatalog',
	function ($rootScope, $scope, Restangular, $timeout, playlistService, libraryService, gettextCatalog) {

		$scope.newPlaylistName = '';

		// holds the state of the editor (visible or not)
		$scope.showCreateForm = false;
		// same as above, but for the playlist renaming. Holds the number of the playlist, which is currently edited
		$scope.showEditForm = null;

		// Start creating playlist
		$scope.startCreate = function() {
			$scope.showCreateForm = true;
			// Move the focus to the input field. This has to be made asynchronously
			// because the field is not visible yet, it is shown by ng-show binding
			// later during this digest loop.
			$timeout(function() {
				$('.new-list').focus();
			});
		};

		// Commit creating playlist
		$scope.commitCreate = function(playlist) {
			if ($scope.newPlaylistName.length > 0) {
				Restangular.all('playlists').post({name: $scope.newPlaylistName}).then(function(playlist){
					libraryService.addPlaylist(playlist);
					$scope.newPlaylistName = '';
				});

				$scope.showCreateForm = false;
			}
		};

		// Start renaming playlist
		$scope.startEdit = function(playlist) {
			$scope.showEditForm = playlist.id;
			// Move the focus to the input field. This has to be made asynchronously
			// because the field does not exist yet, it is added by ng-if binding
			// later during this digest loop.
			$timeout(function() {
				$('.edit-list').focus();
			});
		};

		// Commit renaming of playlist
		$scope.commitEdit = function(playlist) {
			if (playlist.name.length > 0) {
				Restangular.one('playlists', playlist.id).put({name: playlist.name});
				$scope.showEditForm = null;
			}
		};

		// Remove playlist
		$scope.remove = function(playlist) {
			OC.dialogs.confirm(
					gettextCatalog.getString('Are you sure to remove the playlist "{{ name }}"?', { name: playlist.name }),
					gettextCatalog.getString('Remove playlist'),
					function(confirmed) {
						if (confirmed) {
							Restangular.one('playlists', playlist.id).remove();

							// remove the elemnt also from the AngularJS list
							libraryService.removePlaylist(playlist);
						}
					},
					true
				);
		};

		// Play/pause playlist
		$scope.togglePlay = function(destination, playlist) {
			if ($rootScope.playingView == destination) {
				playlistService.publish('togglePlayback');
			}
			else {
				var id = null;
				var tracks = null;
				if (destination == '#') {
					id = 'albums';
					tracks = libraryService.getTracksInAlbumOrder();
				} else if (destination == '#/alltracks') {
					id = 'alltracks';
					tracks = libraryService.getTracksInAlphaOrder();
				} else {
					id = 'playlist-' + playlist.id;
					tracks = playlist.tracks;
				}
				playlistService.setPlaylist(id, tracks);
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

		// Navigate to a view selected from the navigation bar
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

		// An item dragged and dropped on a navigation bar playlist item
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
