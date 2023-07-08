/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2018 - 2023
 */


angular.module('Music').controller('SidebarController', [
	'$rootScope', '$scope', '$timeout',
	function ($rootScope, $scope, $timeout) {

		$scope.follow = (localStorage.getItem('oc_music_details_follow_playback') === 'true');

		$scope.contentType = null;
		$scope.contentId = null;

		$scope.adjustFixedPositions = function() {
			$timeout(function() {
				const sidebarRight = parseInt($('#app-sidebar').css('right'));
				const sidebarWidth = $('#app-sidebar').outerWidth();
				const contentWidth = $('#app-sidebar .sidebar-content').outerWidth();
				const offset = sidebarRight + sidebarWidth - contentWidth;
				$('#app-sidebar .close').css('right', offset);
				$('#app-sidebar #follow-playback').css('right', offset);

				$('#app-sidebar .close').css('top', $('#header').outerHeight());
			});
		};

		function showSidebar(type, id) {
			$('#app-sidebar').removeClass('disappear').show();
			$('#app-content').addClass('with-app-sidebar').trigger(new $.Event('appresized'));

			$scope.contentType = type;
			$scope.contentId = id;
			$scope.adjustFixedPositions();
		}

		$rootScope.$on('showTrackDetails', function(_event, trackId) {
			showSidebar('track', trackId);
		});

		$rootScope.$on('showAlbumDetails', function(_event, albumId) {
			showSidebar('album', albumId);
		});

		$rootScope.$on('showArtistDetails', function(_event, artistId) {
			showSidebar('artist', artistId);
		});

		$rootScope.$on('showRandomFilters', function() {
			showSidebar('random', null);
		});

		$rootScope.$on('showPlaylistDetails', function(_event, playlistId) {
			showSidebar('playlist', playlistId);
		});

		$rootScope.$on('showRadioStationDetails', function(_event, stationId) {
			showSidebar('radioStation', stationId);
		});

		$rootScope.$on('showRadioHint', function() {
			showSidebar('radio', null);
		});

		$rootScope.$on('showPodcastChannelDetails', function(_event, channelId) {
			showSidebar('podcastChannel', channelId);
		});

		$rootScope.$on('showPodcastEpisodeDetails', function(_event, episodeId) {
			showSidebar('podcastEpisode', episodeId);
		});

		$rootScope.$on('hideDetails', function() {
			$('#app-sidebar').hide().addClass('disappear');
			$('#app-content').css('margin-right', '').removeClass('with-app-sidebar').trigger(new $.Event('appresized'));
			$scope.contentId = null;
			$scope.contentType = null;
		});

		$rootScope.$on('resize', $scope.adjustFixedPositions);

		function contentTypeForCurrentPlay() {
			switch ($rootScope.playingView) {
				case '#/radio':		return 'radioStation';
				case '#/podcasts':	return 'podcastEpisode';
				default:			return 'track';
			}
		}

		const showDetailsForCurrentPlay = _.debounce(function() {
			showSidebar(contentTypeForCurrentPlay(), $scope.$parent.currentTrack.id);
		}, 500);

		$scope.$parent.$watch('currentTrack', function(track) {
			// show details for the current track if the feature is enabled
			if ($scope.follow && track && !$('#app-sidebar').hasClass('disappear')) {
				showDetailsForCurrentPlay();
			}
		});

		$scope.toggleFollow = function() {
			$scope.follow = !$scope.follow;
			localStorage.setItem('oc_music_details_follow_playback', $scope.follow.toString());

			// If "follow playback" was enabled and the currently shown track doesn't match currently
			// playing track, then immediately switch to the details of the playing track.
			if ($scope.follow && $scope.$parent.currentTrack
					&& ($scope.$parent.currentTrack.id != $scope.contentId || $scope.contentType != contentTypeForCurrentPlay())) {
				showDetailsForCurrentPlay();
			}
		};

		// A bit hacky logic is needed to show tooltip on truncated detail titles (showing the full title)
		const ctx = document.createElement('canvas').getContext('2d');
		$(document).on('mouseenter', '#app-sidebar dt', function() {
			const $this = $(this);

			const styles = getComputedStyle(this);
			ctx.font = `${styles.fontSize} ${styles.fontFamily}`;
			const text = ctx.measureText(this.innerText);

			const needsTooltip = (text.width > $this.width());
			const hasTooltip = $this.is('[title]');

			if (needsTooltip && !hasTooltip) {
				$this.attr('title', $this.text());
			} else if (!needsTooltip && hasTooltip) {
				$this.removeAttr('title');
			}
		});

		$scope.formatLastfmTags = function(tags) {
			// Last.fm returns individual JSON object in place of array in case there is just one item.
			// In case there are none, the `tags` is undefined.
			tags = tags || [];
			if (!Array.isArray(tags)) {
				tags = [tags];
			}

			// Filter out the tags intended to be used on Last.fm as personal tags. These make no sense
			// for us as we are not aware of the user's Last.fm account and we only show global tags.
			tags = _.reject(tags, {name: 'seen live'});
			tags = _.reject(tags, {name: 'albums I own'});
			tags = _.reject(tags, {name: 'vinyls i own'});
			tags = _.reject(tags, {name: 'favorite albums'});
			tags = _.reject(tags, {name: 'favourite albums'});
			return $scope.formatLinkList(tags);
		};

		$scope.formatLinkList = function(linkArray) {
			// Last.fm returns individual JSON object in place of array in case there is just one item
			// In case there are none, the `linkArray` is undefined.
			linkArray = linkArray || [];
			if (!Array.isArray(linkArray)) {
				linkArray = [linkArray];
			}

			let htmlLinks = _.map(linkArray, function(item) {
				return '<a href="' + item.url + '" target="_blank">' + (item.name || item.title) + '</a>';
			});
			return htmlLinks.join(', ');
		};

		$scope.urlToLink = function(url) {
			return `<a href="${url}" target="_blank">${url}</a>`;
		};

		$scope.scrollToEntity = function(type, entity) {
			const doScroll = function() {
				$rootScope.$emit('scrollTo' + OCA.Music.Utils.capitalize(type), entity.id);
			};

			let destinationView = '#';
			if (type.startsWith('radio')) {
				destinationView = '#/radio';
			} else if (type.startsWith('podcast')) {
				destinationView = '#/podcasts';
			}

			if ($rootScope.currentView !== destinationView) {
				$scope.navigateTo(destinationView, doScroll);
			} else {
				doScroll();
			}
		};
	}
]);
