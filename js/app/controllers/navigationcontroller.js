/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2013
 * @copyright Pauli Järvinen 2017 - 2020
 */


angular.module('Music').controller('NavigationController', [
	'$rootScope', '$scope', '$document', 'Restangular', '$timeout', 'playlistService', 'libraryService', 'gettextCatalog',
	function ($rootScope, $scope, $document, Restangular, $timeout, playlistService, libraryService, gettextCatalog) {

		$rootScope.loading = true;

		$scope.newPlaylistName = '';
		$scope.popupShownForPlaylist = null;

		// holds the state of the editor (visible or not)
		$scope.showCreateForm = false;
		// same as above, but for the playlist renaming. Holds the number of the playlist, which is currently edited
		$scope.showEditForm = null;

		// hide 'more' popup menu of a playlist when user clicks anywhere on the page
		$document.click(function (event) {
			$timeout(function() {
				$scope.popupShownForPlaylist = null;
			});
		});

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
		$scope.commitCreate = function() {
			if ($scope.newPlaylistName.length > 0) {
				Restangular.all('playlists').post({name: $scope.newPlaylistName}).then(function(playlist){
					libraryService.addPlaylist(playlist);
					$scope.newPlaylistName = '';
				});

				$scope.showCreateForm = false;
			}
		};

		// Show/hide the more actions menu on a playlist
		$scope.onPlaylistMoreButton = function(playlist) {
			if ($scope.popupShownForPlaylist == playlist) {
				$scope.popupShownForPlaylist = null;
			} else {
				$scope.popupShownForPlaylist = playlist;
			}
		};

		$scope.showDetails = function(playlist) {
			$rootScope.$emit('showPlaylistDetails', playlist.id);
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

		// Export playlist to file
		$scope.exportToFile = function(playlist) {

			var onFolderSelected = null; // defined later below

			var onConflict = function(path) {
				OC.dialogs.confirm(
					gettextCatalog.getString('The folder already has a file named "{{ filename }}". Select "Yes" to overwrite it.'+
											' Select "No" to export the list with another name. Close the dialog to cancel.',
											{ filename: playlist.name + '.m3u8' }),
					gettextCatalog.getString('Overwrite existing file'),
					function (overwrite) {
						if (overwrite) {
							onFolderSelected(path, 'overwrite');
						} else {
							onFolderSelected(path, 'keepboth');
						}
					},
					true // modal
				);
			};

			onFolderSelected = function(path, onCollision /*optional*/) {
				playlist.busy = true;
				var args = { path: path, oncollision: onCollision || 'abort' };
				Restangular.one('playlists', playlist.id).all('export').post(args).then(
					function (result) {
						OC.Notification.showTemporary(
							gettextCatalog.getString('Playlist exported to file {{ path }}', { path: result.wrote_to_file }));
						playlist.busy = false;
					},
					function (error) {
						switch (error.status) {
						case 409: // conflict
							onConflict(path);
							break;
						case 404: // not found
							OC.Notification.showTemporary(
								gettextCatalog.getString('Playlist or folder not found'));
							break;
						case 403: // forbidden
							OC.Notification.showTemporary(
								gettextCatalog.getString('Writing to the file is not allowed'));
							break;
						default: // unexpected
							OC.Notification.showTemporary(
								gettextCatalog.getString('Unexpected error'));
							break;
						}
						playlist.busy = false;
					}
				);
			};

			OC.dialogs.filepicker(
					gettextCatalog.getString('Export playlist to a file in the selected folder'),
					onFolderSelected,
					false,
					'httpd/unix-directory',
					true
			);
		};

		// Import playlist contents from a file
		$scope.importFromFile = function(playlist) {
			var onFileSelected = function(file) {
				playlist.busy = true;
				Restangular.one('playlists', playlist.id).all('import').post({filePath: file}).then(
					function(result) {
						libraryService.replacePlaylist(result.playlist);
						var message = gettextCatalog.getString('Imported {{ count }} tracks from the file {{ file }}.',
																{ count: result.imported_count, file: file });
						if (result.failed_count > 0) {
							message += ' ' + gettextCatalog.getString('{{ count }} files were skipped.',
																		{ count: result.failed_count });
						}
						OC.Notification.showTemporary(message);
						$rootScope.$emit('playlistUpdated', playlist.id);
						playlist.busy = false;
					},
					function(error) {
						OC.Notification.showTemporary(
								gettextCatalog.getString('Failed to import playlist from file {{ file }}',
														{ file: file }));
						playlist.busy = false;
					}
				);
			};

			var selectFile = function() {
				OC.dialogs.filepicker(
						gettextCatalog.getString('Import playlist contents from the selected file'),
						onFileSelected,
						false,
						['audio/mpegurl', 'audio/x-scpls'],
						true
				);
			};

			if (playlist.tracks.length > 0) {
				OC.dialogs.confirm(
						gettextCatalog.getString('The playlist already contains some tracks. Imported tracks' +
												' will be appended after the existing contents. Proceed?'),
						gettextCatalog.getString('Append to an existing playlist?'),
						function (overwrite) {
							if (overwrite) {
								selectFile();
							}
						},
						true // modal
				);
			}
			else {
				selectFile();
			}
		};

		// Play/pause playlist
		$scope.togglePlay = function(destination, playlist) {
			if ($rootScope.playingView == destination) {
				playlistService.publish('togglePlayback');
			}
			else {
				var play = function(id, tracks) {
					if (tracks && tracks.length) {
						playlistService.setPlaylist(id, tracks);
						playlistService.publish('play', destination);
					}
				};

				if (destination == '#') {
					play('albums', libraryService.getTracksInAlbumOrder());
				} else if (destination == '#/alltracks') {
					play('alltracks', libraryService.getTracksInAlphaOrder());
				} else if (destination == '#/folders') {
					$scope.$parent.loadFoldersAndThen(function() {
						play('folders', libraryService.getTracksInFolderOrder());
					});
				} else if (destination == '#/genres') {
					play('genres', libraryService.getTracksInGenreOrder());
				} else {
					play('playlist-' + playlist.id, playlist.tracks);
				}
			}
		};

		// Add track to the playlist
		$scope.addTrack = function(playlist, songId) {
			addTracks(playlist, [songId]);
		};

		// Add all tracks on an album to the playlist
		$scope.addAlbum = function(playlist, albumId) {
			addTracks(playlist, trackIdsFromAlbum(albumId));
		};

		// Add all tracks on all albums by an artist to the playlist
		$scope.addArtist = function(playlist, artistId) {
			addTracks(playlist, trackIdsFromArtist(artistId));
		};

		// Add all tracks in a folder to the playlist
		$scope.addFolder = function(playlist, folderId) {
			addTracks(playlist, trackIdsFromFolder(folderId));
		};

		// Add all tracks of the genre to the playlist
		$scope.addGenre = function(playlist, genreId) {
			addTracks(playlist, trackIdsFromGenre(genreId));
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
			} else if ('folder' in droppedItem) {
				$scope.addFolder(playlist, droppedItem.folder);
			} else if ('genre' in droppedItem) {
				$scope.addGenre(playlist, droppedItem.genre);
			} else {
				console.error("Unknwon entity dropped on playlist");
			}
		};

		$scope.allowDrop = function(playlist) {
			// Don't allow dragging a track from a playlist back to the same playlist
			return $rootScope.currentView != '#/playlist/' + playlist.id;
		};

		function trackIdsFromAlbum(albumId) {
			var album = libraryService.getAlbum(albumId);
			return _.pluck(album.tracks, 'id');
		}

		function trackIdsFromArtist(artistId) {
			var artist = libraryService.getArtist(artistId);
			return _.flatten(_.map(_.pluck(artist.albums, 'id'), trackIdsFromAlbum));
		}

		function trackIdsFromFolder(folderId) {
			var folder = libraryService.getFolder(folderId);
			return _.pluck(_.pluck(folder.tracks, 'track'), 'id');
		}

		function trackIdsFromGenre(genreId) {
			var genre = libraryService.getGenre(genreId);
			return _.pluck(_.pluck(genre.tracks, 'track'), 'id');
		}

		function addTracks(playlist, trackIds) {
			_.forEach(trackIds, function(trackId) {
				libraryService.addToPlaylist(playlist.id, trackId);
			});

			// Update the currently playing list if necessary
			if ($rootScope.playingView == '#/playlist/' + playlist.id) {
				var newTracks = _.map(trackIds, function(trackId) {
					return { track: libraryService.getTrack(trackId) };
				});
				playlistService.onTracksAdded(newTracks);
			}

			Restangular.one('playlists', playlist.id).all('add').post({trackIds: trackIds.join(',')});
		}
	}
]);
