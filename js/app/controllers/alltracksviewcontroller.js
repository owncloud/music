/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2018
 */


angular.module('Music').controller('AllTracksViewController', [
	'$rootScope', '$scope', 'playlistService', 'libraryService', '$timeout',
	function ($rootScope, $scope, playlistService, libraryService, $timeout) {

		$scope.tracks = null;
		$rootScope.currentView = window.location.hash;

		// $rootScope listeneres must be unsubscribed manually when the control is destroyed
		var unsubFuncs = [];

		function subscribe(event, handler) {
			unsubFuncs.push( $rootScope.$on(event, handler) );
		}

		$scope.$on('$destroy', function () {
			_.each(unsubFuncs, function(func) { func(); });
		});

		function play(startIndex /*optional*/) {
			playlistService.setPlaylist('alltracks', $scope.tracks, startIndex);
			playlistService.publish('play');
		}

		// Call playlistService to play all songs in the current playlist from the beginning
		$scope.onHeaderClick = function() {
			play();
		};

		// Play the list, starting from a specific track
		$scope.onTrackClick = function(trackId) {
			// play/pause if currently playing list item clicked
			if ($scope.$parent.currentTrack && $scope.$parent.currentTrack.id === trackId) {
				playlistService.publish('togglePlayback');
			}
			// on any other list item, start playing the list from this item
			else {
				var index = _.findIndex($scope.tracks, function(i) {return i.track.id == trackId;});
				play(index);
			}
		};

		/**
		 * Gets track data to be dislayed in the tracklist directive
		 */
		$scope.getTrackData = function(listItem, index, scope) {
			var track = listItem.track;
			return {
				title: track.artistName + ' - ' + track.title,
				tooltip: '',
				number: index + 1,
				id: track.id
			};
		};

		$scope.getDraggable = function(trackId) {
			return { track: libraryService.getTrack(trackId) };
		};

		subscribe('scrollToTrack', function(event, trackId) {
			if ($scope.$parent) {
				$scope.$parent.scrollToItem('track-' + trackId);
			}
		});

		// Init happens either immediately (after making the loading animation visible)
		// or once aritsts have been loaded
		$timeout(initView);

		subscribe('artistsLoaded', function () {
			// Nullify any previous tracks to force tracklist directive recreation
			$scope.tracks = null;
			$timeout(initView);
		});

		function initView() {
			if (libraryService.collectionLoaded()) {
				$scope.tracks = libraryService.getTracksInAlphaOrder();
				$timeout(function() {
					$rootScope.loading = false;
				});
				setUpAlphabetNavigation();
			}
		}

		function setUpAlphabetNavigation() {
			$scope.alphabetNavigationTargets = {};
			var prevLetter = '';

			for (var i = 0; i < $scope.tracks.length; ++i) {
				var track = $scope.tracks[i].track;
				var letter = track.artistName.substr(0,1).toUpperCase();
				if (letter != prevLetter) {
					prevLetter = letter;
					$scope.alphabetNavigationTargets[letter] = 'track-' + track.id;
				}
			}
		}

		subscribe('deactivateView', function() {
			// The small delay may help in bringing up the load indicator a bit faster
			// on huge collections (tens of thousands of tracks)
			$timeout(function() {
				$rootScope.$emit('viewDeactivated');
			}, 100);
		});

	}
]);
