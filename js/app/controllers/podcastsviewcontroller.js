/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2021
 */

angular.module('Music').controller('PodcastsViewController', [
	'$scope', '$rootScope', 'playlistService', 'libraryService', '$location', '$timeout',
	function ($scope, $rootScope, playlistService, libraryService, $location, $timeout) {

		$rootScope.currentView = window.location.hash;

		// $rootScope listeneres must be unsubscribed manually when the control is destroyed
		var unsubFuncs = [];

		function subscribe(event, handler) {
			unsubFuncs.push( $rootScope.$on(event, handler) );
		}

		$scope.$on('$destroy', function () {
			_.each(unsubFuncs, function(func) { func(); });
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
			var index = _.findIndex(playlist, function(i) {return i.track.id == track.id;});
			playlistService.setPlaylist(listId, playlist, index);

			var startOffset = $location.search().offset || null;
			playlistService.publish('play', null, startOffset);
			$location.search('offset', null); // the offset parameter has been used up
		}

		$scope.playTrack = function(trackId) {
			var track = libraryService.getTrack(trackId);
			var currentTrack = $scope.$parent.currentTrack;

			// play/pause if currently playing track clicked
			if (currentTrack && track.id === currentTrack.id) {
				playlistService.publish('togglePlayback');
			}
			else {
				var currentListId = playlistService.getCurrentPlaylistId();

				// start playing the album/artist from this track if the clicked track belongs
				// to album/artist which is the current play scope
				if (currentListId === 'album-' + track.album.id || currentListId === 'artist-' + track.album.artist.id) {
					playPlaylistFromTrack(currentListId, playlistService.getCurrentPlaylist(), track);
				}
				// on any other track, start playing the collection from this track
				else {
					playPlaylistFromTrack('albums', libraryService.getTracksInAlbumOrder(), track);
				}
			}
		};

		$scope.playAlbum = function(album) {
			playTracks('album-' + album.id, album.tracks);
		};

		$scope.playArtist = function(artist) {
			var tracks = _.flatten(_.map(artist.albums, 'tracks'));
			playTracks('artist-' + artist.id, tracks);
		};

		/**
		 * Two functions for the alphabet-navigation directive integration
		 */
		$scope.getChannelName = function(index) {
			return $scope.channels[index].title;
		};
		$scope.getChannelElementId = function(index) {
			return 'podcast-channel-' + $scope.channels[index].id;
		};

		/**
		 * Gets track data to be dislayed in the tracklist directive
		 */
		$scope.getEpisodeData = function(episode, _index, _scope) {
			return {
				title: episode.title,
				tooltip: episode.title,
				number: null,
				id: episode.id
			};
		};

		// emited on end of playlist by playerController
		subscribe('playlistEnded', function() {
			updateHighlight(null);
		});

		subscribe('playlistChanged', function(e, playlistId) {
			updateHighlight(playlistId);
		});

		subscribe('scrollToTrack', function(event, trackId, animationTime /* optional */) {
			scrollToAlbumOfTrack(trackId, animationTime);
		});

		subscribe('scrollToAlbum', function(event, albumId, animationTime /* optional */) {
			$scope.$parent.scrollToItem('album-' + albumId, animationTime);
		});

		subscribe('scrollToArtist', function(event, artistId, animationTime /* optional */) {
			const elemId = 'artist-' + artistId;
			if ($('#' + elemId).length) {
				$scope.$parent.scrollToItem(elemId, animationTime);
			} else {
				// No such artist element, this is probably just a performing artist on some track.
				// Find the first album with a track performed by this artist.
				const tracks = libraryService.findTracksByArtist(artistId);
				scrollToAlbumOfTrack(tracks[0].id);
			}
			
		});

		function scrollToAlbumOfTrack(trackId, animationTime /* optional */) {
			var track = libraryService.getTrack(trackId);
			if (track) {
				$scope.$parent.scrollToItem('album-' + track.album.id, animationTime);
			}
		}

		function updateHighlight(playlistId) {
			// remove any previous highlight
			$('.highlight').removeClass('highlight');

			// add highlighting if album or artist is being played
			if (OCA.Music.Utils.startsWith(playlistId, 'album-')
					|| OCA.Music.Utils.startsWith(playlistId, 'artist-')) {
				$('#' + playlistId).addClass('highlight');
			}
		}

		function updateColumnLayout() {
			// Use the single-column layout if there's not enough room for two columns or more
			var containerWidth = $('#podcasts').width();
			var colWidth = 480;
			$('#podcasts').toggleClass('single-col', containerWidth < 2 * colWidth);
		}

		subscribe('resize', updateColumnLayout);

		function onContentReady() {
			// show content only if the view is not already (being) deactivated
			if ($rootScope.currentView && $scope.$parent) {
				$scope.channels = libraryService.getAllPodcastChannels();
				$rootScope.loading = false;
				$timeout(() => $rootScope.$emit('viewActivated'));
			}
		}

		// Start making artists visible immediatedly if the artists are already loaded.
		// Otherwise it happens on the 'artistsLoaded' event handler.
		if (libraryService.radioStationsLoaded()) {
			onContentReady();
		}

		subscribe('podcastsLoaded', function() {
			onContentReady();
		});

		subscribe('deactivateView', function() {
			$timeout(() => $rootScope.$emit('viewDeactivated'));
		});
	}
]);
