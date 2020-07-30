/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2019, 2020
 */


angular.module('Music').controller('FoldersViewController', [
	'$rootScope', '$scope', 'playlistService', 'libraryService', '$timeout',
	function ($rootScope, $scope, playlistService, libraryService, $timeout) {

		$scope.folders = null;
		$rootScope.currentView = window.location.hash;

		// When making the view visible, the folders are added incrementally step-by-step.
		// The purpose of this is to keep the browser responsive even in case the view contains
		// an enormous amount of folders (like several thousands).
		var INCREMENTAL_LOAD_STEP = 100;
		$scope.incrementalLoadLimit = 0;

		// $rootScope listeneres must be unsubscribed manually when the control is destroyed
		var unsubFuncs = [];

		function subscribe(event, handler) {
			unsubFuncs.push( $rootScope.$on(event, handler) );
		}

		function playPlaylist(listId, tracks, startFromTrackId /*optional*/) {
			var startIndex = null;
			if (startFromTrackId !== undefined) {
				startIndex = _.findIndex(tracks, function(i) {return i.track.id == startFromTrackId;});
			}
			playlistService.setPlaylist(listId, tracks, startIndex);
			playlistService.publish('play');
		}

		$scope.onFolderTitleClick = function(folder) {
			playPlaylist('folder-' + folder.id, folder.tracks);
		};

		$scope.onTrackClick = function(trackId) {
			// play/pause if currently playing folder item clicked
			if ($scope.$parent.currentTrack && $scope.$parent.currentTrack.id === trackId) {
				playlistService.publish('togglePlayback');
			}
			// on any other list item, start playing the folder from this item
			else {
				var currentListId = playlistService.getCurrentPlaylistId();
				var folder = libraryService.getTrack(trackId).folder;

				// start playing the folder from this track if the clicked track belongs
				// to folder which is the current play scope
				if (currentListId === 'folder-' + folder.id) {
					playPlaylist(currentListId, folder.tracks, trackId);
				}
				// on any other track, start playing the collection from this track
				else {
					playPlaylist('folders', libraryService.getTracksInFolderOrder(), trackId);
				}
			}
		};

		function updateHighlight(playlistId) {
			// remove any previous highlight
			$('.highlight').removeClass('highlight');

			// add highlighting if an individual folder is being played
			if (OCA.Music.Utils.startsWith(playlistId, 'folder-')) {
				$('#' + playlistId).addClass('highlight');
			}
		}

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

		function getDraggable(type, draggedElementId) {
			var draggable = {};
			draggable[type] = draggedElementId;
			return draggable;
		}

		$scope.getTrackDraggable = function(trackId) {
			return getDraggable('track', trackId);
		};

		$scope.getFolderDraggable = function(folder) {
			return getDraggable('folder', folder.id);
		};

		/**
		 * Two functions for the alphabet-navigation directive integration
		 */
		$scope.getFolderName = function(index) {
			return $scope.folders[index].name;
		};
		$scope.getFolderElementId = function(index) {
			return 'folder-' + $scope.folders[index].id;
		};

		subscribe('playlistEnded', function() {
			updateHighlight(null);
		});

		subscribe('playlistChanged', function(e, playlistId) {
			updateHighlight(playlistId);
		});

		subscribe('scrollToTrack', function(event, trackId) {
			if ($scope.$parent) {
				var elementId = 'track-' + trackId;
				// If the track element is hidden (collapsed), scroll to the folder
				// element instead
				var trackElem = $('#' + elementId);
				if (trackElem.length === 0 || !trackElem.is(':visible')) {
					var folder = libraryService.getTrack(trackId).folder; 
					elementId = 'folder-' + folder.id;
				}
				$scope.$parent.scrollToItem(elementId);
			}
		});

		$scope.$on('$destroy', function () {
			_.each(unsubFuncs, function(func) { func(); });
		});

		// Init happens either immediately (after making the loading animation visible)
		// or once collection has been loaded
		if (libraryService.collectionLoaded()) {
			$timeout(initView);
		}

		subscribe('artistsLoaded', function () {
			$timeout(initView);
		});

		function initView() {
			$scope.incrementalLoadLimit = 0;
			if ($scope.$parent) {
				$scope.$parent.loadFoldersAndThen(function() {
					$scope.folders = libraryService.getAllFolders();
					$timeout(showMore);
				});
			}
		}

		/**
		 * Increase number of shown folders aynchronously step-by-step until
		 * they are all visible. This is to avoid script hanging up for too
		 * long on huge collections.
		 */
		function showMore() {
			// show more entries only if the view is not already (being) deactivated
			if ($rootScope.currentView && $scope.$parent) {
				$scope.incrementalLoadLimit += INCREMENTAL_LOAD_STEP;
				if ($scope.incrementalLoadLimit < $scope.folders.length) {
					$timeout(showMore);
				} else {
					$rootScope.loading = false;
					updateHighlight(playlistService.getCurrentPlaylistId());
				}
			}
		}

		subscribe('deactivateView', function() {
			$timeout(function() {
				$rootScope.$emit('viewDeactivated');
			});
		});
	}
]);
