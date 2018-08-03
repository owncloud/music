/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2013
 * @copyright Pauli Järvinen 2017, 2018
 */


angular.module('Music').controller('PlaylistViewController', [
	'$rootScope', '$scope', '$routeParams', 'playlistService', 'libraryService',
	'gettextCatalog', 'Restangular', '$timeout',
	function ($rootScope, $scope, $routeParams, playlistService, libraryService,
			gettextCatalog, Restangular, $timeout) {

		var INCREMENTAL_LOAD_STEP = 1000;
		$scope.incrementalLoadLimit = INCREMENTAL_LOAD_STEP;
		$scope.tracks = null;
		$rootScope.currentView = window.location.hash;

		// $rootScope listeneres must be unsubscribed manually when the control is destroyed
		var unsubFuncs = [];

		function subscribe(event, handler) {
			unsubFuncs.push( $rootScope.$on(event, handler) );
		}

		$scope.$on('$destroy', function() {
			_.each(unsubFuncs, function(func) { func(); });
		});

		$scope.getCurrentTrackIndex = function() {
			return listIsPlaying() ? $scope.$parent.currentTrackIndex : null;
		};

		// Remove chosen track from the list
		$scope.removeTrack = function(trackIndex) {
			var listId = $scope.playlist.id;

			// Remove the element first from our internal array, without recreating the whole array.
			// Doing this before the HTTP request improves the perceived performance.
			libraryService.removeFromPlaylist(listId, trackIndex);

			if (listIsPlaying()) {
				var playingIndex = $scope.getCurrentTrackIndex();
				if (trackIndex <= playingIndex) {
					--playingIndex;
				}
				playlistService.onPlaylistModified($scope.tracks, playingIndex);
			}

			Restangular.one('playlists', listId).all("remove").post({indices: trackIndex});
		};

		function play(startIndex /*optional*/) {
			var id = 'playlist-' + $scope.playlist.id;
			playlistService.setPlaylist(id, $scope.tracks, startIndex);
			playlistService.publish('play');
		}

		// Call playlistService to play all songs in the current playlist from the beginning
		$scope.onHeaderClick = function() {
			play();
		};

		// Play the list, starting from a specific track
		$scope.onTrackClick = function(trackIndex) {
			// play/pause if currently playing list item clicked
			if ($scope.getCurrentTrackIndex() === trackIndex) {
				playlistService.publish('togglePlayback');
			}
			// on any other list item, start playing the list from this item
			else {
				play(trackIndex);
			}
		};

		$scope.getDraggable = function(index) {
			$scope.draggedIndex = index;
			return {
				track: $scope.tracks[index].track,
				srcIndex: index
			};
		};

		$scope.reorderDrop = function(draggable, dstIndex) {
			var listId = $scope.playlist.id;
			var srcIndex = draggable.srcIndex;

			libraryService.reorderPlaylist($scope.playlist.id, srcIndex, dstIndex);

			if (listIsPlaying()) {
				var playingIndex = $scope.getCurrentTrackIndex();
				if (playingIndex === srcIndex) {
					playingIndex = dstIndex;
				}
				else {
					if (playingIndex > srcIndex) {
						--playingIndex;
					}
					if (playingIndex >= dstIndex) {
						++playingIndex;
					}
				}
				playlistService.onPlaylistModified($scope.tracks, playingIndex);
			}

			Restangular.one('playlists', listId).all("reorder").post({fromIndex: srcIndex, toIndex: dstIndex});
		};

		$scope.allowDrop = function(draggable, dstIndex) {
			return draggable.srcIndex != dstIndex;
		};

		$scope.updateHoverStyle = function(dstIndex) {
			var element = $('.playlist-area .track-list');
			if ($scope.draggedIndex > dstIndex) {
				element.removeClass('insert-below');
				element.addClass('insert-above');
			} else if ($scope.draggedIndex < dstIndex) {
				element.removeClass('insert-above');
				element.addClass('insert-below');
			} else {
				element.removeClass('insert-above');
				element.removeClass('insert-below');
			}
		};

		subscribe('scrollToTrack', function(event, trackId) {
			if ($scope.$parent) {
				var currentIdx = $scope.getCurrentTrackIndex();
				var index;

				// There may be more than one playlist entry with the same track ID.
				// Prefer to scroll to the currently playing entry if the requested
				// track ID matches that. Otherwise scroll to the first match.
				if (currentIdx &&  $scope.tracks[currentIdx].track.id == trackId) {
					index = currentIdx;
				} else {
					index = _.findIndex($scope.tracks, function(entry) {
						return entry.track.id == trackId;
					});
				}
				$scope.$parent.scrollToItem('playlist-track-' + index);
			}
		});

		// Init happens either immediately (after making the loading animation visible)
		// or once both aritsts and playlists have been loaded
		$timeout(initViewFromRoute);
		subscribe('artistsLoaded', initViewFromRoute);
		subscribe('playlistsLoaded', initViewFromRoute);

		function listIsPlaying() {
			return ($rootScope.playingView === $rootScope.currentView);
		}

		function showMore() {
			// show more entries only if the view is not already (being) deactivated
			if ($rootScope.currentView && $scope.$parent) {
				$scope.incrementalLoadLimit += INCREMENTAL_LOAD_STEP;
				if ($scope.incrementalLoadLimit < $scope.tracks.length) {
					$timeout(showMore);
				} else {
					$rootScope.loading = false;
				}
			}
		}

		function initViewFromRoute() {
			if (libraryService.collectionLoaded() && libraryService.playlistsLoaded()) {
				if ($routeParams.playlistId) {
					var playlist = libraryService.getPlaylist($routeParams.playlistId);
					if (playlist) {
						$scope.playlist = playlist;
						$scope.tracks = playlist.tracks;
					}
					else {
						OC.Notification.showTemporary(gettextCatalog.getString('Requested entry was not found'));
						window.location.hash = '#/';
					}
				}
				$timeout(showMore);
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
