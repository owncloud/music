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
	'$scope', '$rootScope', 'playlistService', 'podcastService', 'libraryService', '$timeout', 'gettextCatalog',
	function ($scope, $rootScope, playlistService, podcastService, libraryService, $timeout, gettextCatalog) {

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
		function playEpisodes(listId, episodes) {
			var playlist = _.map(episodes, (episode) => ({track: episode}));
			playlistService.setPlaylist(listId, playlist);
			playlistService.publish('play');
		}

		function playPlaylistFromEpisode(listId, playlist, episode) {
			var index = _.findIndex(playlist, function(i) {return i.track.id == episode.id;});
			playlistService.setPlaylist(listId, playlist, index);
			playlistService.publish('play');
		}

		$scope.playEpisode = function(episodeId) {
			var episode = libraryService.getPodcastEpisode(episodeId);
			var currentTrack = $scope.$parent.currentTrack;

			// play/pause if currently playing track clicked
			if (currentTrack && episode.id === currentTrack.id) {
				playlistService.publish('togglePlayback');
			}
			else {
				var currentListId = playlistService.getCurrentPlaylistId();

				// start playing the channel from this episode if the clicked track belongs
				// to a channel which is the current play scope
				if (currentListId === 'podcast-channel-' + episode.channel.id) {
					playPlaylistFromEpisode(currentListId, playlistService.getCurrentPlaylist(), episode);
				}
				// on any other episode, start playing just the episode
				else {
					playEpisodes('podcast-episode-' + episode.id, [episode]);
				}
			}
		};

		$scope.playChannel = function(channel) {
			// play the episodes from bottom to top ie. from the oldest to newest
			const episodes = _.clone(channel.episodes).reverse();
			playEpisodes('podcast-channel-' + channel.id, episodes);
		};

		$scope.showAddPodcast = function() {
			podcastService.showAddPodcastDialog().then(
				() => $rootScope.loading = false, // success
				() => $rootScope.loading = false, // failure
				() => $rootScope.loading = true,  // adding actually started
			);
		};

		$scope.reloadChannel = function(channel) {
			channel.busy = true;
			podcastService.reloadPodcastChannel(channel).then(() => channel.busy = false);
		}; 

		$scope.removeChannel = function(channel) {
			podcastService.removePodcastChannel(channel).then(
				() => channel.busy = false, // success
				() => channel.busy = false, // failure
				() => channel.busy = true,  // removing actually started
			);
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
		 * Two functions for the tracklist directive integration
		 */
		$scope.getEpisodeData = function(episode, _index, _scope) {
			return {
				title: episode.title,
				tooltip: episode.title,
				number: episode.ordinal,
				id: episode.id
			};
		};
		$scope.getMoreEpisodesText = function(count) {
			return gettextCatalog.getString('Show all {{ count }} episodes …', { count: count });
		};

		// emited on end of playlist by playerController
		subscribe('playlistEnded', function() {
			updateHighlight(null);
		});

		subscribe('playlistChanged', function(_event, playlistId) {
			updateHighlight(playlistId);
		});

		subscribe('scrollToPodcastEpisode', function(_event, episodeId, animationTime /* optional */) {
			var episode = libraryService.getPodcastEpisode(episodeId);
			if (episode) {
				$scope.$parent.scrollToItem('podcast-channel-' + episode.channel.id, animationTime);
			}
		});

		subscribe('scrollToPodcastChannel', function(_event, channelId, animationTime /* optional */) {
			$scope.$parent.scrollToItem('podcast-channel-' + channelId, animationTime);
		});

		function updateHighlight(playlistId) {
			// remove any previous highlight
			$('.highlight').removeClass('highlight');

			// add highlighting if a channel is being played
			if (playlistId.startsWith('podcast-channel-')) {
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

		// Make the content visible immediatedly if the podcasts are already loaded.
		// Otherwise it happens on the 'podcastsLoaded' event handler.
		if (libraryService.podcastsLoaded()) {
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
