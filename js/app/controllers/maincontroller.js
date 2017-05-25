/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @copyright Morris Jobke 2013, 2014
 */

angular.module('Music').controller('MainController',
	['$rootScope', '$scope', '$route', '$timeout', '$window', 'ArtistFactory', 'playlistService', 'gettextCatalog', 'Restangular',
	function ($rootScope, $scope, $route, $timeout, $window, ArtistFactory, playlistService, gettextCatalog, Restangular) {

	// retrieve language from backend - is set in ng-app HTML element
	gettextCatalog.currentLanguage = $rootScope.lang;

	$rootScope.playingView = null;
	$scope.currentTrack = null;
	playlistService.subscribe('trackChanged', function(e, listEntry){
		$scope.currentTrack = listEntry.track;
		$scope.currentTrackIndex = playlistService.getCurrentIndex();
	});

	playlistService.subscribe('play', function() {
		$rootScope.playingView = $rootScope.currentView;
	});

	playlistService.subscribe('playlistEnded', function() {
		$rootScope.playingView = null;
		$scope.currentTrack = null;
		$scope.currentTrackIndex = -1;
	});

	$scope.letters = [
		'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J',
		'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T',
		'U', 'V', 'W', 'X', 'Y', 'Z'
	];

	$scope.letterAvailable = {};
	for(var i in $scope.letters){
		$scope.letterAvailable[$scope.letters[i]] = false;
	}

	$scope.update = function() {
		$scope.updateAvailable = false;
		$rootScope.loading = true;

		// load the music collection
		ArtistFactory.getArtists().then(function(artists){
			$scope.artists = sortCollection(artists);
			$scope.allTracks = createTracksIndex(artists);
			for(var i=0; i < artists.length; i++) {
				var artist = artists[i],
					letter = artist.name.substr(0,1).toUpperCase();

				if($scope.letterAvailable.hasOwnProperty(letter)) {
					$scope.letterAvailable[letter] = true;
				}

			}

			// Emit the event asynchronously so that the DOM tree has already been
			// manipulated and rendered by the browser when obeservers get the event.
			$timeout(function() {
				$rootScope.$emit('artistsLoaded');
			});
		});

		// load all playlists
		Restangular.all('playlists').getList().then(function(playlists){
			$scope.playlists = playlists;
			$rootScope.$emit('playlistsLoaded');
		});
	};

	// initial loading of artists
	$scope.update();

	$scope.totalTrackCount = function() {
		return $scope.allTracks ? Object.keys($scope.allTracks).length : 0;
	};

	var FILES_TO_SCAN_PER_STEP = 10;
	var filesToScan = null;
	var filesToScanIterator = 0;

	function updateFilesToScan() {
		Restangular.one('scanstate').get().then(function(state) {
			filesToScan = state.unscannedFiles;
			filesToScanIterator = 0;
			$scope.toScan = (filesToScan.length > 0);
			$scope.noMusicAvailable = (state.scannedCount === 0 && !$scope.toScan);
		});
	}

	$scope.processNextScanStep = function() {
		$scope.toScan = false;
		$scope.scanning = true;
		$scope.scanningTotal = filesToScan.length;

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

			$scope.scanningScanned = filesToScanIterator;

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

	$scope.updatePlaylist = function(playlist) {
		for (var i = 0; i < $scope.playlists.length; ++i) {
			if ($scope.playlists[i].id == playlist.id) {
				$scope.playlists[i].name = playlist.name;
				$scope.playlists[i].trackIds = playlist.trackIds;
				break;
			}
		}
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
				controlsWidth -= $('#app-navigation').width();
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

	// index tracks in a collection (which has tree-like structure artists > albums > tracks)
	function createTracksIndex(artists) {
		var tracksDict = {};

		for (var i = 0; i < artists.length; ++i) {
			var albums = artists[i].albums;
			for (var j = 0; j < albums.length; ++j) {
				var tracks = albums[j].tracks;
				for (var k = 0; k < tracks.length; ++k) {
					var track = tracks[k];
					tracksDict[track.id] = track;
				}
			}
		}

		return tracksDict;
	}

	function sortByName(items) {
		return _.sortBy(items, function(i) { return i.name.toLowerCase(); });
	}

	function sortByYearNameAndDisc(albums) {
		albums = _.sortBy(albums, "disk");
		albums = sortByName(albums);
		albums = _.sortBy(albums, "year");
		return albums;
	}

	function sortByNumberAndTitle(tracks) {
		tracks = _.sortBy(tracks, function(t) { return t.title.toLowerCase(); });
		tracks = _.sortBy(tracks, "number");
		return tracks;
	}

	function sortCollection(artists) {
		artists = sortByName(artists);

		for (var i = 0; i < artists.length; ++i) {
			var artist = artists[i];
			artist.albums = sortByYearNameAndDisc(artist.albums); 

			for (var j = 0; j < artist.albums.length; ++j) {
				var album = artist.albums[j];
				album.tracks = sortByNumberAndTitle(album.tracks);
			}
		}

		return artists;
	}

	// initial lookup if new files are available
	updateFilesToScan();

	$scope.scanning = false;
	$scope.scanningScanned = 0;
	$scope.scanningTotal = 0;

}]);
