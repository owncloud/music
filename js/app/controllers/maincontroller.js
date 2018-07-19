/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2013, 2014
 * @copyright Pauli Järvinen 2017, 2018
 */

angular.module('Music').controller('MainController', [
'$rootScope', '$scope', '$timeout', '$window', 'ArtistFactory',
'playlistService', 'libraryService', 'gettext', 'gettextCatalog', 'Restangular',
function ($rootScope, $scope, $timeout, $window, ArtistFactory,
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
	function resetNavigationLetters() {
		for(var i in $scope.letters) {
			$scope.letterAvailable[$scope.letters[i]] = false;
		}
	}
	resetNavigationLetters();

	function updateNavigationLetters(artists) {
		for (var i=0; i < artists.length; i++) {
			var artist = artists[i];
			var letter = artist.name.substr(0,1).toUpperCase();

			if ($scope.letterAvailable.hasOwnProperty(letter)) {
				$scope.letterAvailable[letter] = true;
			}
		}
	}

	$scope.update = function() {
		$scope.updateAvailable = false;
		$rootScope.loadingCollection = true;
		resetNavigationLetters();

		// load the music collection
		ArtistFactory.getArtists().then(function(artists) {
			libraryService.setCollection(artists);
			$scope.artists = libraryService.getAllArtists();

			updateNavigationLetters(artists);

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

	function processNextScanStep() {
		var sliceEnd = filesToScanIterator + FILES_TO_SCAN_PER_STEP;
		var filesForStep = filesToScan.slice(filesToScanIterator, sliceEnd);
		var params = {
				files: filesForStep.join(','),
				finalize: sliceEnd >= filesToScan.length
		};
		Restangular.all('scan').post(params).then(function(result) {
			// Ignore the results if scanning has been cancelled while we
			// were waiting for the result.
			if ($scope.scanning) {
				filesToScanIterator = sliceEnd;

				if (result.filesScanned || result.coversUpdated) {
					$scope.updateAvailable = true;
				}

				$scope.scanningScanned = previouslyScannedCount + filesToScanIterator;

				if (filesToScanIterator < filesToScan.length) {
					processNextScanStep();
				} else {
					$scope.scanning = false;
				}

				// Update the newly scanned tracks to UI automatically when
				// a) the first batch is ready
				// b) the scanning process is completed.
				// Otherwise the UI state is updated only when the user hits the 'update' button
				if ($scope.updateAvailable && $scope.artists && ($scope.artists.length === 0 || !$scope.scanning)) {
					$scope.update();
				}
			}
		});
	}

	$scope.startScanning = function() {
		$scope.toScan = false;
		$scope.scanning = true;
		processNextScanStep();
	};

	$scope.stopScanning = function() {
		$scope.scanning = false;
	};

	$scope.showSidebar = function(trackId) {
		$rootScope.$emit('showDetails', trackId);
		$timeout(function() {
			onViewWidthChange();
			var trackElem = document.getElementById('track-' + trackId);
			if (!isElementInViewPort(trackElem)) {
				$rootScope.$emit('scrollToTrack', trackId, 0);
			}
		}, 300);
	};

	$scope.hideSidebar = function() {
		$rootScope.$emit('hideDetails');
		$timeout(onViewWidthChange, 300);
	};

	var controls = document.getElementById('controls');
	$scope.scrollOffset = function() {
		return controls ? controls.offsetHeight : 0;
	};

	$scope.scrollToItem = function(itemId, animationTime /* optional */) {
		var container = document.getElementById('app-content');
		var element = document.getElementById(itemId);
		if (container && element) {
			if (animationTime === undefined) {
				animationTime = 500;
			}
			angular.element(container).scrollToElement(
					angular.element(element), $scope.scrollOffset(), animationTime);
		}
	};

	// Test if element is at least partially within the view-port
	function isElementInViewPort(el) {
		var appView = document.getElementById('app-view');
		var header = document.getElementById('header');
		var viewPortTop = header.offsetHeight + $scope.scrollOffset();
		var viewPortBottom = header.offsetHeight + appView.offsetHeight;

		var rect = el.getBoundingClientRect();
		return rect.bottom >= viewPortTop && rect.top <= viewPortBottom;
	}

	function setMasterLayout(classes) {
		var missingClasses = _.difference(['tablet', 'mobile', 'portrait'], classes);
		var appContent = $('#app-content');

		_.each(classes, function(cls) {
			appContent.addClass(cls);
		});
		_.each(missingClasses, function(cls) {
			appContent.removeClass(cls);
		});
	}

	function onViewWidthChange() {
		var appViewWidth = $('#app-view').outerWidth();
		// Ignore if the app view is not yet available
		if (appViewWidth) {
			// adjust controls bar width to not overlap with the scroll bar

			// Subtrack one pixel from the width because outerWidth() seems to
			// return rounded integer value which may sometimes be slightly larger
			// than the actual width of the #app-view.
			$('#controls').css('width', appViewWidth - 1);
			$('#controls').css('min-width', appViewWidth - 1);

			// anchor the alphabet navigation to the right edge of the app view
			appViewRight = $window.innerWidth - $('#app-view').offset().left - appViewWidth;
			$('.alphabet-navigation').css('right', appViewRight);

			// Set the app-content classs according to window and view width. This has
			// impact on the overall layout of the app. See mobile.css and tablet.css.
			if ($window.innerWidth <= 570 || appViewWidth <= 500) {
				setMasterLayout(['mobile', 'portrait']);
			}
			else if ($window.innerWidth <= 768) {
				setMasterLayout(['mobile']);
			}
			else if (appViewWidth <= 690) {
				setMasterLayout(['tablet', 'portrait']);
			}
			else if (appViewWidth <= 1050) {
				setMasterLayout(['tablet']);
			}
			else {
				setMasterLayout([]);
			}
		}
	}
	// Watch browser window size changes
	$($window).resize(function() {
		// A small delay is needed here on ownCloud 10.0, otherwise #app-view does not
		// yet have its final width. On Nextcloud 13 the delay would not be necessary.
		$timeout(onViewWidthChange, 300);
		$rootScope.$emit('windowResized');
	});
	// Watch app view width changes within angularjs digest cycles
	$scope.$watch(
		function(scope) {
			return $('#app-view').outerWidth();
		},
		onViewWidthChange
	);
	// Watch view switching being completed
	$rootScope.$watch('loading', function(isLoading) {
		if (!isLoading) {
			$timeout(onViewWidthChange);
		}
	});

	$scope.scanning = false;
	$scope.scanningScanned = 0;
	$scope.scanningTotal = 0;

	// initial lookup if new files are available
	$scope.updateFilesToScan();
}]);
