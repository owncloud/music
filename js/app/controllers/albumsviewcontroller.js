/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2014
 * @copyright Pauli Järvinen 2017, 2018
 */

angular.module('Music').controller('AlbumsViewController', [
	'$scope', '$rootScope', 'playlistService', 'libraryService',
	'Restangular', '$route', '$timeout', 'gettextCatalog',
	function ($scope, $rootScope, playlistService, libraryService,
			Restangular, $route, $timeout, gettextCatalog) {

		$rootScope.currentView = '#';

		var INCREMENTAL_LOAD_STEP = 10;
		$scope.incrementalLoadLimit = 0;

		// $rootScope listeneres must be unsubscribed manually when the control is destroyed
		var unsubFuncs = [];

		function subscribe(event, handler) {
			unsubFuncs.push( $rootScope.$on(event, handler) );
		}

		$scope.$on('$destroy', function () {
			_.each(unsubFuncs, function(func) { func(); });
		});

		// Prevent controller reload when the URL is updated with window.location.hash,
		// unless the new location actually requires another controller.
		// See http://stackoverflow.com/a/12429133/2104976
		var lastRoute = $route.current;
		$scope.$on('$locationChangeSuccess', function(event) {
			if (lastRoute.$$route.controller === $route.current.$$route.controller) {
				$route.current = lastRoute;
			}
		});

		// Wrap the supplied tracks as a playlist and pass it to the service for playing
		function playTracks(listId, tracks, startIndex /*optional*/) {
			var playlist = _.map(tracks, function(track) {
				return { track: track };
			});
			playlistService.setPlaylist(listId, playlist, startIndex);
			playlistService.publish('play');
		}

		function playPlaylistFromTrack(listId, playlist, track) {
			// update URL hash
			window.location.hash = '#/track/' + track.id;

			var index = _.findIndex(playlist, function(i) {return i.track.id == track.id;});
			playlistService.setPlaylist(listId, playlist, index);
			playlistService.publish('play');
		}

		$scope.playTrack = function(track) {
			// Allow passing an ID as well as a track object
			if (!isNaN(track)) {
				track = libraryService.getTrack(track);
			}

			var currentTrack = $scope.$parent.currentTrack;
			var currentListId = playlistService.getCurrentPlaylistId();

			// play/pause if currently playing track clicked
			if (currentTrack && track.id === currentTrack.id) {
				playlistService.publish('togglePlayback');
			}
			else {
				var album = libraryService.findAlbumOfTrack(track.id);
				var artist = libraryService.findArtistOfAlbum(album.id);

				// start playing the album/artist from this track if the clicked track belongs
				// to album/artist which is the current play scope
				if (currentListId === 'album-' + album.id || currentListId === 'artist-' + artist.id) {
					playPlaylistFromTrack(currentListId, playlistService.getCurrentPlaylist(), track);
				}
				// on any other track, start playing the collection from this track
				else {
					playPlaylistFromTrack('albums', libraryService.getTracksInAlbumOrder(), track);
				}
			}
		};

		$scope.playAlbum = function(album) {
			// update URL hash
			window.location.hash = '#/album/' + album.id;
			playTracks('album-' + album.id, album.tracks);
		};

		$scope.playArtist = function(artist) {
			// update URL hash
			window.location.hash = '#/artist/' + artist.id;
			var tracks = _.flatten(_.pluck(artist.albums, 'tracks'));
			playTracks('artist-' + artist.id, tracks);
		};

		$scope.playFile = function (fileid) {
			if (fileid) {
				Restangular.one('file', fileid).get().then(function(result) {
					$scope.playTrack(result);
					scrollToAlbumOfTrack(result.id);
				});
			}
		};

		$scope.getDraggable = function(type, draggedElement) {
			var draggable = {};
			draggable[type] = draggedElement;
			return draggable;
		};

		$scope.getTrackDraggable = function(trackId) {
			return $scope.getDraggable('track', libraryService.getTrack(trackId));
		};

		$scope.decoratedYear = function(album) {
			return album.year ? ' (' + album.year + ')' : '';
		};

		/**
		 * Gets track data to be dislayed in the tracklist directive
		 */
		$scope.getTrackData = function(track, index, scope) {
			return {
				title: getTitleString(track, scope.artist, false),
				tooltip: getTitleString(track, scope.artist, true),
				number: track.number,
				id: track.id
			};
		};

		/**
		 * Formats a track title string for displaying in tracklist directive
		 */
		function getTitleString(track, artist, plaintext) {
			var att = track.title;
			if (track.artistId !== artist.id) {
				var artistName = ' (' + track.artistName + ') ';
				if (!plaintext) {
					artistName = ' <span class="muted">' + artistName + '</span>';
				}
				att += artistName;
			}
			return att;
		}

		// emited on end of playlist by playerController
		subscribe('playlistEnded', function() {
			window.location.hash = '#/';
			updateHighlight(null);
		});

		subscribe('playlistChanged', function(e, playlistId) {
			updateHighlight(playlistId);
		});

		subscribe('scrollToTrack', function(event, trackId, animationTime /* optional */) {
			var track = libraryService.getTrack(trackId);
			if (track) {
				scrollToAlbumOfTrack(trackId, animationTime);
			}
		});

		function scrollToAlbumOfTrack(trackId, animationTime /* optional */) {
			var album = libraryService.findAlbumOfTrack(trackId);
			if (album) {
				$scope.$parent.scrollToItem('album-' + album.id, animationTime);
			}
		}

		function isPlaying() {
			return $rootScope.playingView !== null;
		}

		function startsWith(str, search) {
			return str !== null && search !== null && str.slice(0, search.length) === search;
		}

		function updateHighlight(playlistId) {
			// remove any previous highlight
			$('.highlight').removeClass('highlight');

			// add highlighting if album or artist is being played
			if (startsWith(playlistId, 'album-') || startsWith(playlistId, 'artist-')) {
				$('#' + playlistId).addClass('highlight');
			}
		}

		function setUpAlphabetNavigation() {
			$scope.alphabetNavigationTargets = {};
			var prevLetter = '';

			for (var i = 0; i < $scope.artists.length; ++i) {
				var letter = $scope.artists[i].name.substr(0,1).toUpperCase();
				if (letter != prevLetter) {
					prevLetter = letter;
					$scope.alphabetNavigationTargets[letter] = 'artist-' + $scope.artists[i].id;
				}
			}
		}

		function initializePlayerStateFromURL() {
			var hashParts = window.location.hash.substr(1).split('/');
			if (!hashParts[0] && hashParts[1] && hashParts[2]) {
				var type = hashParts[1];
				var id = hashParts[2];

				try {
					if (type == 'file') {
						$scope.playFile(id);
					} else if (type == 'artist') {
						$scope.playArtist(libraryService.getArtist(id));
						$scope.$parent.scrollToItem('artist-' + id);
					} else if (type == 'album') {
						$scope.playAlbum(libraryService.getAlbum(id));
						$scope.$parent.scrollToItem('album-' + id);
					} else if (type == 'track') {
						$scope.playTrack(libraryService.getTrack(id));
						scrollToAlbumOfTrack(id);
					}
				}
				catch (exception) {
					OC.Notification.showTemporary(gettextCatalog.getString('Requested entry was not found'));
					window.location.hash = '#/';
				}
			}
			$rootScope.loading = false;
		}

		/**
		 * Increase number of shown artists aynchronously step-by-step until
		 * they are all visible. This is to avoid script hanging up for too
		 * long on huge collections.
		 */
		function showMore() {
			// show more entries only if the view is not already (being) deactivated
			if ($rootScope.currentView && $scope.$parent) {
				$scope.incrementalLoadLimit += INCREMENTAL_LOAD_STEP;
				if ($scope.incrementalLoadLimit < $scope.$parent.artists.length) {
					$timeout(showMore);
				} else {
					// Do not reinitialize the player state if it is already playing.
					// This is the case when the user has started playing music while scanning is ongoing,
					// and then hits the 'update' button. Reinitializing would stop and restart the playback.
					if (!isPlaying()) {
						initializePlayerStateFromURL();
					} else {
						$rootScope.loading = false;
					}
					setUpAlphabetNavigation();
					updateHighlight(playlistService.getCurrentPlaylistId());
				}
			}
		}

		/**
		 * Decrease number of shown artists aynchronously step-by-step until
		 * they are all removed. This is to avoid script hanging up for too
		 * long on huge collections.
		 */
		function showLess() {
			$scope.incrementalLoadLimit -= INCREMENTAL_LOAD_STEP;
			if ($scope.incrementalLoadLimit > 0) {
				$timeout(showLess);
			} else {
				$scope.incrementalLoadLimit = 0;
				$rootScope.$emit('viewDeactivated');
			}
		}

		// Start making artists visible immediatedly if the artists are already loaded.
		// Otherwise it happens on the 'artistsLoaded' event handler.
		if ($scope.$parent.artists) {
			showMore();
		}

		subscribe('artistsLoaded', function() {
			// Start the anynchronus process of making aritsts visible
			$scope.incrementalLoadLimit = 0;
			showMore();
		});

		subscribe('deactivateView', function() {
			$timeout(showLess);
		});
	}
]);
