/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2020 - 2024
 */


angular.module('Music').controller('GenresViewController', [
	'$rootScope', '$scope', 'playlistService', 'libraryService', '$timeout',
	function ($rootScope, $scope, playlistService, libraryService, $timeout) {

		$scope.genres = null;
		$rootScope.currentView = $scope.getViewIdFromUrl();

		// When making the view visible, the genres are added incrementally step-by-step.
		// The purpose of this is to keep the browser responsive even in case the view contains
		// an enormous amount of genres (like several thousands).
		const INCREMENTAL_LOAD_STEP = 100;
		$scope.incrementalLoadLimit = 0;

		// $rootScope listeners must be unsubscribed manually when the control is destroyed
		let unsubFuncs = [];

		function subscribe(event, handler) {
			unsubFuncs.push( $rootScope.$on(event, handler) );
		}

		$scope.startScanning = function() {
			$scope.$parent.startScanning($scope.$parent.filesWithUnscannedGenre);
			$scope.$parent.filesWithUnscannedGenre = null;
		};

		function playPlaylist(listId, tracks, startFromTrackId = undefined) {
			let startIndex = null;
			if (startFromTrackId !== undefined) {
				startIndex = _.findIndex(tracks, (i) => i.track.id == startFromTrackId);
			}
			playlistService.setPlaylist(listId, tracks, startIndex);
			playlistService.publish('play');
		}

		$scope.onGenreTitleClick = function(genre) {
			playPlaylist('genre-' + genre.id, genre.tracks);
		};

		$scope.onTrackClick = function(trackId) {
			// play/pause if currently playing item clicked
			const currentTrack = $scope.$parent.currentTrack;
			if (currentTrack && currentTrack.id === trackId && currentTrack.type == 'song') {
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
		 * Gets track data to be displayed in the tracklist directive
		 */
		$scope.getTrackData = function(listItem, index, _scope) {
			let track = listItem.track;
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

		playlistService.subscribe('playlistEnded', function() {
			updateHighlight(null);
		}, this);

		playlistService.subscribe('playlistChanged', function(playlistId) {
			updateHighlight(playlistId);
		}, this);

		subscribe('scrollToTrack', function(_event, trackId) {
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

		$scope.$on('$destroy', () => {
			_.each(unsubFuncs, function(func) { func(); });
			playlistService.unsubscribeAll(this);
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
		 * Increase number of shown genres asynchronously step-by-step until
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
