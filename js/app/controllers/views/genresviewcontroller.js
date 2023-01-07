/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2020, 2021
 */


angular.module('Music').controller('GenresViewController', [
	'$rootScope', '$scope', 'playlistService', 'libraryService', '$timeout',
	function ($rootScope, $scope, playlistService, libraryService, $timeout) {

		$scope.genres = null;
		$rootScope.currentView = $scope.getViewIdFromUrl();

		// When making the view visible, the genres are added incrementally step-by-step.
		// The purpose of this is to keep the browser responsive even in case the view contains
		// an enormous amount of genres (like several thousands).
		let INCREMENTAL_LOAD_STEP = 100;
		$scope.incrementalLoadLimit = 0;

		// $rootScope listeneres must be unsubscribed manually when the control is destroyed
		let unsubFuncs = [];

		function subscribe(event, handler) {
			unsubFuncs.push( $rootScope.$on(event, handler) );
		}

		$scope.startScanning = function() {
			$scope.$parent.startScanning($scope.$parent.filesWithUnscannedGenre);
			$scope.$parent.filesWithUnscannedGenre = null;
		};

		function playPlaylist(listId, tracks, startFromTrackId /*optional*/) {
			let startIndex = null;
			if (startFromTrackId !== undefined) {
				startIndex = _.findIndex(tracks, function(i) {return i.track.id == startFromTrackId;});
			}
			playlistService.setPlaylist(listId, tracks, startIndex);
			playlistService.publish('play');
		}

		$scope.onGenreTitleClick = function(genre) {
			playPlaylist('genre-' + genre.id, genre.tracks);
		};

		$scope.onTrackClick = function(trackId) {
			// play/pause if currently playing item clicked
			if ($scope.$parent.currentTrack && $scope.$parent.currentTrack.id === trackId) {
				playlistService.publish('togglePlayback');
			}
			// on any other list item, start playing the genre or whole library from this item
			else {
				let currentListId = playlistService.getCurrentPlaylistId();
				let genre = libraryService.getTrack(trackId).genre;

				// start playing the genre from this track if the clicked track belongs
				// to genre which is the current play scope
				if (currentListId === 'genre-' + genre.id) {
					playPlaylist(currentListId, genre.tracks, trackId);
				}
				// on any other track, start playing the collection from this track
				else {
					playPlaylist('genres', libraryService.getTracksInGenreOrder(), trackId);
				}
			}
		};

		function updateHighlight(playlistId) {
			// remove any previous highlight
			$('.highlight').removeClass('highlight');

			// add highlighting if an individual genre is being played
			if (playlistId?.startsWith('genre-')) {
				$('#' + playlistId).addClass('highlight');
			}
		}

		/**
		 * Gets track data to be dislayed in the tracklist directive
		 */
		$scope.getTrackData = function(listItem, index, _scope) {
			let track = listItem.track;
			return {
				title: track.artistName + ' - ' + track.title,
				tooltip: '',
				number: index + 1,
				id: track.id
			};
		};

		function getDraggable(type, draggedElementId) {
			let draggable = {};
			draggable[type] = draggedElementId;
			return draggable;
		}

		$scope.getTrackDraggable = function(trackId) {
			return getDraggable('track', trackId);
		};

		$scope.getGenreDraggable = function(genre) {
			return getDraggable('genre', genre.id);
		};

		/**
		 * Two functions for the alphabet-navigation directive integration
		 */
		$scope.getGenreName = function(index) {
			// Substitute the empty string used on unknown genre with a character from
			// the private use area. This should sort after alphabet regardless of the
			// locale settings.
			return $scope.genres[index].name || '';
		};
		$scope.getGenreElementId = function(index) {
			return 'genre-' + $scope.genres[index].id;
		};

		subscribe('playlistEnded', function() {
			updateHighlight(null);
		});

		subscribe('playlistChanged', function(e, playlistId) {
			updateHighlight(playlistId);
		});

		subscribe('scrollToTrack', function(event, trackId) {
			if ($scope.$parent) {
				let elementId = 'track-' + trackId;
				// If the track element is hidden (collapsed), scroll to the genre
				// element instead
				let trackElem = $('#' + elementId);
				if (trackElem.length === 0 || !trackElem.is(':visible')) {
					let genre = libraryService.getTrack(trackId).genre; 
					elementId = 'genre-' + genre.id;
				}
				$scope.$parent.scrollToItem(elementId);
			}
		});

		$scope.$on('$destroy', function () {
			_.each(unsubFuncs, function(func) { func(); });
		});

		// Init happens either immediately (after making the loading animation visible)
		// or once collection has been loaded
		if (libraryService.genresLoaded()) {
			$timeout(initView);
		}

		subscribe('genresLoaded', function () {
			$timeout(initView);
		});

		function initView() {
			$scope.incrementalLoadLimit = 0;
			$scope.genres = libraryService.getAllGenres();
			$timeout(showMore);

			// The "rescan needed" banner uses "collapsed" layout if there are any genres already available
			let rescanPopup = $('#toRescan');
			if ($scope.genres.length > 0) {
				rescanPopup.addClass('collapsed');
			} else {
				rescanPopup.removeClass('collapsed');
			}
		}

		/**
		 * Increase number of shown genres aynchronously step-by-step until
		 * they are all visible. This is to avoid script hanging up for too
		 * long on huge collections.
		 */
		function showMore() {
			// show more entries only if the view is not already (being) deactivated
			if ($rootScope.currentView && $scope.$parent) {
				$scope.incrementalLoadLimit += INCREMENTAL_LOAD_STEP;
				if ($scope.incrementalLoadLimit < $scope.genres.length) {
					$timeout(showMore);
				} else {
					$rootScope.loading = false;
					updateHighlight(playlistService.getCurrentPlaylistId());
					$rootScope.$emit('viewActivated');
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
