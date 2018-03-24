/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @copyright Morris Jobke 2013, 2014
 */

angular.module('Music').controller('MainController', [
'$rootScope', '$scope', '$route', '$timeout', '$window', 'ArtistFactory',
'playlistService', 'libraryService', 'gettext', 'gettextCatalog', 'Restangular',
function ($rootScope, $scope, $route, $timeout, $window, ArtistFactory,
		playlistService, libraryService, gettext, gettextCatalog, Restangular) {

	// retrieve language from backend - is set in ng-app HTML element
	gettextCatalog.currentLanguage = $rootScope.lang;

	$rootScope.playing = false;
	$rootScope.playingView = null;
	$scope.currentTrack = null;
	playlistService.subscribe('trackChanged', function(e, listEntry){
		$scope.currentTrack = listEntry.track;
		$scope.currentTrackIndex = playlistService.getCurrentIndex();
	});

	playlistService.subscribe('play', function(e, playingView) {
		// assume that the play started from current view if no other view given
		$rootScope.playingView = playingView || $rootScope.currentView;
	});

	playlistService.subscribe('playlistEnded', function() {
		$rootScope.playingView = null;
		$scope.currentTrack = null;
		$scope.currentTrackIndex = -1;
	});

	$scope.trackCountText = function(playlist) {
		var trackCount = playlist ? playlist.tracks.length : libraryService.getTrackCount();
		return gettextCatalog.getPlural(trackCount, '1 track', '{{ count }} tracks', { count: trackCount });
	};

	$scope.albumCountText = function() {
		var albumCount = libraryService.getAlbumCount();
		return gettextCatalog.getPlural(albumCount, '1 album', '{{ count }} albums', { count: albumCount });
	};

	$scope.letters = [
		'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J',
		'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T',
		'U', 'V', 'W', 'X', 'Y', 'Z'
	];

	$scope.letterAvailable = {};
	for(var i in $scope.letters) {
		$scope.letterAvailable[$scope.letters[i]] = false;
	}

	$scope.update = function() {
		$scope.updateAvailable = false;
		$rootScope.loadingCollection = true;

		// load the music collection
		ArtistFactory.getArtists().then(function(artists) {
			libraryService.setCollection(artists);
			$scope.artists = libraryService.getAllArtists();

			for (var i=0; i < artists.length; i++) {
				var artist = artists[i],
					letter = artist.name.substr(0,1).toUpperCase();

				if ($scope.letterAvailable.hasOwnProperty(letter)) {
					$scope.letterAvailable[letter] = true;
				}
			}

			// Emit the event asynchronously so that the DOM tree has already been
			// manipulated and rendered by the browser when obeservers get the event.
			$timeout(function() {
				$rootScope.$emit('artistsLoaded');
			});

			// Load playlist once the collection has been loaded
			Restangular.all('playlists').getList().then(function(playlists) {
				libraryService.setPlaylists(playlists);
				$scope.playlists = libraryService.getAllPlaylists();
				$rootScope.$emit('playlistsLoaded');
			});
			$rootScope.loadingCollection = false;
		},
		function(response) { // error handling
			$rootScope.loadingCollection = false;

			var reason = null;
			switch (response.status) {
			case 500:
				reason = gettextCatalog.getString(gettext('Internal server error'));
				break;
			case 504:
				reason = gettextCatalog.getString(gettext('Timeout'));
				break;
			default:
				reason = response.status;
				break;
			}
			OC.Notification.showTemporary(
					gettextCatalog.getString(gettext('Failed to load the collection: ')) + reason);
		});

	};

	// initial loading of artists
	$scope.update();

	var FILES_TO_SCAN_PER_STEP = 10;
	var filesToScan = null;
	var filesToScanIterator = 0;
	var previouslyScannedCount = 0;

	$scope.updateFilesToScan = function() {
		Restangular.one('scanstate').get().then(function(state) {
			previouslyScannedCount = state.scannedCount;
			filesToScan = state.unscannedFiles;
			filesToScanIterator = 0;
			$scope.toScan = (filesToScan.length > 0);
			$scope.scanningScanned = previouslyScannedCount;
			$scope.scanningTotal = previouslyScannedCount + filesToScan.length;
			$scope.noMusicAvailable = ($scope.scanningTotal === 0);
		});
	};

	$scope.processNextScanStep = function() {
		$scope.toScan = false;
		$scope.scanning = true;

		var sliceEnd = filesToScanIterator + FILES_TO_SCAN_PER_STEP;
		var filesForStep = filesToScan.slice(filesToScanIterator, sliceEnd);
		var params = {
				files: filesForStep.join(','),
				finalize: sliceEnd >= filesToScan.length
		};
		Restangular.all('scan').post(params).then(function(result){
			filesToScanIterator = sliceEnd;

			if(result.filesScanned || result.coversUpdated) {
				$scope.updateAvailable = true;
			}

			$scope.scanningScanned = previouslyScannedCount + filesToScanIterator;

			if(filesToScanIterator < filesToScan.length) {
				$scope.processNextScanStep();
			} else {
				$scope.scanning = false;
			}

			// Update the newly scanned tracks to UI automatically when
			// a) the first batch is ready
			// b) the scanning process is completed.
			// Otherwise the UI state is updated only when the user hits the 'update' button
			if($scope.updateAvailable && $scope.artists && ($scope.artists.length === 0 || !$scope.scanning)) {
				$scope.update();
			}
		});
	};

	var controls = document.getElementById('controls');
	$scope.scrollOffset = function() {
		return controls ? controls.offsetHeight : 0;
	};

	$scope.scrollToItem = function(itemId) {
		var container = document.getElementById('app-content');
		var element = document.getElementById(itemId);
		if(container && element) {
			angular.element(container).scrollToElement(
					angular.element(element), $scope.scrollOffset(), 500);
		}
	};

	// adjust controls bar width to not overlap with the scroll bar
	function adjustControlsBarWidth() {
		try {
			var controlsWidth = $window.innerWidth - getScrollBarWidth();
			if($(window).width() > 768) {
				controlsWidth -= $('#app-navigation').outerWidth();
			}
			$('#controls').css('width', controlsWidth);
			$('#controls').css('min-width', controlsWidth);
		}
		catch (exception) {
			console.log("No getScrollBarWidth() in core");
		}
	}
	$($window).resize(function() {
		adjustControlsBarWidth();
		$rootScope.$emit('windowResized');
	});
	adjustControlsBarWidth();

	$scope.scanning = false;
	$scope.scanningScanned = 0;
	$scope.scanningTotal = 0;

	// initial lookup if new files are available
	$scope.updateFilesToScan();
}]);
