/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2023, 2024
 */


angular.module('Music').controller('SmartListViewController', [
	'$rootScope', '$scope', 'playQueueService', 'libraryService', '$timeout',
	function ($rootScope, $scope, playQueueService, libraryService, $timeout) {

		$rootScope.currentView = $scope.getViewIdFromUrl();

		$scope.tracks = null;

		// $rootScope listeners must be unsubscribed manually when the control is destroyed
		let _unsubFuncs = [];

		function subscribe(event, handler) {
			_unsubFuncs.push( $rootScope.$on(event, handler) );
		}

		$scope.$on('$destroy', () => {
			_.each(_unsubFuncs, (func) => func());
		});

		function play(startIndex = null) {
			playQueueService.setPlaylist('smartlist', $scope.tracks, startIndex);
			playQueueService.publish('play');
		}

		// Call playQueueService to play all songs in the current playlist from the beginning
		$scope.onHeaderClick = play;

		// Play the list, starting from a specific track
		$scope.onTrackClick = function(trackId) {
			// play/pause if currently playing list item clicked
			if ($scope.$parent.currentTrack && $scope.$parent.currentTrack.id === trackId) {
				playQueueService.publish('togglePlayback');
			}
			// on any other list item, start playing the list from this item
			else {
				let index = _.findIndex($scope.tracks, (i) => i.track.id == trackId);
				play(index);
			}
		};

		/**
		 * Gets track data to be displayed in the tracklist directive
		 */
		$scope.getTrackData = function(listItem, index, _scope) {
			var track = listItem.track;
			return {
				title: track.title,
				title2: track.artist.name,
				tooltip: track.title,
				tooltip2: track.artist.name,
				number: index + 1,
				id: track.id,
				art: track.album
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

		// Init happens either immediately (after making the loading animation visible)
		// or once artists have been loaded
		$timeout(initView);

		subscribe('smartListLoaded', function () {
			// Nullify any previous tracks to force tracklist directive recreation
			$scope.tracks = null;
			$timeout(initView);
		});

		function initView() {
			const list = libraryService.getSmartList();
			if (list !== null) {
				$scope.tracks = list.tracks;
				$timeout(() => {
					$rootScope.loading = false;
					$rootScope.$emit('viewActivated');
				});
			}
		}

		subscribe('deactivateView', () => {
			$rootScope.$emit('viewDeactivated');
		});

	}
]);
