/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2024
 */


angular.module('Music').controller('AdvancedSearchViewController', [
	'$rootScope', '$scope', 'playlistService', '$timeout',
	function ($rootScope, $scope, playlistService, $timeout) {

		$rootScope.currentView = $scope.getViewIdFromUrl();

		$scope.tracks = null;

		// $rootScope listeneres must be unsubscribed manually when the control is destroyed
		let _unsubFuncs = [];

		function subscribe(event, handler) {
			_unsubFuncs.push( $rootScope.$on(event, handler) );
		}

		$scope.$on('$destroy', () => {
			_.each(_unsubFuncs, (func) => func());
		});

		/*
		function play(startIndex = null) {
			playlistService.setPlaylist('smartlist', $scope.tracks, startIndex);
			playlistService.publish('play');
		}

		// Call playlistService to play all songs in the current playlist from the beginning
		$scope.onHeaderClick = play;

		// Play the list, starting from a specific track
		$scope.onTrackClick = function(trackId) {
			// play/pause if currently playing list item clicked
			if ($scope.$parent.currentTrack && $scope.$parent.currentTrack.id === trackId) {
				playlistService.publish('togglePlayback');
			}
			// on any other list item, start playing the list from this item
			else {
				let index = _.findIndex($scope.tracks, (i) => i.track.id == trackId);
				play(index);
			}
		};

		$scope.getTrackData = function(listItem, index, _scope) {
			var track = listItem.track;
			return {
				title: track.artist.name + ' - ' + track.title,
				tooltip: '',
				number: index + 1,
				id: track.id
			};
		};

		$scope.getDraggable = function(trackId) {
			return { track: trackId };
		};

		subscribe('scrollToTrack', function(_event, trackId) {
			if ($scope.$parent) {
				$scope.$parent.scrollToItem('track-' + trackId);
			}
		});
		*/

		$timeout(() => {
			$rootScope.loading = false;
			$rootScope.$emit('viewActivated');
		});

		subscribe('deactivateView', () => {
			$rootScope.$emit('viewDeactivated');
		});

	}
]);
