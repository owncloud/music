/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2020
 */


angular.module('Music').controller('RadioViewController', [
	'$rootScope', '$scope', 'playlistService', 'libraryService', 'gettextCatalog', 'Restangular', '$timeout',
	function ($rootScope, $scope, playlistService, libraryService, gettextCatalog, Restangular, $timeout) {

		var INCREMENTAL_LOAD_STEP = 1000;
		$scope.incrementalLoadLimit = INCREMENTAL_LOAD_STEP;
		$scope.stations = null;
		$rootScope.currentView = window.location.hash;

		// $rootScope listeneres must be unsubscribed manually when the control is destroyed
		var unsubFuncs = [];

		function subscribe(event, handler) {
			unsubFuncs.push( $rootScope.$on(event, handler) );
		}

		$scope.$on('$destroy', function() {
			_.each(unsubFuncs, function(func) { func(); });
		});

		$scope.getCurrentStationIndex = function() {
			return listIsPlaying() ? $scope.$parent.currentTrackIndex : null;
		};

		$scope.deleteStation = function(station) {
			OC.dialogs.confirm(
				gettextCatalog.getString('Are you sure to remove the radio station "{{ name }}"?', { name: station.name }),
				gettextCatalog.getString('Remove radio station'),
				function(confirmed) {
					if (confirmed) {
						doDeleteStation(station);
					}
				},
				true
			);
		};

		function doDeleteStation(station) {
			station.busy = true;

			Restangular.one('radio', station.id).remove().then(
				function() {
					station.busy = false;
					var removedIndex = libraryService.removeRadioStation(station.id);
					// Remove also from the play queue if the radio is currently playing
					if (listIsPlaying()) {
						var playingIndex = $scope.getCurrentStationIndex();
						if (removedIndex <= playingIndex) {
							--playingIndex;
						}
						playlistService.onPlaylistModified($scope.stations, playingIndex);
					}
				},
				function (error) {
					station.busy = false;
					OC.Notification.showTemporary(
							gettextCatalog.getString('Failed to delete the radio station'));
				}
			);
		}

		function play(startIndex /*optional*/) {
			playlistService.setPlaylist('radio', $scope.stations, startIndex);
			playlistService.publish('play');
		}

		// Call playlistService to play all songs in the current playlist from the beginning
		$scope.onHeaderClick = function() {
			play();
		};

		// Play the list, starting from a specific track
		$scope.onStationClick = function(stationIndex) {
			// play/pause if currently playing list item clicked
			if ($scope.getCurrentStationIndex() === stationIndex) {
				playlistService.publish('togglePlayback');
			}
			// on any other list item, start playing the list from this item
			else {
				play(stationIndex);
			}
		};

		subscribe('scrollToStation', function(event, stationId) {
			if ($scope.$parent) {
				$scope.$parent.scrollToItem('radio-station-' + stationId);
			}
		});

		// Reload the view if the stations got updated (by import from file)
		subscribe('playlistUpdated', function(event, playlistId) {
			if (playlistId === 'radio') {
				initView();
			}
		});

		function listIsPlaying() {
			return ($rootScope.playingView === $rootScope.currentView);
		}

		// Init happens either immediately (after making the loading animation visible)
		// or once the radio stations have been loaded
		if (libraryService.radioStationsLoaded()) {
			$timeout(initView);
		}

		subscribe('radioStationsLoaded', function () {
			$timeout(initView);
		});

		function initView() {
			$scope.incrementalLoadLimit = 0;
			$scope.stations = libraryService.getRadioStations();
			$timeout(showMore);
		}

		/**
		 * Increase number of shown stations aynchronously step-by-step until
		 * they are all visible. This is to avoid script hanging up for too
		 * long on huge collections.
		 */
		function showMore() {
			// show more entries only if the view is not already (being) deactivated
			if ($rootScope.currentView && $scope.$parent) {
				$scope.incrementalLoadLimit += INCREMENTAL_LOAD_STEP;
				if ($scope.incrementalLoadLimit < $scope.stations.length) {
					$timeout(showMore);
				} else {
					$rootScope.loading = false;
				}
			}
		}

		function showLess() {
			$scope.incrementalLoadLimit -= INCREMENTAL_LOAD_STEP;
			if ($scope.incrementalLoadLimit > 0) {
				$timeout(showLess);
			} else {
				$scope.incrementalLoadLimit = 0;
				$rootScope.$emit('viewDeactivated');
			}
		}

		subscribe('deactivateView', function() {
			$timeout(showLess);
		});

	}
]);
