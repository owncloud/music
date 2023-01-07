/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2018 - 2022
 */


angular.module('Music').controller('AllTracksViewController', [
	'$rootScope', '$scope', 'playlistService', 'libraryService', 'alphabetIndexingService', '$timeout',
	function ($rootScope, $scope, playlistService, libraryService, alphabetIndexingService, $timeout) {

		$rootScope.currentView = $scope.getViewIdFromUrl();

		let _tracks = null;
		let _indexChars = alphabetIndexingService.indexChars();

		// Tracks are split into "buckets" to facilitate lazy loading. One track-list directive
		// is created for each bucket. All tracks in a single bucket have the same indexing char
		// but a single indexing char may have several buckets.
		const BUCKET_MAX_SIZE = 100;
		$scope.trackBuckets = null;

		// $rootScope listeneres must be unsubscribed manually when the control is destroyed
		let _unsubFuncs = [];

		function subscribe(event, handler) {
			_unsubFuncs.push( $rootScope.$on(event, handler) );
		}

		$scope.$on('$destroy', function () {
			_.each(_unsubFuncs, function(func) { func(); });
		});

		function play(startIndex /*optional*/) {
			playlistService.setPlaylist('alltracks', _tracks, startIndex);
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
				let index = _.findIndex(_tracks, function(i) {return i.track.id == trackId;});
				play(index);
			}
		};

		/**
		 * Gets track data to be dislayed in the tracklist directive
		 */
		$scope.getTrackData = function(listItem, index, scope) {
			let track = listItem.track;
			return {
				title: track.artistName + ' - ' + track.title,
				tooltip: '',
				number: scope.$parent.bucket.baseIndex + index + 1,
				id: track.id
			};
		};

		/**
		 * Two functions for the alphabet-navigation directive integration
		 */
		$scope.getBucketName = function(index) {
			return $scope.trackBuckets[index].name;
		};
		$scope.getBucketElementId = function(index) {
			return 'track-bucket-' + index;
		};

		$scope.getDraggable = function(trackId) {
			return { track: trackId };
		};

		function bucketElementForTrack(trackId) {
			let track = libraryService.getTrack(trackId);
			if (track) {
				return document.getElementById('track-bucket-' + track.bucket.id);
			} else {
				return null;
			}
		}

		subscribe('scrollToTrack', function(event, trackId) {
			if ($scope.$parent) {
				$rootScope.$emit('inViewObserver_revealElement', bucketElementForTrack(trackId));
				$scope.$parent.scrollToItem('track-' + trackId);
			}
		});

		// Init happens either immediately (after making the loading animation visible)
		// or once artists have been loaded
		$timeout(initView);

		subscribe('collectionLoaded', function () {
			// Nullify any previous tracks to force tracklist directive recreation
			_tracks = null;
			$scope.trackBuckets = null;
			$timeout(initView);
		});

		function initView() {
			if (libraryService.collectionLoaded()) {
				_tracks = libraryService.getTracksInAlphaOrder();
				$scope.trackBuckets = createTrackBuckets();
				$timeout(function() {
					$rootScope.loading = false;
					$rootScope.$emit('viewActivated');
				});
			}
		}

		function trackAtIndexPreceedsIndexCharAt(trackIdx, charIdx) {
			let name = _tracks[trackIdx].track.artistSortName;
			return (charIdx >= _indexChars.length
				|| alphabetIndexingService.titlePrecedesIndexCharAt(name, charIdx));
		}

		function createTrackBuckets() {
			let buckets = [];

			for (var charIdx = 0, trackIdx = 0;
				charIdx < _indexChars.length && trackIdx < _tracks.length;
				++charIdx)
			{
				if (trackAtIndexPreceedsIndexCharAt(trackIdx, charIdx + 1)) {
					// Track at trackIdx belongs to bucket of the char _indexChars[charIdx]

					let bucket = null;
	
					// Add all the items belonging to the same alphabet to the same bucket
					do {
						// create a new bucket when necessary
						if (!bucket || bucket.tracks.length >= BUCKET_MAX_SIZE) {
							bucket = {
								id: buckets.length,
								char: _indexChars[charIdx],
								firstForChar: !bucket,
								name: _tracks[trackIdx].track.artistSortName,
								tracks: [],
								baseIndex: trackIdx
							};
							buckets.push(bucket);
						}

						_tracks[trackIdx].track.bucket = bucket;
						bucket.tracks.push(_tracks[trackIdx]);
						++trackIdx;
					}
					while (trackIdx < _tracks.length
							&& trackAtIndexPreceedsIndexCharAt(trackIdx, charIdx + 1));
				}
			}

			return buckets;
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
