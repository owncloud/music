/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2018 - 2020
 */


angular.module('Music').controller('SidebarController', [
	'$rootScope', '$scope', '$timeout',
	function ($rootScope, $scope, $timeout) {

		$scope.follow = Cookies.get('oc_music_details_follow_playback') == 'true';

		$scope.contentType = null;
		$scope.contentId = null;

		$scope.adjustFixedPositions = function() {
			$timeout(function() {
				var sidebarWidth = $('#app-sidebar').outerWidth();
				var contentWidth = $('#app-sidebar .sidebar-content').outerWidth();
				var offset = sidebarWidth - contentWidth;
				$('#app-sidebar .close').css('right', offset);
				$('#app-sidebar #follow-playback').css('right', offset);

				$('#app-sidebar .close').css('top', $('#header').outerHeight());
			});
		};

		function showTrackDetails(trackId) {
			OC.Apps.showAppSidebar();
			$scope.contentType = 'track';
			$scope.contentId = trackId;
		}

		$rootScope.$on('showDetails', function(event, trackId) {
			showTrackDetails(trackId);
		});

		$rootScope.$on('hideDetails', function() {
			OC.Apps.hideAppSidebar();
		});

		$rootScope.$on('resize', $scope.adjustFixedPositions);

		$scope.$parent.$watch('currentTrack', function(track) {
			// show details for the current track if the feature is enabled
			if ($scope.follow && track && !$('#app-sidebar').hasClass('disappear')) {
				showTrackDetails(track.id);
			}
		});

		$scope.toggleFollow = function() {
			$scope.follow = !$scope.follow;
			Cookies.set('oc_music_details_follow_playback', $scope.follow.toString(), { expires: 3650 });

			// If "follow playback" was enabled and the currently shown track doesn't match currently
			// playing track, then immediately switch to the details of the playing track.
			if ($scope.follow && $scope.$parent.currentTrack
					&& ($scope.$parent.currentTrack.id != $scope.contentId || $scope.contentType != 'track')) {
				showTrackDetails($scope.$parent.currentTrack.id);
			}
		};
	}
]);
