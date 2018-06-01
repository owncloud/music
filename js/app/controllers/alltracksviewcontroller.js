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
	'$rootScope', '$scope', '$routeParams', 'playlistService', 'libraryService',
	'gettext', 'gettextCatalog', 'Restangular', '$timeout',
	function ($rootScope, $scope, $routeParams, playlistService, libraryService,
			gettext, gettextCatalog, Restangular , $timeout) {

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

		// Call playlistService to play all songs in the current playlist from the beginning
		$scope.playAll = function() {
			playlistService.setPlaylist($scope.tracks);
			playlistService.publish('play');
		};

		// Play the list, starting from a specific track
		$scope.playTrack = function(trackId) {
			// play/pause if currently playing list item clicked
			if ($scope.$parent.currentTrack && $scope.$parent.currentTrack.id === trackId) {
				playlistService.publish('togglePlayback');
			}
			// on any other list item, start playing the list from this item
			else {
				var index = _.findIndex($scope.tracks, function(i) {return i.track.id == trackId;});
				playlistService.setPlaylist($scope.tracks, index);
				playlistService.publish('play');
			}
		};

		/**
		 * Gets track data to be dislayed in the tracklist directive
		 */
		$scope.getTrackData = function(listItem, index, scope) {
			var track = listItem.track;
			var title = track.artistName + ' - ' + track.title;
			return {
				title: title,
				tooltip: title,
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
		$timeout(function() {
			initViewFromRoute();
		});
		subscribe('artistsLoaded', function () {
			initViewFromRoute();
		});

		function listIsPlaying() {
			return ($rootScope.playingView === $rootScope.currentView);
		}

		function initViewFromRoute() {
			if (libraryService.collectionLoaded()) {
				$scope.tracks = libraryService.getTracksInAlphaOrder();
				$rootScope.loading = false;
			}
		}

		subscribe('deactivateView', function() {
			$rootScope.$emit('viewDeactivated');
		});

	}
]);
