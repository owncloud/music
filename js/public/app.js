
// fix SVGs in IE because the scaling is a real PITA
// https://github.com/owncloud/music/issues/126
if($('html').hasClass('ie')) {
	var replaceSVGs = function() {
		replaceSVG();
		// call them periodically to keep track of possible changes in the artist view
		setTimeout(replaceSVG, 10000);
	};
	replaceSVG();
	setTimeout(replaceSVG, 1000);
	setTimeout(replaceSVGs, 5000);
}

angular.module('Music', ['restangular', 'duScroll', 'gettext', 'ngRoute', 'ang-drag-drop', 'pasvaz.bindonce'])
	.config(['RestangularProvider', '$routeProvider', '$locationProvider',
		function (RestangularProvider, $routeProvider, $locationProvider) {

			// configure RESTAngular path
			RestangularProvider.setBaseUrl('api');

			var overviewControllerConfig = {
				controller:'OverviewController',
				templateUrl:'overview.html'
			};

			var playlistControllerConfig = {
				controller:'PlaylistViewController',
				templateUrl:'playlistview.html'
			};

			var settingsControllerConfig = {
				controller:'SettingsViewController',
				templateUrl:'settingsview.html'
			};

			/**
			 * @see https://stackoverflow.com/questions/38455077/angular-force-an-undesired-exclamation-mark-in-url/41223197#41223197
			 */
			$locationProvider.hashPrefix('');

			$routeProvider
				.when('/',                     overviewControllerConfig)
				.when('/artist/:id',           overviewControllerConfig)
				.when('/album/:id',            overviewControllerConfig)
				.when('/track/:id',            overviewControllerConfig)
				.when('/file/:id',             overviewControllerConfig)
				.when('/playlist/:playlistId', playlistControllerConfig)
				.when('/alltracks',            playlistControllerConfig)
				.when('/settings',             settingsControllerConfig);
		}
	])
	.run(['Token', 'Restangular',
		function(Token, Restangular){
			// add CSRF token
			Restangular.setDefaultHeaders({requesttoken: Token});
		}
	]);

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

angular.module('Music').controller('OverviewController', [
	'$scope', '$rootScope', 'playlistService', 'libraryService', 'Restangular',
	'$route', '$window', '$timeout', 'gettext', 'gettextCatalog',
	function ($scope, $rootScope, playlistService, libraryService, Restangular,
			$route, $window, $timeout, gettext, gettextCatalog) {

		$rootScope.currentView = '#';

		var INCREMENTAL_LOAD_STEP = 4;
		$scope.incrementalLoadLimit = INCREMENTAL_LOAD_STEP;

		// $rootScope listeneres must be unsubscribed manually when the control is destroyed
		var unsubFuncs = [];

		function subscribe(event, handler) {
			unsubFuncs.push( $rootScope.$on(event, handler) );
		}

		$scope.$on('$destroy', function () {
			_.each(unsubFuncs, function(func) { func(); });
		});

		// Prevent controller reload when the URL is updated with window.location.hash,
		// unless the new location actually requires another controller.
		// See http://stackoverflow.com/a/12429133/2104976
		var lastRoute = $route.current;
		$scope.$on('$locationChangeSuccess', function(event) {
			if (lastRoute.$$route.controller === $route.current.$$route.controller) {
				$route.current = lastRoute;
			}
		});

		// Wrap the supplied tracks as a playlist and pass it to the service for playing
		function playTracks(tracks, startIndex /*optional*/) {
			var playlist = _.map(tracks, function(track) {
				return { track: track };
			});
			playlistService.setPlaylist(playlist, startIndex);
			playlistService.publish('play');
		}

		$scope.playTrack = function(track) {
			// Allow passing an ID as well as a track object
			if (!isNaN(track)) {
				track = libraryService.getTrack(track);
			}

			// play/pause if currently playing track clicked
			var currentTrack = $scope.$parent.currentTrack;
			if (currentTrack && track.id === currentTrack.id) {
				playlistService.publish('togglePlayback');
			}
			// on any other track, start playing the album from this track
			else {
				// update URL hash
				window.location.hash = '#/track/' + track.id;

				var album = libraryService.findAlbumOfTrack(track.id);

				playTracks(album.tracks, album.tracks.indexOf(track));
			}
		};

		$scope.$on('playTrack', function (event, trackId) {
			$scope.playTrack(trackId);
		});

		$scope.playAlbum = function(album) {
			// update URL hash
			window.location.hash = '#/album/' + album.id;
			playTracks(album.tracks);
		};

		$scope.playArtist = function(artist) {
			// update URL hash
			window.location.hash = '#/artist/' + artist.id;
			playTracks(_.flatten(_.pluck(artist.albums, 'tracks')));
		};

		$scope.playFile = function (fileid) {
			if (fileid) {
				Restangular.one('file', fileid).get()
					.then(function(result){
						playTracks([result]);
						scrollToAlbumOfTrack(result.id);
					});
			}
		};

		$scope.getDraggable = function(type, draggedElement) {
			var draggable = {};
			draggable[type] = draggedElement;
			return draggable;
		};

		// emited on end of playlist by playerController
		subscribe('playlistEnded', function() {
			window.location.hash = '#/';
		});

		subscribe('scrollToTrack', function(event, trackId) {
			var track = libraryService.getTrack(trackId);
			if (track) {
				scrollToAlbumOfTrack(trackId);
			}
		});

		function scrollToAlbumOfTrack(trackId) {
			var album = libraryService.findAlbumOfTrack(trackId);
			if (album) {
				$scope.$parent.scrollToItem('album-' + album.id);
			}
		}

		function isPlaying() {
			return $rootScope.playingView !== null;
		}

		function initializePlayerStateFromURL() {
			var hashParts = window.location.hash.substr(1).split('/');
			if (!hashParts[0] && hashParts[1] && hashParts[2]) {
				var type = hashParts[1];
				var id = hashParts[2];

				try {
					if (type == 'file') {
						$scope.playFile(id);
					} else if (type == 'artist') {
						$scope.playArtist(libraryService.getArtist(id));
						$scope.$parent.scrollToItem('artist-' + id);
					} else if (type == 'album') {
						$scope.playAlbum(libraryService.getAlbum(id));
						$scope.$parent.scrollToItem('album-' + id);
					} else if (type == 'track') {
						$scope.playTrack(libraryService.getTrack(id));
						scrollToAlbumOfTrack(id);
					}
				}
				catch (exception) {
					OC.Notification.showTemporary(gettextCatalog.getString(gettext('Requested entry was not found')));
					window.location.hash = '#/';
				}
			}
			$rootScope.loading = false;
		}

		function showMore() {
			// show more entries only if the view is not already (being) deactivated
			if ($rootScope.currentView && $scope.$parent) {
				$scope.incrementalLoadLimit += INCREMENTAL_LOAD_STEP;
				if ($scope.incrementalLoadLimit < $scope.$parent.artists.length) {
					$timeout(showMore);
				} else {
					// Do not reinitialize the player state if it is already playing.
					// This is the case when the user has started playing music while scanning is ongoing,
					// and then hits the 'update' button. Reinitializing would stop and restart the playback.
					if (!isPlaying()) {
						initializePlayerStateFromURL();
					} else {
						$rootScope.loading = false;
					}
				}
			}
		}

		// initialize either immedately or once the parent view has finished loading the collection
		if ($scope.$parent.artists) {
			$timeout(showMore);
		}

		subscribe('artistsLoaded', function() {
			showMore();
		});

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

angular.module('Music').controller('PlayerController', [
'$scope', '$rootScope', 'playlistService', 'libraryService',
'Audio', 'Restangular', 'gettext', 'gettextCatalog', '$timeout',
function ($scope, $rootScope, playlistService, libraryService,
		Audio, Restangular, gettext, gettextCatalog, $timeout) {

	$scope.loading = false;
	$scope.player = Audio;
	$scope.currentTrack = null;
	$scope.currentAlbum = null;
	$scope.seekCursorType = 'default';
	$scope.volume = parseInt(Cookies.get('oc_music_volume')) || 50;  // volume can be 0~100
	$scope.repeat = Cookies.get('oc_music_repeat') == 'true';
	$scope.shuffle = Cookies.get('oc_music_shuffle') == 'true';
	$scope.position = {
		bufferPercent: '0%',
		currentPercent: '0%',
		current: 0,
		total: 0
	};

	// Player events may fire synchronously or asynchronously. Utilize $timeout
	// to always handle them asynchronously to run the handler within digest loop
	// but with no nested digests loop (which causes an exception).
	function onPlayerEvent(event, handler) {
		$scope.player.on(event, function(arg) {
			$timeout(function() {
				handler(arg);
			});
		});
	}

	onPlayerEvent('buffer', function (percent) {
		$scope.setBufferPercentage(percent);
	});
	onPlayerEvent('ready', function () {
		$scope.setLoading(false);
	});
	onPlayerEvent('progress', function (currentTime) {
		$scope.setTime(currentTime/1000, $scope.player.duration/1000);
	});
	onPlayerEvent('end', function() {
		$scope.setPlay(false);
		$scope.next();
	});
	onPlayerEvent('duration', function(msecs) {
		$scope.setTime($scope.position.current, $scope.player.duration/1000);
	});

	var titleApp = $('title').html().trim();
	var titleSong = '';
	var titleIcon = '';

	function updateWindowTitle() {
		$('title').html(titleIcon + titleSong + titleApp);
	}

	// display a play icon in the title if a song is playing
	$scope.$watch('playing', function(newValue) {
		titleIcon = newValue ? '▶ ' : '';
		updateWindowTitle();
	});

	// display the song name and artist in the title when there is current track
	$scope.$watch('currentTrack', function(newTrack) {
		titleSong = newTrack ? newTrack.title + ' (' + newTrack.artistName + ') - ' : '';
		updateWindowTitle();
	});

	$scope.getPlayableFileId = function (track) {
		for(var mimeType in track.files) {
			if($scope.player.canPlayMIME(mimeType)) {
				return {
					'mime': mimeType,
					'id': track.files[mimeType]
				};
			}
		}

		return null;
	};

	function setCurrentTrack(playlistEntry) {
		var track = playlistEntry ? playlistEntry.track : null;
		$scope.currentTrack = track;
		$scope.player.stop();
		$scope.setPlay(false);
		if(track !== null) {
			// switch initial state
			$rootScope.started = true;
			$scope.currentAlbum = libraryService.findAlbumOfTrack(track.id);
			$scope.setLoading(true);

			// get webDAV URL to the track and start playing it
			var mimeAndId = $scope.getPlayableFileId(track);
			Restangular.one('file', mimeAndId.id).one('webdav').get().then(function(result) {
				// It is possible that the active track has already changed again by the time we get
				// the URI. Do not start playback in that case.
				if (track == $scope.currentTrack) {
					var url = result.url + '?requesttoken=' + encodeURIComponent(OC.requestToken);
					$scope.player.fromURL(url, mimeAndId.mime);
					$scope.seekCursorType = $scope.player.seekingSupported() ? 'pointer' : 'default';

					$scope.player.play();
					$scope.setPlay(true);
				}
			});

		} else {
			$scope.currentAlbum = null;
			// switch initial state
			$rootScope.started = false;
		}
	}

	$scope.setPlay = function(playing) {
		$rootScope.playing = playing;
	};

	$scope.setLoading = function(loading) {
		$scope.loading = loading;
		if (loading) {
			$scope.position.currentPercent = 0;
			$scope.position.bufferPercent = 0;
		}
	};

	$scope.$watch('volume', function(newValue, oldValue) {
		$scope.player.setVolume(newValue);
		Cookies.set('oc_music_volume', newValue, { expires: 3650 });
	});

	$scope.toggleShuffle = function() {
		$scope.shuffle = !$scope.shuffle;
		Cookies.set('oc_music_shuffle', $scope.shuffle.toString(), { expires: 3650 });
	};

	$scope.toggleRepeat = function() {
		$scope.repeat = !$scope.repeat;
		Cookies.set('oc_music_repeat', $scope.repeat.toString(), { expires: 3650 });
	};

	$scope.setTime = function(position, duration) {
		$scope.position.current = position;
		$scope.position.total = duration;
		$scope.position.currentPercent = (duration > 0 && position <= duration) ?
				Math.round(position/duration*100) + '%' : 0;
	};

	$scope.setBufferPercentage = function(percent) {
		$scope.position.bufferPercent = Math.min(100, Math.round(percent)) + '%';
	};

	$scope.toggle = function(forcePlay) {
		forcePlay = forcePlay || false;
		if($scope.currentTrack === null) {
			// nothing to do
			return null;
		}
		if(forcePlay) {
			$scope.player.play();
			$scope.setPlay(true);
		} else {
			$scope.player.togglePlayback();
			$rootScope.playing = !$rootScope.playing;
		}
	};

	$scope.next = function() {
		var entry = playlistService.jumpToNextTrack($scope.repeat, $scope.shuffle),
			tracksSkipped = false;

		// get the next track as long as the current one contains no playable
		// audio mimetype
		while(entry !== null && !$scope.getPlayableFileId(entry.track)) {
			tracksSkipped = true;
			entry = playlistService.jumpToNextTrack($scope.repeat, $scope.shuffle);
		}
		if(tracksSkipped) {
			OC.Notification.showTemporary(gettextCatalog.getString(gettext('Some not playable tracks were skipped.')));
		}
		setCurrentTrack(entry);
	};

	$scope.prev = function() {
		var track = playlistService.jumpToPrevTrack();
		if(track !== null) {
			setCurrentTrack(track);
		}
	};

	$scope.seek = function($event) {
		var offsetX = $event.offsetX || $event.originalEvent.layerX,
			percentage = offsetX / $event.currentTarget.clientWidth;
		$scope.player.seek(percentage);
	};

	playlistService.subscribe('play', function() {
		// fetch track and start playing
		$scope.next();
	});

	playlistService.subscribe('togglePlayback', function() {
		$scope.toggle();
	});

	$scope.scrollToCurrentTrack = function() {
		if ($scope.currentTrack) {
			$rootScope.$emit('scrollToTrack', $scope.currentTrack.id);
		}
	};
}]);

angular.module('Music').controller('PlaylistViewController', [
	'$rootScope', '$scope', '$routeParams', 'playlistService', 'libraryService',
	'gettext', 'gettextCatalog', 'Restangular', '$timeout',
	function ($rootScope, $scope, $routeParams, playlistService, libraryService,
			gettext, gettextCatalog, Restangular , $timeout) {

		var INCREMENTAL_LOAD_STEP = 1000;
		$scope.incrementalLoadLimit = INCREMENTAL_LOAD_STEP;
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

		// Call playlistService to play all songs in the current playlist from the beginning
		$scope.playAll = function() {
			playlistService.setPlaylist($scope.tracks);
			playlistService.publish('play');
		};

		// Play the list, starting from a specific track
		$scope.playTrack = function(trackIndex) {
			// play/pause if currently playing list item clicked
			if ($scope.getCurrentTrackIndex() === trackIndex) {
				playlistService.publish('togglePlayback');
			}
			// on any other list item, start playing the list from this item
			else {
				playlistService.setPlaylist($scope.tracks, trackIndex);
				playlistService.publish('play');
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
			return $scope.playlist && draggable.srcIndex != dstIndex;
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
				$scope.$parent.scrollToItem('track-' + trackId);
			}
		});

		// Init happens either immediately (after making the loading animation visible)
		// or once both aritsts and playlists have been loaded
		$timeout(function() {
			initViewFromRoute();
		});
		subscribe('artistsLoaded', function () {
			initViewFromRoute();
		});
		subscribe('playlistsLoaded', function () {
			initViewFromRoute();
		});

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
						OC.Notification.showTemporary(gettextCatalog.getString(gettext('Requested entry was not found')));
						window.location.hash = '#/';
					}
				}
				else {
					$scope.playlist = null;
					$scope.tracks = libraryService.getTracksInAlphaOrder();
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

angular.module('Music').controller('SettingsViewController', [
	'$scope', '$rootScope', 'Restangular','$window', '$timeout',
	function ($scope, $rootScope, Restangular, $window, $timeout ) {

		$rootScope.currentView = window.location.hash;

		// $rootScope listeneres must be unsubscribed manually when the control is destroyed
		var unsubFuncs = [];

		function subscribe(event, handler) {
			unsubFuncs.push( $rootScope.$on(event, handler) );
		}

		$scope.$on('$destroy', function () {
			_.each(unsubFuncs, function(func) { func(); });
		});

		$scope.selectPath = function() {
			OC.dialogs.filepicker(
				t('music', 'Path to your music collection'),
				function (path) {
					if ($scope.settings.path !== path) {
						Restangular.one('settings/user/path').customPOST({value: path}, '', {}, {}).then(function (data) {
							if (data.success) {
								$scope.errorPath = false;
								$scope.settings.path = path;
								$scope.$parent.update();
								$scope.$parent.updateFilesToScan();
							} else {
								$scope.errorPath = true;
							}
						});
					}
				},
				false,
				'httpd/unix-directory',
				true
			);
		};

		$scope.addAPIKey = function() {
			var password = Math.random().toString(36).slice(-6) + Math.random().toString(36).slice(-6);
			Restangular.one('settings/userkey/add').customPOST({ password: password, description: $scope.ampacheDescription }, '', {}, {}).then(function(data) {
				if (data.success) {
					$scope.settings.ampacheKeys.push({
						description: $scope.ampacheDescription,
						id: data.id
						});
					$scope.ampacheDescription = '';
					$scope.ampachePassword = password;
				} else {
					$scope.ampachePassword = '';
					$scope.errorAmpache = true;
				}
			});
		};

		$scope.removeAPIKey = function(key) {
			key.loading=true;
			Restangular.one('settings/userkey/remove').customPOST({ id: key.id }, '', {}, {}).then(function(data) {
				if (data.success) {
					// refresh remaining ampacheKeys
					Restangular.one('settings').get().then(function (value) {
						$scope.settings.ampacheKeys = value.ampacheKeys;
					});
				} else {
					key.loading=false;
				}
			});
		};

		$scope.errorPath = false;
		$scope.errorAmpache = false;

		$timeout(function() {
			Restangular.one('settings').get().then(function (value) {
				$scope.settings=value;
				$rootScope.loading = false;
			});
		});

		subscribe('deactivateView', function() {
			$rootScope.$emit('viewDeactivated');
		});

	}
]);

angular.module('Music').controller('SidebarController', [
	'$rootScope', '$scope', 'Restangular', '$timeout', 'playlistService', 'libraryService',
	function ($rootScope, $scope, Restangular, $timeout, playlistService, libraryService) {

		$scope.newPlaylistName = null;

		// holds the state of the editor (visible or not)
		$scope.showCreateForm = false;
		// same as above, but for the playlist renaming. Holds the number of the playlist, which is currently edited
		$scope.showEditForm = null;

		// create playlist
		$scope.create = function(playlist) {
			Restangular.all('playlists').post({name: $scope.newPlaylistName}).then(function(playlist){
				libraryService.addPlaylist(playlist);
				$scope.newPlaylistName = null;
			});

			$scope.showCreateForm = false;
		};

		// Start renaming playlist
		$scope.startEdit = function(playlist) {
			$scope.showEditForm = playlist.id;
		};

		// Commit renaming of playlist
		$scope.commitEdit = function(playlist) {
			Restangular.one('playlists', playlist.id).put({name: playlist.name});
			$scope.showEditForm = null;
		};

		// Remove playlist
		$scope.remove = function(playlist) {
			Restangular.one('playlists', playlist.id).remove();

			// remove the elemnt also from the AngularJS list
			libraryService.removePlaylist(playlist);
		};

		// Play/pause playlist
		$scope.togglePlay = function(destination, playlist) {
			if ($rootScope.playingView == destination) {
				playlistService.publish('togglePlayback');
			}
			else {
				var tracks = null;
				if (destination == '#') {
					tracks = libraryService.getTracksInAlbumOrder();
				} else if (destination == '#/alltracks') {
					tracks = libraryService.getTracksInAlphaOrder();
				} else {
					tracks = playlist.tracks;
				}
				playlistService.setPlaylist(tracks);
				playlistService.publish('play', destination);
			}
		};

		// Add track to the playlist
		$scope.addTrack = function(playlist, song) {
			addTracks(playlist, [song.id]);
		};

		// Add all tracks on an album to the playlist
		$scope.addAlbum = function(playlist, album) {
			addTracks(playlist, trackIdsFromAlbum(album));
		};

		// Add all tracks on all albums by an artist to the playlist
		$scope.addArtist = function(playlist, artist) {
			addTracks(playlist, trackIdsFromArtist(artist));
		};

		// Navigate to a view selected from the sidebar
		var navigationDestination = null;
		$scope.navigateTo = function(destination) {
			if ($rootScope.currentView != destination) {
				$rootScope.currentView = null;
				navigationDestination = destination;
				$rootScope.loading = true;
				// Deactivate the current view. The view emits 'viewDeactivated' once that is done.
				$rootScope.$emit('deactivateView');
			}
		};

		$rootScope.$on('viewDeactivated', function() {
			// carry on with the navigation once the previous view is deactivated
			window.location.hash = navigationDestination;
		});

		// An item dragged and dropped on a sidebar playlist item
		$scope.dropOnPlaylist = function(droppedItem, playlist) {
			if ('track' in droppedItem) {
				$scope.addTrack(playlist, droppedItem.track);
			} else if ('album' in droppedItem) {
				$scope.addAlbum(playlist, droppedItem.album);
			} else if ('artist' in droppedItem) {
				$scope.addArtist(playlist, droppedItem.artist);
			} else {
				console.error("Unknwon entity dropped on playlist");
			}
		};

		$scope.allowDrop = function(playlist) {
			// Don't allow dragging a track from a playlist back to the same playlist
			return $rootScope.currentView != '#/playlist/' + playlist.id;
		};

		function trackIdsFromAlbum(album) {
			return _.pluck(album.tracks, 'id');
		}

		function trackIdsFromArtist(artist) {
			return _.flatten(_.map(artist.albums, trackIdsFromAlbum));
		}

		function addTracks(playlist, trackIds) {
			_.forEach(trackIds, function(trackId) {
				libraryService.addToPlaylist(playlist.id, trackId);
			});

			// Update the currently playing list if necessary
			if ($rootScope.playingView == "#/playlist/" + playlist.id) {
				var newTracks = _.map(trackIds, function(trackId) {
					return { track: libraryService.getTrack(trackId) };
				});
				playlistService.onTracksAdded(newTracks);
			}

			Restangular.one('playlists', playlist.id).all("add").post({trackIds: trackIds.join(',')});
		}
	}
]);

angular.module('Music').directive('albumart', [function() {

	function setCoverImage(element, imageUrl) {
		// remove placeholder stuff
		element.html('');
		element.css('background-color', '');
		// add background image
		element.css('background-image', 'url(' + imageUrl + ')');
	}

	function setPlaceholder(element, text) {
		if(text) {
			// remove background image
			element.css('-ms-filter', '');
			element.css('background-image', '');
			// add placeholder stuff
			element.imageplaceholder(text);
			// remove inlined size-related style properties set by imageplaceholder() to allow
			// dynamic changing between mobile and desktop styles when window size changes
			element.css('line-height', '');
			element.css('font-size', '');
			element.css('width', '');
			element.css('height', '');
		}
	}

	return function(scope, element, attrs, ctrl) {

		var onCoverChanged = function() {
			if(attrs.cover) {
				setCoverImage(element, attrs.cover);
			} else {
				setPlaceholder(element, attrs.albumart);
			}
		};

		var onAlbumartChanged = function() {
			if(!attrs.cover) {
				setPlaceholder(element, attrs.albumart);
			}
		};

		attrs.$observe('albumart', onAlbumartChanged);
		attrs.$observe('cover', onCoverChanged);
	};
}]);


angular.module('Music').directive('ngEnter', function () {
	return function (scope, element, attrs) {
		element.bind("keydown keypress", function (event) {
			if(event.which === 13) {
				scope.$apply(function (){
					scope.$eval(attrs.ngEnter);
				});
				event.preventDefault();
			}
		});
	};
});

angular.module('Music').directive('resize', ['$window', '$rootScope', function($window, $rootScope) {
	return function(scope, element, attrs, ctrl) {
		var resizeNavigation = function() {
			var height = $window.innerHeight;

			// top and button padding of 5px each
			height = height - 10;
			// remove playerbar height if started
			if(scope.started) {
				height = height - 65;
			}
			// remove header height
			height = height - 45;

			element.css('height', height);

			// Hide or replace every second letter on short screens
			if(height < 300) {
				$(".alphabet-navigation a").removeClass("dotted").addClass("stripped");
			} else if(height < 500) {
				$(".alphabet-navigation a").removeClass("stripped").addClass("dotted");
			} else {
				$(".alphabet-navigation a").removeClass("dotted stripped");
			}

			if(height < 300) {
				element.css('line-height', Math.floor(height/13) + 'px');
			} else {
				element.css('line-height', Math.floor(height/26) + 'px');
			}
		};

		resizeNavigation();

		// trigger resize on window resize and player status changes
		var unsubscribeFuncs = [
			$rootScope.$on('windowResized', resizeNavigation),
			$rootScope.$watch('started', resizeNavigation)
		];

		// unsubscribe listeners when the scope is destroyed
		scope.$on('$destroy', function () {
			_.each(unsubscribeFuncs, function(func) { func(); });
		});
	};
}]);

angular.module('Music').directive('sidebarListItem', function() {
	return {
		scope: {
			text: '=',
			destination: '=',
			playlist: '='
		},
		templateUrl: 'sidebarlistitem.html',
		replace: true
	};
});

/**
 * This custom directive produces a self-contained track list widget that updates its list items according to the global playback state and user interaction.
 * Handling this with markup alone would produce a large amount of watchers.
 */


angular.module('Music').directive('trackList', ['$window', '$rootScope', '$interpolate', function ($window, $rootScope, $interpolate) {

	var tpl = '<div class="play-pause"></div>' +
		'<span data-number="{{number}}" class="muted">{{number}}</span> ' +
		'<span title="{{titleAtt}}">{{title}}</span>';

	var trackRenderer = $interpolate(tpl);

	return {
		restrict: 'E',
		link: function (scope, element, attrs) {
			var rendered = false;
			var expanded = false;
			var listContainer;
			var tracks = scope.album.tracks;
			var moreText = scope.$eval(attrs.moreText);
			var lessText = scope.$eval(attrs.lessText);

			var listeners = [
				$rootScope.$watch('currentTrack', function () {
					requestAnimationFrame(render);
				}),
				$rootScope.$watch('playing', function () {
					requestAnimationFrame(render);
				})
			];

			/**
			 * Render markup (once) and set classes according to current scope (always)
			 */
			function render () {
				if (!rendered) {
					var widget = document.createDocumentFragment(),
						trackListFragment = renderTrackList();

					listContainer = document.createElement('ul');
					listContainer.className = 'track-list';

					listContainer.appendChild(trackListFragment);
					widget.appendChild(listContainer);
					element.html(widget);
					element.addClass('collapsed');
					rendered = true;
				}
				/**
				 * Set classes for the currently active list item
				 */
				var elems = listContainer.querySelectorAll(".playing, .current");
				[].forEach.call(elems, function (el) {
					el.classList.remove('current');
					el.classList.remove('playing');
				});
				if (!scope.currentTrack) {
					return;
				}
				var playing = listContainer.querySelector('[data-track-id="' + scope.currentTrack.id + '"]');
				if (playing) {
					playing.classList.add('current');
					if ($rootScope.playing) {
						playing.classList.add('playing');
					} else {
						playing.classList.remove('playing');
					}
				}
			}

			/**
			 * Create the list of individual tracks. Skips after reaching the "toggle threshold"
			 * so only tracks that are initially visible are actually being rendered
			 *
			 * @returns {DocumentFragment}
			 */
			function renderTrackList () {
				var trackListFragment = document.createDocumentFragment();

				for (var index = 0; index < tracks.length; index++) {
					if (index > 4 && tracks.length !== 6) {
						break;
					}
					var track = tracks[index];
					var className = '';
					trackListFragment.appendChild(getTrackNode(track, className));

				}
				if (tracks.length > 6) {
					var lessEl = document.createElement('li');
					var moreEl = document.createElement('li');

					lessEl.innerHTML = lessText;
					lessEl.classList = 'muted more-less collapsible';
					moreEl.innerHTML = moreText;
					moreEl.classList = 'muted more-less';
					trackListFragment.appendChild(lessEl);
					trackListFragment.appendChild(moreEl);
				}
				return trackListFragment;
			}

			/**
			 * Renders a single Track HTML Node
			 *
			 * @param track
			 * @param className
			 * @returns {HTMLLIElement}
			 */
			function getTrackNode (track, className) {
				var listItem = document.createElement('li');
				var newElement = trackRenderer(prepareTrackTemplateData(track));
				listItem.setAttribute('data-track-id', track.id);
				listItem.setAttribute('draggable', true);
				listItem.className = className;
				listItem.innerHTML = newElement;
				return listItem;
			}

			/**
			 * Adds those tracks that aren't initially visible to the listContainer
			 *
			 * @returns {boolean}
			 */
			function renderHiddenTracks () {
				if (tracks.length < 6) {
					return;
				}
				var trackListFragment = document.createDocumentFragment();

				for (var index = 5; index < tracks.length; index++) {
					var track = tracks[index];
					var className = 'collapsible';
					trackListFragment.appendChild(getTrackNode(track, className));
				}
				var toggle = listContainer.getElementsByClassName('muted more-less collapsible');
				listContainer.insertBefore(trackListFragment, toggle[0]);
				return true;
			}

			/**
			 * Checks if the track artist differs from the album artist
			 *
			 * @param track
			 * @returns {boolean}
			 */
			function hasDifferentArtist (track) {
				return (track.artistId !== scope.artist.id);
			}

			/**
			 * Formats a track title string for displaying in the template
			 *
			 * @param track
			 * @param plaintext
			 */
			function getTitleString (track, plaintext) {
				var att = track.title;
				if (hasDifferentArtist(track)) {
					var artistName = ' (' + track.artistName + ') ';
					if (!plaintext) {
						artistName = ' <div class="muted">' + artistName + '</div>';
					}
					att += artistName;
				}

				return att;
			}

			/**
			 * Prepares template data.
			 * 
			 * @param track
			 */
			function prepareTrackTemplateData (track) {
				var data = Object.assign({}, track);//Clone the track data
				data.title = getTitleString(track);
				data.titleAtt = getTitleString(track, true);
				if (data.number) {
					data.number += '.';
				}
				return data;
			}

			/**
			 * Click handler for list items
			 */
			element.on('click', 'li', function (event) {
				var trackId = this.getAttribute('data-track-id');
				if (trackId) {
					scope.$emit('playTrack', trackId);
					scope.$apply();
					return;
				}
				expanded = expanded || renderHiddenTracks();
				element.toggleClass('collapsed');
			});

			/**
			 * Drag&Drop compatibility
			 */
			element.on('dragstart', 'li', function (e) {
				if (e.originalEvent) {
					e.dataTransfer = e.originalEvent.dataTransfer;
				}
				var trackId = this.getAttribute('data-track-id');
				var track = _.findWhere(tracks, {id: parseInt(trackId)});
				var dragData = {'track': track};
				var offset = {x: e.offsetX, y: e.offsetY};
				var transferDataObject = {
					data: dragData,
					channel: 'defaultchannel',
					offset: offset
				};
				var transferDataText = angular.toJson(transferDataObject);
				e.dataTransfer.setData('text', transferDataText);
				e.dataTransfer.effectAllowed = 'copyMove';
				$rootScope.$broadcast('ANGULAR_DRAG_START', e, 'defaultchannel', transferDataObject);
			});

			element.on('dragend', 'li', function (e) {
				$rootScope.$broadcast('ANGULAR_DRAG_END', e, 'defaultchannel');
			});

			scope.$on('$destroy', function () {
				element.off();
				[].forEach.call(listeners, function (el) {
					el();
				});
			});

		}
	};
}]);

angular.module('Music').factory('ArtistFactory', ['Restangular', '$rootScope', function (Restangular, $rootScope) {
	return {
		getArtists: function() { return Restangular.all('collection').getList(); }
	};
}]);

angular.module('Music').factory('Audio', ['$rootScope', function ($rootScope) {
	var wrapper = new PlayerWrapper();
	wrapper.init(function() {
		$rootScope.$emit('SoundManagerReady');
	});
	return wrapper;
}]);

angular.module('Music').factory('Token', [function () {
	return document.getElementsByTagName('head')[0].getAttribute('data-requesttoken');
}]);

angular.module('Music').filter('playTime', function() {
	return function(input) {
		var minutes = Math.floor(input/60),
			seconds = Math.floor(input - (minutes * 60));
		return minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
	};
});
var PlayerWrapper = function() {
	this.underlyingPlayer = 'aurora';
	this.aurora = {};
	this.sm2 = {};
	this.sm2ready = false;
	this.duration = 0;
	this.volume = 100;

	return this;
};

PlayerWrapper.prototype = _.extend({}, OC.Backbone.Events);

PlayerWrapper.prototype.init = function(onReadyCallback) {
	var self = this;
	this.sm2 = soundManager.setup({
		html5PollingInterval: 200,
		onready: function() {
			self.sm2ready = true;
			onReadyCallback();
		}
	});
};

PlayerWrapper.prototype.play = function() {
	switch(this.underlyingPlayer) {
		case 'sm2':
			this.sm2.play('ownCloudSound');
			break;
		case 'aurora':
			this.aurora.play();
			break;
	}
};

PlayerWrapper.prototype.stop = function() {
	switch(this.underlyingPlayer) {
		case 'sm2':
			this.sm2.stop('ownCloudSound');
			this.sm2.destroySound('ownCloudSound');
			break;
		case 'aurora':
			if(this.aurora.asset !== undefined) {
				// check if player's constructor has been called,
				// if so, stop() will be available
				this.aurora.stop();
			}
			break;
	}
};

PlayerWrapper.prototype.togglePlayback = function() {
	switch(this.underlyingPlayer) {
		case 'sm2':
			this.sm2.togglePause('ownCloudSound');
			break;
		case 'aurora':
			this.aurora.togglePlayback();
			break;
	}
};

PlayerWrapper.prototype.seekingSupported = function() {
	// Seeking is not implemented in aurora/flac.js and does not work on all
	// files with aurora/mp3.js. Hence, we disable seeking with aurora.
	return this.underlyingPlayer == 'sm2';
};

PlayerWrapper.prototype.seek = function(percentage) {
	if (this.seekingSupported()) {
		console.log('seek to '+percentage);
		switch(this.underlyingPlayer) {
			case 'sm2':
				this.sm2.setPosition('ownCloudSound', percentage * this.duration);
				break;
			case 'aurora':
				this.aurora.seek(percentage * this.duration);
				break;
		}
	}
	else {
		console.log('seeking is not supported for this file');
	}
};

PlayerWrapper.prototype.setVolume = function(percentage) {
	this.volume = percentage;

	switch(this.underlyingPlayer) {
		case 'sm2':
			this.sm2.setVolume('ownCloudSound', this.volume);
			break;
		case 'aurora':
			this.aurora.volume = this.volume;
			break;
	}
};

PlayerWrapper.prototype.canPlayMIME = function(mime) {
	// Function soundManager.canPlayMIME should not be called if SM2 is still in the process
	// of being initialized, as it may lead to dereferencing an uninitialized member (see #629).
	var canPlayWithSm2 = (this.sm2ready && soundManager.canPlayMIME(mime));
	var canPlayWithAurora = (mime == 'audio/flac' || mime == 'audio/mpeg');
	return canPlayWithSm2 || canPlayWithAurora;
};

PlayerWrapper.prototype.fromURL = function(url, mime) {
	// ensure there are no active playback before starting new
	this.stop();

	this.trigger('loading');

	if (soundManager.canPlayMIME(mime)) {
		this.underlyingPlayer = 'sm2';
	} else {
		this.underlyingPlayer = 'aurora';
	}
	console.log('Using ' + this.underlyingPlayer + ' for type ' + mime + ' URL ' + url);

	var self = this;
	switch (this.underlyingPlayer) {
		case 'sm2':
			this.sm2.html5Only = true;
			this.sm2.createSound({
				id: 'ownCloudSound',
				url: url,
				whileplaying: function() {
					self.trigger('progress', this.position);
				},
				whileloading: function() {
					self.duration = this.durationEstimate;
					self.trigger('duration', this.durationEstimate);
					// The buffer may contain holes after seeking but just ignore those.
					// Show the buffering status according the last buffered position.
					var bufCount = this.buffered.length;
					var bufEnd = (bufCount > 0) ? this.buffered[bufCount-1].end : 0;
					self.trigger('buffer', bufEnd / this.durationEstimate * 100);
				},
				onsuspend: function() {
					// Work around an issue in Firefox where the last buffered position will almost
					// never equal the duration. See https://github.com/scottschiller/SoundManager2/issues/114.
					// On Firefox, the buffering is *usually* not suspended and this event fires only when the
					// downloading is completed.
					var isFirefox = (typeof InstallTrigger !== 'undefined');
					if (isFirefox) {
						self.trigger('buffer', 100);
					}
				},
				onfinish: function() {
					self.trigger('end');
				},
				onload: function(success) {
					if (success) {
						self.trigger('ready');
					} else {
						console.log('SM2: sound load error');
					}
				}
			});
			break;

		case 'aurora':
			this.aurora = AV.Player.fromURL(url);
			this.aurora.asset.source.chunkSize=524288;

			this.aurora.on('buffer', function(percent) {
				self.trigger('buffer', percent);
			});
			this.aurora.on('progress', function(currentTime) {
				self.trigger('progress', currentTime);
			});
			this.aurora.on('ready', function() {
				self.trigger('ready');
			});
			this.aurora.on('end', function() {
				self.trigger('end');
			});
			this.aurora.on('duration', function(msecs) {
				self.duration = msecs;
				self.trigger('duration', msecs);
			});
			break;
	}

	// Set the current volume to the newly created player instance
	this.setVolume(this.volume);
};

angular.module('Music').service('libraryService', ['$rootScope', function($rootScope) {

	var artists = null;
	var albums = null;
	var tracksIndex = {};
	var tracksInAlbumOrder = null;
	var tracksInAlphaOrder = null;
	var playlists = null;

	function sortCaseInsensitive(items, field) {
		return _.sortBy(items, function(i) { return i[field].toLowerCase(); });
	}

	function sortByYearNameAndDisc(albums) {
		albums = _.sortBy(albums, 'disk');
		albums = sortCaseInsensitive(albums, 'name');
		albums = _.sortBy(albums, 'year');
		return albums;
	}

	function sortByNumberAndTitle(tracks) {
		tracks = sortCaseInsensitive(tracks, 'title');
		tracks = _.sortBy(tracks, 'number');
		return tracks;
	}

	function sortCollection(collection) {
		collection = sortCaseInsensitive(collection, 'name');
		_.forEach(collection, function(artist) {
			artist.albums = sortByYearNameAndDisc(artist.albums);
			_.forEach(artist.albums, function(album) {
				album.tracks = sortByNumberAndTitle(album.tracks);
			});
		});
		return collection;
	}

	function moveArrayElement(array, from, to) {
		array.splice(to, 0, array.splice(from, 1)[0]);
	}

	function playlistEntry(track) {
		return { track: track };
	}

	function playlistEntryFromId(trackId) {
		return playlistEntry(tracksIndex[trackId]);
	}

	function wrapPlaylist(playlist) {
		return {
			id: playlist.id,
			name: playlist.name,
			tracks: _.map(playlist.trackIds, playlistEntryFromId)
		};
	}

	function createTrackContainers() {
		// album order "playlist"
		var tracks = _.flatten(_.pluck(albums, 'tracks'));
		tracksInAlbumOrder = _.map(tracks, playlistEntry);

		// alphabetic order "playlist"
		tracks = sortCaseInsensitive(tracks, 'title');
		tracks = sortCaseInsensitive(tracks, 'artistName');
		tracksInAlphaOrder = _.map(tracks, playlistEntry);

		// tracks index
		_.forEach(tracks, function(track) {
			tracksIndex[track.id] = track;
		});
	}

	return {
		setCollection: function(collection) {
			artists = sortCollection(collection);
			albums = _.flatten(_.pluck(artists, 'albums'));
			createTrackContainers();
		},
		setPlaylists: function(lists) {
			playlists = _.map(lists, wrapPlaylist);
		},
		addPlaylist: function(playlist) {
			playlists.push(wrapPlaylist(playlist));
		},
		removePlaylist: function(playlist) {
			playlists.splice(playlists.indexOf(playlist), 1);
		},
		addToPlaylist: function(playlistId, trackId) {
			playlist = this.getPlaylist(playlistId);
			playlist.tracks.push(playlistEntryFromId(trackId));
		},
		removeFromPlaylist: function(playlistId, indexToRemove) {
			playlist = this.getPlaylist(playlistId);
			playlist.tracks.splice(indexToRemove, 1);
		},
		reorderPlaylist: function(playlistId, srcIndex, dstIndex) {
			playlist = this.getPlaylist(playlistId);
			moveArrayElement(playlist.tracks, srcIndex, dstIndex);
		},
		getArtist: function(id) {
			return _.findWhere(artists, { id: Number(id) });
		},
		getAllArtists: function() {
			return artists;
		},
		getAlbum: function(id) {
			return _.findWhere(albums, { id: Number(id) });
		},
		getAlbumCount: function() {
			return albums ? albums.length : 0;
		},
		getTrack: function(id) {
			return tracksIndex[id];
		},
		getTracksInAlphaOrder: function() {
			return tracksInAlphaOrder;
		},
		getTracksInAlbumOrder: function() {
			return tracksInAlbumOrder;
		},
		getTrackCount: function() {
			return tracksInAlphaOrder ? tracksInAlphaOrder.length : 0;
		},
		getPlaylist: function(id) {
			return _.findWhere(playlists, { id: Number(id) });
		},
		getAllPlaylists: function() {
			return playlists;
		},
		findAlbumOfTrack: function(trackId) {
			return _.find(albums, function(album) {
				return _.findWhere(album.tracks, {id : Number(trackId)});
			});
		},
		collectionLoaded: function() {
			return artists !== null;
		},
		playlistsLoaded: function() {
			return playlists !== null;
		}
	};
}]);

angular.module('Music').service('playlistService', ['$rootScope', function($rootScope) {
	var playlist = null;
	var playOrder = [];
	var playOrderIter = -1;
	var startFromIndex = null;
	var prevShuffleState = false;

	function shuffledIndices() {
		var indices = _.range(playlist.length);
		return _.shuffle(indices);
	}

	function shuffledIndicesExcluding(toExclude) {
		var indices = _.range(playlist.length);
		indices.splice(toExclude, 1);
		return _.shuffle(indices);
	}

	function wrapIndexToStart(list, index) {
		if (index > 0) {
			// slice array in two parts and interchange them
			var begin = list.slice(0, index);
			var end = list.slice(index);
			list = end.concat(begin);
		}
		return list;
	}

	function initPlayOrder(shuffle) {
		if (shuffle) {
			if (startFromIndex !== null) {
				playOrder = [startFromIndex].concat(shuffledIndicesExcluding(startFromIndex));
			} else {
				playOrder = shuffledIndices();
			}
		}
		else {
			playOrder = _.range(playlist.length);
			if (startFromIndex !== null) {
				playOrder = wrapIndexToStart(playOrder, startFromIndex);
			}
		}
		prevShuffleState = shuffle;
	}

	function enqueueIndices(shuffle) {
		var prevIndex = _.last(playOrder);
		var nextIndices = null;

		// Append playlist indices in suitable order, excluding the previously played index
		// to prevent the same track from playing twice in row. Playlist containing only a
		// single track is a special case as there we cannot exclude our only track.
		if (playlist.length === 1) {
			nextIndices = [0];
		} else if (shuffle) {
			nextIndices = shuffledIndicesExcluding(prevIndex);
		} else {
			nextIndices = wrapIndexToStart(_.range(playlist.length), prevIndex);
			nextIndices = _.rest(nextIndices);
		}

		playOrder = playOrder.concat(nextIndices);
	}

	function checkShuffleStateChange(currentShuffleState) {
		if (currentShuffleState != prevShuffleState) {
			// Drop any future indices from the play order when the shuffle state changes
			// and enqueue one playlist worth of indices according the new state.
			playOrder = _.first(playOrder, playOrderIter);
			enqueueIndices(currentShuffleState);
			prevShuffleState = currentShuffleState;
		}
	}

	function insertMany(hostArray, targetIndex, insertedItems) {
		hostArray.splice.apply(hostArray, [targetIndex, 0].concat(insertedItems));
	}

	return {
		getCurrentIndex: function() {
			return (playOrderIter >= 0) ? playOrder[playOrderIter] : null;
		},
		jumpToPrevTrack: function() {
			if(playlist && playOrderIter > 0) {
				--playOrderIter;
				track = playlist[this.getCurrentIndex()];
				this.publish('trackChanged', track);
				return track;
			}
			return null;
		},
		jumpToNextTrack: function(repeat, shuffle) {
			if (playlist === null) {
				return null;
			}
			if (!playOrder) {
				initPlayOrder(shuffle);
			}
			++playOrderIter;
			checkShuffleStateChange(shuffle);

			// check if we have run to the end of the enqueued tracks
			if (playOrderIter >= playOrder.length) {
				if (repeat) { // start another round
					enqueueIndices(shuffle);
				} else { // we are done
					playOrderIter = -1;
					playlist = null;
					this.publish('playlistEnded');
					return null;
				}
			}

			var track = playlist[this.getCurrentIndex()];
			this.publish('trackChanged', track);
			return track;
		},
		setPlaylist: function(pl, startIndex /*optional*/) {
			playlist = pl.slice(); // copy
			playOrder = null;
			playOrderIter = -1;
			startFromIndex = (startIndex === undefined) ? null : startIndex;
		},
		onPlaylistModified: function(pl, currentIndex) {
			var currentTrack = playlist[this.getCurrentIndex()];
			// check if the track being played is still available in the list
			if (pl[currentIndex] === currentTrack) {
				// re-init the play-order, erasing any history data
				playlist = pl.slice(); // copy
				playOrderIter = 0;
				startFromIndex = currentIndex;
				initPlayOrder(prevShuffleState);
			}
			// if not, then we no longer have a valid list position
			else {
				playlist = null;
				playOrder = null;
				playOrderIter = -1;
			}
			this.publish('trackChanged', currentTrack);
		},
		onTracksAdded: function(newTracks) {
			var prevListSize = playlist.length;
			playlist = playlist.concat(newTracks);
			var newIndices = _.range(prevListSize, playlist.length);
			if (prevShuffleState) {
				// Shuffle the new tracks with the remaining tracks on the list
				var remaining = _.tail(playOrder, playOrderIter+1);
				remaining = _.shuffle(remaining.concat(newIndices));
				playOrder = _.first(playOrder, playOrderIter+1).concat(remaining);
			}
			else {
				// Try to find the next position of the previously last track of the list,
				// and insert the new tracks in play order after that. If the index is not
				// found, then we have already wrapped over the last track and the new tracks
				// do not need to be added.
				var insertPos = _.indexOf(playOrder, prevListSize-1, playOrderIter);
				if (insertPos >= 0) {
					++insertPos;
					insertMany(playOrder, insertPos, newIndices);
				}
			}
		},
		publish: function(name, parameters) {
			$rootScope.$emit(name, parameters);
		},
		subscribe: function(name, listener) {
			return $rootScope.$on(name, listener);
		}
	};
}]);

angular.module('Music').run(['gettextCatalog', function (gettextCatalog) {
/* jshint -W100 */
    gettextCatalog.setStrings('ach', {});
    gettextCatalog.setStrings('ady', {});
    gettextCatalog.setStrings('af_ZA', {});
    gettextCatalog.setStrings('af', {});
    gettextCatalog.setStrings('ak', {});
    gettextCatalog.setStrings('am_ET', {});
    gettextCatalog.setStrings('ar', {"Albums":"الألبومات","Artists":"الفنانون","Description":"الوصف","Description (e.g. App name)":"الوصف (مثل اسم التطبيق)","Generate API password":"أنشِئ كلمة سر لواجهة برمجة التطبيقات ( API)","Invalid path":"مسار غير صحيح","Music":"الموسيقى","Next":"التالي","Pause":"إيقاف","Play":"تشغيل","Previous":"السابق","Repeat":"إعادة","Revoke API password":"إلغاء كلمة سر API","Shuffle":"اختيار عشوائي","Some not playable tracks were skipped.":"جرى تخطى بعض المقاطع غير العاملة","This setting specifies the folder which will be scanned for music.":"ستخصص الإعدادات الملف الذي سُيجرى البحث فيه عن الموسيقى","Tracks":"المقاطع","Unknown album":"ألبوم غير معروف","Unknown artist":"فنان غير معروف","Use this address to browse your music collection from any Ampache compatible player.":"استخدم هذا العنوان في أي مشغل متوافق مع Ampache للبحث عن مجموعتك الموسيقية "});
    gettextCatalog.setStrings('ast', {"Albums":"Álbumes","Artists":"Artistes","Description":"Descripción","Description (e.g. App name)":"Descripción (p.ex, nome de l'aplicación)","Generate API password":"Xenerar contraseña pa la API","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Equí pues xenerar contraseñes pa usales cola API d'Ampache, yá que nun puen almacenase de mou seguru pol diseñu de la API d'Ampache. Pues crear toles contraseñes que quieras y revocales en cualquier momentu.","Invalid path":"Camín inválidu","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Recuerda que la API d'Ampache namái ye un prototipu y ye inestable. Informa de la to esperiencia con esta nueva carauterística nel <a href=\"https://github.com/owncloud/music/issues/60\">informe de fallu</a> correspondiente. Prestábame tener una llista de veceros cola que probala. Gracies.","Music":"Música","Next":"Siguiente","Path to your music collection":"Camín a la to coleición de música","Pause":"Posa","Play":"Reproducir","Previous":"Anterior","Repeat":"Repitir","Revoke API password":"Revocar contraseña pa la API","Shuffle":"Mecer","Tracks":"Canciones","Unknown album":"Álbum desconocíu","Unknown artist":"Artista desconocíu","Use your username and following password to connect to this Ampache instance:":"Usa'l to nome d'usuariu y la siguiente contraseña pa coneutate con esta instancia d'Ampache:"});
    gettextCatalog.setStrings('az', {"Albums":"Albomlar","Artists":"Müğənnilər","Description":"Açıqlanma","Description (e.g. App name)":"Açıqlanma(Misal üçün proqram adı)","Generate API password":"APİ şifrəsinin generasiyası","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Ampache APİ-nin istifadə edilməsi üçün burda siz şifrələr generasiya edə bilərsiniz ona görə ki, onlar yalnz təhlükəsiz saxlana bilər. Bu Ampache API-nin öz dizaynıdır. Siz istənilən zaman çoxlu şifrə yarada və onları silə bilərsiniz.","Invalid path":"Yalnış ünvan","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Yadda saxlayın, Ampache API yalnız göstərmək üçündür və stabil deyil. Bu imkanla olan öz praktikanızı <a href=\"https://github.com/owncloud/music/issues/60\">səbəb</a> uyğun unvanla bölüşməkdən çəkinməyin. Həmçinin bunun test edilməsi üçün müştərilər siyahısını istərdim.\nTəşəkkürlər ","Music":"Musiqi","Next":"Növbəti","Path to your music collection":"Sizin musiqi yığmasının ünvanı","Pause":"Ara ver","Play":"Oxu","Previous":"Əvvələ","Repeat":"Təkrar","Revoke API password":"API şifrəsini sil","Shuffle":"Qarışdırmaq","Some not playable tracks were skipped.":"Bəzi oxunulabilməyən musiqilər ötürülüb.","This setting specifies the folder which will be scanned for music.":"Bu quraşdırma qovluğu təyin edir hansı ki, musiqi üçün tədqiq ediləcək.","Tracks":"Musiqi","Unknown album":"Bəlli olmayan albom","Unknown artist":"Bəlli olmayan artist","Use this address to browse your music collection from any Ampache compatible player.":"İstənilən Ampache uyğunluğu olan oxuyucudan sizin musiqi kolleksiyanızı göstərmək üçün, bu ünvandan istifadə edin.","Use your username and following password to connect to this Ampache instance:":"Bu Ampache nusxəsinə qoşulmaq üçün öz istifadəçi adı və şifrənizi istifadə edin."});
    gettextCatalog.setStrings('be', {});
    gettextCatalog.setStrings('bg_BG', {"Albums":"Албуми","Artists":"Изпълнители","Description":"Описание","Description (e.g. App name)":"Описание (пр. име на Приложението)","Generate API password":"Генерирай API парола","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Тук можеш да генерираш пароли, които да използваш с Ampache API, защото те не могат да бъдат съхранени по сигурен начин поради архитектурата на Ampachi API. Можеш да генерираш колко искаш пароли и да ги спираш по всяко време.","Invalid path":"Невалиден път","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Помни, че Ampache API е само предварителна варсия и не е стабилно. Можеш да опишеш своя опит с тази услуга на <a href=\"https://github.com/owncloud/music/issues/60\">следната страница</a>.","Music":"Музика","Next":"Следваща","Path to your music collection":"Пътят към музикалната ти колекция","Pause":"Пауза","Play":"Пусни","Previous":"Предишна","Repeat":"Повтори","Revoke API password":"Премахни API паролата","Shuffle":"Разбъркай","Some not playable tracks were skipped.":"Някои невъзпроизведими песни бяха пропуснати.","This setting specifies the folder which will be scanned for music.":"Тази настройка задава папката, която ще бъде сканирана за музика.","Tracks":"Песни","Unknown album":"Непознат албум","Unknown artist":"Непознат изпълнител","Use this address to browse your music collection from any Ampache compatible player.":"Използвай този адрес, за да разглеждаш музикалната си колекция от всеки съвместим с Ampache музикален плеър.","Use your username and following password to connect to this Ampache instance:":"Използвай своето потребителско име и следната парола за връзка с тази Ampache инсталация:"});
    gettextCatalog.setStrings('bg', {"Albums":"Албуми","Artists":"Изпълнители","Description":"Описание","Description (e.g. App name)":"Описание (пр. име на Приложението)","Generate API password":"Генерирай API парола","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Тук можеш да генерираш пароли, които да използваш с Ampache API, защото те не могат да бъдат съхранени по сигурен начин поради архитектурата на Ampachi API. Можеш да генерираш колко искаш пароли и да ги спираш по всяко време.","Invalid path":"Невалиден път","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Помни, че Ampache API е само предварителна варсия и не е стабилно. Можеш да опишеш своя опит с тази услуга на <a href=\"https://github.com/owncloud/music/issues/60\">следната страница</a>.","Music":"Музика","Next":"Следваща","Path to your music collection":"Пътят към музикалната ти колекция","Pause":"Пауза","Play":"Пусни","Previous":"Предишна","Repeat":"Повтори","Revoke API password":"Премахни API паролата","Shuffle":"Разбъркай","Some not playable tracks were skipped.":"Някои невъзпроизведими песни бяха пропуснати.","This setting specifies the folder which will be scanned for music.":"Тази настройка задава папката, която ще бъде сканирана за музика.","Tracks":"Песни","Unknown album":"Непознат албум","Unknown artist":"Непознат изпълнител","Use this address to browse your music collection from any Ampache compatible player.":"Използвай този адрес, за да разглеждаш музикалната си колекция от всеки съвместим с Ampache музикален плеър.","Use your username and following password to connect to this Ampache instance:":"Използвай своето потребителско име и следната парола за връзка с тази Ampache инсталация:"});
    gettextCatalog.setStrings('bn_BD', {"Albums":"অ্যালবামসমূহ","Artists":"শিল্পীগণ","Description":"বিবরণ","Description (e.g. App name)":"বিবরণ (উদাহরণ: অ্যাপ নাম)","Generate API password":"API কুটশব্দ তৈরী কর","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"এখানে আপনি Ampache API এর জন্য কুটশব্দ তৈরী করতে পারেন কারণ তার ডিজােইনের কারণেই Ampache API কে নিরাপদে সংরক্ষণ করা যায়না। আপনার যত খুশি কুটশব্দ তৈরী করতে পারেন এবং ইচ্ছেমাফিক তাদের বাতিল করতেও পারেন।","Invalid path":"পথটি সঠিক নয়","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"মনে রাখুন যে Ampache API একটি প্রিভিউ মাত্র এবং এটি স্থির কিছু নয়। এতদ্বিষয়ে আপনার অভিজ্ঞতা আমাদের জানাতে <a href=\"https://github.com/owncloud/music/issues/60\">issue</a> ব্যাবহার করুন। এটি পরীক্ষা করার জন্য আমার কিছু ব্যাবহারকারী প্রয়োজন। ধন্যবাদ ","Music":"গানবাজনা","Next":"পরবর্তী","Path to your music collection":"আপনার গানের সংগ্রহের পথ ","Pause":"বিরতি","Play":"বাজাও","Previous":"পূর্ববর্তী","Repeat":"পূনঃসংঘটন","Revoke API password":"API কুটশব্দ বাতিল কর","Shuffle":"এলোমেলো কর","Some not playable tracks were skipped.":"বাজানোর অনুপযোগী কিছু ট্র্যাক এড়িয়ে যাওয়া হয়েছে।","This setting specifies the folder which will be scanned for music.":"এই নিয়ামকটি গান খুজে বের করার জন্য ফোল্ডার নির্ধারণ করে।","Tracks":"ট্র্যাকসমূহ","Unknown album":"অজানা অ্যালবাম","Unknown artist":"অজানা শিল্পী","Use this address to browse your music collection from any Ampache compatible player.":"Ampache compatible player হতে আপনার গানের সংগ্রহ দেখতে এই ঠিকানা ব্যাবহার করুন।","Use your username and following password to connect to this Ampache instance:":"এই  Ampache-টিতে সংযুক্ত হতে আপনার ব্যাবহারকারী নাম ও নীচের কুটশব্দ ব্যাবহার করুন:"});
    gettextCatalog.setStrings('bn_IN', {"Albums":"অ্যালবাম","Artists":"শিল্পী","Description":"বর্ণনা","Description (e.g. App name)":"বর্ণনা (যেমন অ্যাপ নাম)","Generate API password":"এপিআই পাসওয়ার্ড নির্মাণ করা","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"এখানে আপনি আম্পাচে এপিআইের সঙ্গে ব্যবহার করার জন্য পাসওয়ার্ড তৈরি করতে পারেন,কারন তাদের নিরাপদ ভাবে সংরক্ষণ করা যাবে না আম্পাচে এপিআই এর নকশার জন্যে।আপনি যখন ইচ্ছে অনেক পাসওয়ার্ড জেনারেট করতে পারেন এবং যে কোনো সময় তাদের প্রত্যাহার করতে পারেন.","Invalid path":"অবৈধ পথ","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"  মনে রাখবেন যে আম্পাচে এপিআই শুধু একটি প্রাকদর্শন এবং অস্থির।এই বৈশিষ্ট্যের সঙ্গে আপনার অভিজ্ঞতা রিপোর্ট করুন বিনা দ্বিধায় <a href=\"https://github.com/owncloud/music/issues/60\">সংশ্লিষ্ট প্রকাশে</a>।আমি সঙ্গে পরীক্ষা করার জন্য গ্রাহকদের একটি তালিকা চাই।ধন্যবাদ","Music":"সঙ্গীত","Next":"পরবর্তী","Path to your music collection":"আপনার সঙ্গীত সংগ্রহের পথ","Pause":"বিরাম","Play":"প্লে","Previous":"পূর্ববর্তী","Repeat":"পুনরাবৃত্তি","Revoke API password":"এপিআই পাসওয়ার্ড প্রত্যাহার করা","Shuffle":"অদলবদল","Some not playable tracks were skipped.":"কিছু কিছু প্লে করার যোগ্য ট্র্যাক এড়ানো হয়েছে।","This setting specifies the folder which will be scanned for music.":"এই সেটিং ফোল্ডার উল্লেখ করে যেটা সঙ্গীতের জন্য স্ক্যান করা হবে।","Tracks":"সঙ্গীত","Unknown album":"অজানা অ্যালবাম","Unknown artist":"অজানা শিল্পী","Use this address to browse your music collection from any Ampache compatible player.":"কোনো আম্পাচে সামঞ্জস্যপূর্ণ প্লেয়ার থেকে আপনার সঙ্গীত সংগ্রহের এবং ব্রাউজ করার জন্য এই ঠিকানা ব্যবহার করুন।","Use your username and following password to connect to this Ampache instance:":"এই আম্পাচে উদাহরণস্বরূপের সাথে সংযোগ স্থাপন করতে আপনার ব্যবহারকারীর নাম ও নিম্নলিখিত পাসওয়ার্ড ব্যবহার করুন:"});
    gettextCatalog.setStrings('bs', {"Description":"Opis","Next":"Sljedeći","Pause":"Pauza","Play":"Play","Previous":"Prethodno","Repeat":"Ponovi"});
    gettextCatalog.setStrings('ca@valencia', {});
    gettextCatalog.setStrings('ca', {"Albums":"Àlbums","Artists":"Artistes","Description":"Descripció","Description (e.g. App name)":"Descripció (per exemple nom de l'aplicació)","Generate API password":"Genera contrasenya API","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Aquí podeu generar contrasenyes per usar amb l'API d'Ampache,ja que no es poden desar de forma segura degut al diseny de l'API d'Ampache. Podeu generar tantes contrasenyes com volgueu i revocar-les en qualsevol moment.","Invalid path":"El camí no és vàlid","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Recordeu que l'API d'Ampache és només una previsualització i que és inestable. Sou lliures d'informar de la vostra experiència amb aquesta característica en el <a href=\"https://github.com/owncloud/music/issues/60\">fil</a> corresponent. També voldríem tenir una llista de clients per fer proves. Gràcies.","Music":"Música","Next":"Següent","Path to your music collection":"Camí a la col·lecció de música","Pause":"Pausa","Play":"Reprodueix","Previous":"Anterior","Repeat":"Repeteix","Revoke API password":"Revoca la cotrasenya de l'API","Shuffle":"Aleatori","Some not playable tracks were skipped.":"Algunes pistes no reproduïbles s'han omès.","This setting specifies the folder which will be scanned for music.":"Aquest arranjament especifica la carpeta que s'escanejarà en cerca de música","Tracks":"Peces","Unknown album":"Àlbum desconegut","Unknown artist":"Artista desconegut","Use this address to browse your music collection from any Ampache compatible player.":"Utilitza aquesta adreça per navegar per la teva col·lecció de música des de qualsevol reproductor compatible amb Ampache.","Use your username and following password to connect to this Ampache instance:":"Useu el vostre nom d'usuari i contrasenya per connectar amb la instància Ampache:"});
    gettextCatalog.setStrings('cs_CZ', {"+ New Playlist":"+ Nový seznam","Albums":"Alba","All tracks":"Všechny stopy","Artists":"Umělci","Click here to start the scan":"Klikněte zde pro začátek hledání","Description":"Popis","Description (e.g. App name)":"Popis (např. Jméno aplikace)","Generate API password":"Generovat heslo API","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Zde můžete vytvářet hesla pro Ampache API, protože tato nemohou být uložena skutečně bezpečným způsobem z důvodu designu Ampache API. Je možné vygenerovat libovolné množství hesel a kdykoliv je zneplatnit.","Invalid path":"Chybná cesta","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Mějte na paměti, že Ampache API je stále ve vývoji a není stabilní. Můžete nás bez obav informovat o zkušenostech s touto funkcí odesláním hlášení v příslušném <a href=\"https://github.com/owncloud/music/issues/60\">tiketu</a>. Chtěl bych také sestavit seznam zájemců o testování. Díky","Music":"Hudba","New music available":"Nová hudba k dispozici","New music available. Click here to reload the music library.":"Nová hudba k dispozici. Klikněte pro znovunačtení hudební knihovny.","Next":"Následující","No music found":"Hudba nenalezena","Path to your music collection":"Cesta k vlastní sbírce hudby","Pause":"Pozastavit","Play":"Přehrát","Previous":"Předchozí","Repeat":"Opakovat","Revoke API password":"Odvolat heslo API","Scanning music …":"Hledám hudbu …","Shuffle":"Promíchat","Some not playable tracks were skipped.":"Některé stopy byly přeskočeny, protože se nedají přehrát.","This setting specifies the folder which will be scanned for music.":"Toto nastavení určuje adresář, ve kterém bude hledána hudba.","Tracks":"Stopy","Unknown album":"Neznámé album","Unknown artist":"Neznámý umělec","Upload music in the files app to listen to it here":"Nahrát hudbu v aplikaci soubory k poslechu zde","Use this address to browse your music collection from any Ampache compatible player.":"Použijte tuto adresu pro přístup k hudební sbírce z jakéhokoliv přehrávače podporujícího Ampache.","Use your username and following password to connect to this Ampache instance:":"Použijte Vaše uživatelské jméno a následující heslo pro připojení k této instanci Ampache:","Volume":"Hlasitost","tracks":"stopy","{{ scanningScanned }} of {{ scanningTotal }}":"{{ scanningScanned }} z {{ scanningTotal }}"});
    gettextCatalog.setStrings('cs', {"+ New Playlist":"+ Nový seznam","Albums":"Alba","All tracks":"Všechny stopy","Artists":"Umělci","Click here to start the scan":"Klikněte zde pro začátek hledání","Description":"Popis","Description (e.g. App name)":"Popis (např. Jméno aplikace)","Generate API password":"Generovat heslo API","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Zde můžete vytvářet hesla pro Ampache API, protože tato nemohou být uložena skutečně bezpečným způsobem z důvodu designu Ampache API. Je možné vygenerovat libovolné množství hesel a kdykoliv je zneplatnit.","Invalid path":"Chybná cesta","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Mějte na paměti, že Ampache API je stále ve vývoji a není stabilní. Můžete nás bez obav informovat o zkušenostech s touto funkcí odesláním hlášení v příslušném <a href=\"https://github.com/owncloud/music/issues/60\">tiketu</a>. Chtěl bych také sestavit seznam zájemců o testování. Díky","Music":"Hudba","New music available":"Nová hudba k dispozici","New music available. Click here to reload the music library.":"Nová hudba k dispozici. Klikněte pro znovunačtení hudební knihovny.","Next":"Následující","No music found":"Hudba nenalezena","Path to your music collection":"Cesta k vlastní sbírce hudby","Pause":"Pozastavit","Play":"Přehrát","Previous":"Předchozí","Repeat":"Opakovat","Revoke API password":"Odvolat heslo API","Scanning music …":"Hledám hudbu …","Shuffle":"Promíchat","Some not playable tracks were skipped.":"Některé stopy byly přeskočeny, protože se nedají přehrát.","This setting specifies the folder which will be scanned for music.":"Toto nastavení určuje adresář, ve kterém bude hledána hudba.","Tracks":"Stopy","Unknown album":"Neznámé album","Unknown artist":"Neznámý umělec","Upload music in the files app to listen to it here":"Nahrát hudbu v aplikaci soubory k poslechu zde","Use this address to browse your music collection from any Ampache compatible player.":"Použijte tuto adresu pro přístup k hudební sbírce z jakéhokoliv přehrávače podporujícího Ampache.","Use your username and following password to connect to this Ampache instance:":"Použijte Vaše uživatelské jméno a následující heslo pro připojení k této instanci Ampache:","Volume":"Hlasitost","tracks":"stopy","{{ scanningScanned }} of {{ scanningTotal }}":"{{ scanningScanned }} z {{ scanningTotal }}"});
    gettextCatalog.setStrings('cy_GB', {"Description":"Disgrifiad","Music":"Cerddoriaeth","Next":"Nesaf","Pause":"Seibio","Play":"Chwarae","Previous":"Blaenorol","Repeat":"Ailadrodd"});
    gettextCatalog.setStrings('da', {"Albums":"Album","Artists":"Kunstnere","Description":"Beskrivelse","Description (e.g. App name)":"Beskrivelse (f.eks. App-navn)","Generate API password":"Generér API-adgangskode","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Her kan du generere adgangskoder, der bruges med Ampache API'et, da de ikke kan lagres på en rigtig sikker måde, hvilket skyldes designet af Ampache API'et. Du kan generere alle de adgangskoder som du ønsker, og tilbagekalde dem til enhver tid.","Invalid path":"Ugyldig sti","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Det bør holdes for øje, at Ampache API'et er i et meget tidligt stadie og fungerer ustabilt. Du er velkommen til at berette om dine erfaringer med denne funktion i den respektive <a href=\"https://github.com/owncloud/music/issues/60\">sag</a>. Jeg vil også være interesseret i at etablere en kreds af klienter, der kan hjælpe med afprøvninger. Tak","Music":"Musik","Next":"Næste","Path to your music collection":"Sti til dit musikbibliotek ","Pause":"Pause","Play":"Afspil","Previous":"Forrige","Repeat":"Gentag","Revoke API password":"Tilbagekald API-adgangskode","Shuffle":"Bland","Some not playable tracks were skipped.":"Numre som ikke kunne afspilles blev sprunget over.","This setting specifies the folder which will be scanned for music.":"Denne indstilling angiver dén mappe, der vil blive skannet for musik.","Tracks":"Numre","Unknown album":"Ukendt album","Unknown artist":"Ukendt artist","Use this address to browse your music collection from any Ampache compatible player.":"Brug denne adresse til at gennemse din musiksamling fra hvilken som helst Ampache-kompatibel afspiller.","Use your username and following password to connect to this Ampache instance:":"Brug dit brugernavn og følgende adgangskode for at tilslutte til denne Ampache-instans:"});
    gettextCatalog.setStrings('de_AT', {"Description":"Beschreibung","Music":"Musik","Next":"Nächstes","Pause":"Pause","Play":"Abspielen","Previous":"Vorheriges","Repeat":"Wiederholen","Shuffle":"Zufallswiedergabe","Unknown album":"Unbekanntes Album","Unknown artist":"Unbekannter Künstler"});
    gettextCatalog.setStrings('de_CH', {"Description":"Beschreibung","Music":"Musik","Next":"Weiter","Pause":"Anhalten","Play":"Abspielen","Previous":"Vorheriges","Repeat":"Wiederholen"});
    gettextCatalog.setStrings('de_DE', {"+ New Playlist":"+ Neue Wiedergabeliste","Albums":"Alben","All tracks":"Alle Titel","Artists":"Künstler","Click here to start the scan":"Um das Durchsuchen zu starten, hier klicken","Description":"Beschreibung","Description (e.g. App name)":"Beschreibung (z.B. Name der Anwendung)","Generate API password":"API-Passwort erzeugen","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Hier können Sie Passwörter zur Benutzung mit der Ampache-API erzeugen, da diese aufgrund des Designs der Ampache-API auf keine wirklich sichere Art und Weise gespeichert werden können. Sie könenn soviele Passwörter generieren, wie Sie möchten und diese jederzeit verwerfen.","Invalid path":"Ungültiger Pfad","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Bitte bedenken Sie, dass die Ampache-API derzeit eine Vorschau und instabil ist. Sie können gerne Ihre Erfahrungen mit dieser Funktion im entsprechenden <a href=\"https://github.com/owncloud/music/issues/60\">Fehlerbericht</a> melden. Ich würde ebenfalls eine Liste von Anwendung zu Testzwecken sammeln. Dankeschön","Music":"Musik","New music available":"Neue Musik verfügbar","New music available. Click here to reload the music library.":"Neue Musik verfügbar. Zum Neuladen der Medienbibliothek, hier klicken.","Next":"Weiter","No music found":"Keine Musik gefunden","Path to your music collection":"Pfad zu Ihrer Musiksammlung","Pause":"Anhalten","Play":"Abspielen","Previous":"Zurück","Repeat":"Wiederholen","Revoke API password":"API-Passwort verwerfen","Scanning music …":"Untersuche Musik ...","Shuffle":"Zufallswiedergabe","Some not playable tracks were skipped.":"Einige nicht abspielbare Titel wurden übersprungen.","This setting specifies the folder which will be scanned for music.":"Diese Einstellung spezifiziert den zu durchsuchenden Musikordner.","Tracks":"Titel","Unknown album":"Unbekanntes Album","Unknown artist":"Unbekannter Künstler","Upload music in the files app to listen to it here":"Laden Sie Musik in der Dateien-App hoch, um diese hier anzuhören.","Use this address to browse your music collection from any Ampache compatible player.":"Nutzen Sie diese Adresse zum Durchsuchen Ihrer Musiksammlung auf einem beliebigen Ampache-kompatiblen Abspieler.","Use your username and following password to connect to this Ampache instance:":"Benutzen Sie Ihren Benutzernamen und folgendes Passwort, um sich mit dieser Ampache-Instanz zu verbinden:","Volume":"Lautstärke","tracks":"Titel","{{ scanningScanned }} of {{ scanningTotal }}":"{{ scanningScanned }} von {{ scanningTotal }}"});
    gettextCatalog.setStrings('de', {"+ New Playlist":"+ Neue Wiedergabeliste","Albums":"Alben","All tracks":"Alle Titel","Artists":"Künstler","Click here to start the scan":"Um das Durchsuchen zu starten, hier klicken","Description":"Beschreibung","Description (e.g. App name)":"Beschreibung (z.B. Name der Anwendung)","Generate API password":"API Passwort erzeugen","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Hier kannst Du Passwörter zur Benutzung mit der Ampache-API erzeugen, da diese aufgrund des Designs der Ampache-API auf keine wirklich sichere Art und Weise gespeichert werden können. Du kannst soviele Passwörter generieren, wie Du möchtest und diese jederzeit verwerfen.","Invalid path":"Ungültiger Pfad","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Bitte bedenke, dass die Ampache-API derzeit eine Vorschau und instabil ist. Du kannst gerne Deine Erfahrungen mit dieser Funktion im entsprechenden <a href=\"https://github.com/owncloud/music/issues/60\">Fehlerbericht</a> melden. Ich würde ebenfalls eine Liste von Anwendung zu Testzwecken sammeln. Dankeschön","Music":"Musik","New music available":"Neue Musik verfügbar","New music available. Click here to reload the music library.":"Neue Musik verfügbar. Zum Neuladen der Medienbibliothek, hier klicken.","Next":"Weiter","No music found":"Keine Musik gefunden","Path to your music collection":"Pfad zu Deiner Musiksammlung","Pause":"Anhalten","Play":"Abspielen","Previous":"Zurück","Repeat":"Wiederholen","Revoke API password":"API Passwort verwerfen","Scanning music …":"Untersuche Musik ...","Shuffle":"Zufallswiedergabe","Some not playable tracks were skipped.":"Einige nicht abspielbare Titel wurden übersprungen.","This setting specifies the folder which will be scanned for music.":"Diese Einstellung spezifiziert den zu durchsuchenden Musikordner.","Tracks":"Titel","Unknown album":"Unbekanntes Album","Unknown artist":"Unbekannter Künstler","Upload music in the files app to listen to it here":"Laden Sie Musik in der Dateien-App hoch, um diese hier anzuhören.","Use this address to browse your music collection from any Ampache compatible player.":"Nutze diese Adresse zum Durchsuchen Deiner Musiksammlung auf einem beliebigen Ampache-kompatiblen Abspieler.","Use your username and following password to connect to this Ampache instance:":"Nutze Deinen Benutzernamen und folgendes Passwort, um zu dieser Ampache-Instanz zu verbinden:","Volume":"Lautstärke","tracks":"Titel","{{ scanningScanned }} of {{ scanningTotal }}":"{{ scanningScanned }} von {{ scanningTotal }}"});
    gettextCatalog.setStrings('el', {"+ New Playlist":"+ Νέα λίστα αναπαραγωγής","Albums":"Άλμπουμ","All tracks":"Όλα τα κομμάτια","Artists":"Καλλιτέχνες","Click here to start the scan":"Κάντε κλικ εδώ για την εκκίνηση σάρωσης","Description":"Περιγραφή","Description (e.g. App name)":"Περιγραφή (π.χ. όνομα Εφαρμογής)","Generate API password":"Δημιουργία κωδικού πρόσβασης API","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Εδώ μπορείτε να δημιουργήσετε κωδικούς πρόσβασης για χρήση με το API του Ampache, γιατί δεν είναι δυνατό να αποθηκευτούν με πραγματικά ασφαλή τρόπο λόγω της σχεδίασης του API του Ampache. Μπορείτε να δημιουργήσετε όσα συνθηματικά θέλετε και να τα ανακαλέσετε οποτεδήποτε.","Invalid path":"Άκυρη διαδρομή","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Θυμηθείτε ότι το API του Ampache είναι απλά μια προτεπισκόπηση και είναι ασταθές. Παρακαλούμε αναφέρετε την εμπειρία σας με αυτή τη λειτουργία στην αντίστοιχη <a href=\"https://github.com/owncloud/music/issues/60\">αναφορά</a>. Θα ήταν καλό να υπάρχει επίσης μια λίστα με εφαρμογές προς δοκιμή. Ευχαριστούμε!","Music":"Μουσική","New music available":"Διαθέσιμη νέα μουσική","New music available. Click here to reload the music library.":"Διαθέσιμη νέα μουσική. Κάντε κλικ εδώ για να φορτώσετε ξανά τη βιβλιοθήκη μουσικής.","Next":"Επόμενο","No music found":"Δεν βρέθηκε μουσική","Path to your music collection":"Διαδρομή για τη μουσική σας συλλογή","Pause":"Παύση","Play":"Αναπαραγωγή","Previous":"Προηγούμενο","Repeat":"Επανάληψη","Revoke API password":"Ανάκληση κωδικού πρόσβασης API","Scanning music …":"Σάρωση μουσικής ...","Shuffle":"Τυχαία αναπαραγωγή","Some not playable tracks were skipped.":"Μερικά μη αναγνώσιμα τραγούδια έχουν παρακαμφθεί.","This setting specifies the folder which will be scanned for music.":"Αυτή η ρύθμιση προσδιορίζει το φάκελο που θα σαρωθεί για μουσική.","Tracks":"Κομμάτια","Unknown album":"Άγνωστο άλμπουμ","Unknown artist":"Άγνωστος καλλιτέχνης","Upload music in the files app to listen to it here":"Μεταφόρτωση μουσικής στην εφαρμογή μουσικής για ακρόαση εδώ","Use this address to browse your music collection from any Ampache compatible player.":"Χρησιμοποιήστε αυτή τη διεύθυνση για να περιηγηθείτε στη μουσική σας συλλογή από οποιοδήποτε εφαρμογή αναπαραγωγής συμβατή με το Ampache.","Use your username and following password to connect to this Ampache instance:":"Χρησιμοποιήστε το όνομα χρήστη σας και τον παρακάτω κωδικό πρόσβασης για να συνδεθείτε σε αυτή την εγκατάσταση του Ampache:","Volume":"Ένταση","tracks":"κομμάτια","{{ scanningScanned }} of {{ scanningTotal }}":"{{ scanningScanned }} από {{ scanningTotal }}"});
    gettextCatalog.setStrings('en_GB', {"Albums":"Albums","Artists":"Artists","Description":"Description","Description (e.g. App name)":"Description (e.g. App name)","Generate API password":"Generate API password","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.","Invalid path":"Invalid path","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks","Music":"Music","Next":"Next","Path to your music collection":"Path to your music collection","Pause":"Pause","Play":"Play","Previous":"Previous","Repeat":"Repeat","Revoke API password":"Revoke API password","Shuffle":"Shuffle","Some not playable tracks were skipped.":"Some unplayable tracks were skipped.","This setting specifies the folder which will be scanned for music.":"This setting specifies the folder which will be scanned for music.","Tracks":"Tracks","Unknown album":"Unknown album","Unknown artist":"Unknown artist","Use this address to browse your music collection from any Ampache compatible player.":"Use this address to browse your music collection from any Ampache compatible player.","Use your username and following password to connect to this Ampache instance:":"Use your username and following password to connect to this Ampache instance:"});
    gettextCatalog.setStrings('en_NZ', {});
    gettextCatalog.setStrings('eo', {"Albums":"Albumoj","Artists":"Artistoj","Description":"Priskribo","Description (e.g. App name)":"Priskribo (ekz.: aplikaĵonomo)","Invalid path":"Nevalida vojo","Music":"Muziko","Next":"Jena","Path to your music collection":"Vojo al via muzikokolekto","Pause":"Paŭzi...","Play":"Ludi","Previous":"Maljena","Repeat":"Ripeti","Shuffle":"Miksi","Unknown album":"Nekonata albumo","Unknown artist":"Nekonata artisto"});
    gettextCatalog.setStrings('es_AR', {"Albums":"Álbumes","Artists":"Artistas","Description":"Descripción","Description (e.g. App name)":"Descripción (ej. Nombre de la Aplicación)","Generate API password":"Generar contraseña de la API","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Aquí puede generar contraseñas para usar con la API de Ampache, porque no pueden ser guardadas de manera segura por diseño de la API de Ampache. Puede generar tantas contraseñas como quiera y revocarlas todas en cualquier momento.","Invalid path":"Ruta no válida","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Tenga en cuenta que la API de Ampache esta en etapa de prueba y es inestable. Siéntase libre de reportar su experiencia con esta característica en el correspondiente <a href=\"https://github.com/owncloud/music/issues/60\">punto</a>. También me gustaría tener una lista de clientes para probar.  Gracias!.","Music":"Música","Next":"Siguiente","Path to your music collection":"Ruta a tu colección de música.","Pause":"Pausar","Play":"Reproducir","Previous":"Previo","Repeat":"Repetir","Revoke API password":"Revocar contraseña de la API","Shuffle":"Aleatorio","Tracks":"Pistas","Unknown album":"Album desconocido","Unknown artist":"Artista desconocido","Use this address to browse your music collection from any Ampache compatible player.":"Use esta dirección para navegar tu colección de música desde cualquier reproductor compatible con Ampache.","Use your username and following password to connect to this Ampache instance:":"Use su nombre de usuario y la siguiente contraseña para conectar a esta instancia de Ampache:"});
    gettextCatalog.setStrings('es_BO', {});
    gettextCatalog.setStrings('es_CL', {});
    gettextCatalog.setStrings('es_CO', {});
    gettextCatalog.setStrings('es_CR', {});
    gettextCatalog.setStrings('es_EC', {});
    gettextCatalog.setStrings('es_MX', {"Albums":"Álbunes","Artists":"Artistas","Description":"Descripción","Description (e.g. App name)":"Descripción (e.g. Nombre de la aplicación)","Generate API password":"Generar contraseña de API","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Aqui puedes generar contraseñas para usar con la API Ampache, porque no pueden ser guardadas de una manera realmente segura debido al diseño de la API Ampache. Puedes generar tantas contraseñas como lo desees y revocarlas en cualquier momento.","Invalid path":"Ruta no valida","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Tenga en cuenta, que la API Ampache es sólo una vista previa y es inestable. No dude en informar de su experiencia con esta característica en el correspondiente  <a href=\"https://github.com/owncloud/music/issues/60\">asunto</a>. Me gustaría tener una lista de clientes para probarlo. Gracias","Music":"Música","Next":"Siguiente","Path to your music collection":"Ruta de acceso a la colección de música","Pause":"Pausa","Play":"Reproducir","Previous":"Anterior","Repeat":"Repetir","Revoke API password":"Revocar contraseña de API","Shuffle":"Mezclar","Some not playable tracks were skipped.":"Algunas pistas no reproducibles fueron omitidas.","This setting specifies the folder which will be scanned for music.":"Esta configuración especifica la carpeta que será analizada en busca de música.","Tracks":"Pistas","Unknown album":"Álbum desconocido","Unknown artist":"Artista desconocido","Use this address to browse your music collection from any Ampache compatible player.":"Utiliza esta dirección para navegar por tu colección de música desde cualquier reproductor compatible Ampache.","Use your username and following password to connect to this Ampache instance:":"Utiliza tu nombre de usuario seguido de tu contraseña para conectarte a esta instancia de Ampache"});
    gettextCatalog.setStrings('es_PE', {});
    gettextCatalog.setStrings('es_PY', {});
    gettextCatalog.setStrings('es_US', {});
    gettextCatalog.setStrings('es_UY', {});
    gettextCatalog.setStrings('es', {"+ New Playlist":"+ Nueva Lista de reproducción","Albums":"Álbumes","All tracks":"Todas las pistas","Artists":"Artistas","Click here to start the scan":"Clic para iniciar la busqueda","Description":"Descripción","Description (e.g. App name)":"Descripción (p.ej., nombre de la aplicación)","Generate API password":"Generar contraseña para la API","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Aquí se pueden crear contraseñas para usarlas con el API de Ampache. Dado que el diseño del API de Ampache no permite almacenar contraseñas de manera segura, se pueden generar tantas contraseñas como sea necesario, así como revocarlas en cualquier momento.","Invalid path":"Ruta inválida","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Recuerde que el API de Ampache solo es un prototipo y es inestable. Puede reportar su experiencia con esta nueva funcionalidad en el <a href=\"https://github.com/owncloud/music/issues/60\">informe de error</a> correspondiente. También quisiera tener una lista de clientes con quienes probarla. Gracias.","Music":"Música","New music available":"Nueva musica disponible","New music available. Click here to reload the music library.":"Nueva musica disponible. Clic para recargar la libreria de musica.","Next":"Siguiente","No music found":"No se ha encontrado musica","Path to your music collection":"Ruta a su colección de música","Pause":"Pausa","Play":"Reproducir","Previous":"Anterior","Repeat":"Repetir","Revoke API password":"Revocar contraseña para la API","Scanning music …":"Buscando Musica ...","Shuffle":"Mezclar","Some not playable tracks were skipped.":"No se pudieron reproducir algunas canciones.","This setting specifies the folder which will be scanned for music.":"Esta configuración especifica la carpeta en la cual se escaneará la música","Tracks":"Audios","Unknown album":"Álbum desconocido","Unknown artist":"Artista desconocido","Upload music in the files app to listen to it here":"Suba musica en la aplicación para escucharla desde aqui.","Use this address to browse your music collection from any Ampache compatible player.":"Use esta dirección para explorar su colección de música desde cualquier reproductor compatible con Ampache.","Use your username and following password to connect to this Ampache instance:":"Use su nombre de usuario y la siguiente contraseña para conectarse con esta instancia de Ampache:","Volume":"Volumen","tracks":"Pistas","{{ scanningScanned }} of {{ scanningTotal }}":"{{ scanningScanned }} de {{ scanningTotal }}"});
    gettextCatalog.setStrings('et_EE', {"+ New Playlist":"+ Uus esitusloend","Albums":"Albumid","All tracks":"Kõik lood","Artists":"Artistid","Click here to start the scan":"Skännimise alustamiseks kliki siia","Description":"Kirjeldus","Description (e.g. App name)":"Kirjeldus (nt. rakendi nimi)","Generate API password":"Tekita API parool","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Siin sa saad tekitada parooli, mida kasutada Ampache API-ga, kuid neid ei ole võimalik talletada turvalisel moel Ampache API olemuse tõttu. Sa saad genereerida nii palju paroole kui soovid ning tühistada neid igal ajal.","Invalid path":"Vigane tee","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Pea meeles, et Ampache APi on lihtsalt eelvaade ning see pole stabiilne. Anna teada oma kogemustest selle funktsionaalsusega vastavalt <a href=\"https://github.com/owncloud/music/issues/60\">teemaarendusele</a>. Ühtlasi soovin nimistut klientidest, mida testida. Tänan.","Music":"Muusika","New music available":"Saadaval on uut muusikat","New music available. Click here to reload the music library.":"Saadaval on uut muusikat. Kliki siia, et muusikakogu uuesti laadida.","Next":"Järgmine","No music found":"Muusikat ei leitud","Path to your music collection":"Tee sinu muusikakoguni","Pause":"Paus","Play":"Esita","Previous":"Eelmine","Repeat":"Korda","Revoke API password":"Keeldu API paroolist","Scanning music …":"Muusika otsimine ...","Shuffle":"Juhuslik esitus","Some not playable tracks were skipped.":"Mõned mittemängitavad lood jäeti vahele.","This setting specifies the folder which will be scanned for music.":"See seade määrab kausta, kust muusikat otsitakse.","Tracks":"Lood","Unknown album":"Tundmatu album","Unknown artist":"Tundmatu esitaja","Upload music in the files app to listen to it here":"Laadi muusika üles failide rakenduses, et seda siin kuulata","Use this address to browse your music collection from any Ampache compatible player.":"Kasuta seda aadressi sirvimaks oma muusikakogu suvalisest Ampache-ga ühilduvast muusikapleierist.","Use your username and following password to connect to this Ampache instance:":"Kasuta oma kasutajatunnust ja järgmist parooli ühendumaks selle Ampache instantsiga:","Volume":"Helitugevus","tracks":"lugu","{{ scanningScanned }} of {{ scanningTotal }}":"{{ scanningScanned }} / {{ scanningTotal }}"});
    gettextCatalog.setStrings('eu_ES', {"Description":"Deskripzioa","Music":"Musika","Next":"Aurrera","Pause":"geldi","Play":"jolastu","Previous":"Atzera","Repeat":"Errepikatu"});
    gettextCatalog.setStrings('eu', {"Albums":"Diskak","Artists":"Artistak","Description":"Deskribapena","Description (e.g. App name)":"Deskribapena (adb. App izena)","Generate API password":"Sortu API pasahitza","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Hemen Ampache APIrekin erabiltzeko pasahitzak sor ditzazkezu. Hauek ezin dira modu benetan seguru batean gorde Ampache APIren diseinua dela eta, honengatik nahi duzun pashitz aina sor ditzakezu eta nahi duzunean deuseztatu.","Invalid path":"Baliogabeko bidea","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Gogoratu Ampache APIa aurreikuspen bat besterik ez dela eta ez dela egonkorra.  Mesdez emazu aukera honekiko zure esperientziaren berri dagokion <a href=\"https://github.com/owncloud/music/issues/60\">lekuan</a>. Gustatuko litzaidake ere bezero zerrenda bat izatea probak egin ahal izateko. Milesker","Music":"Musika","Next":"Hurrengoa","Path to your music collection":"Musika bildumaren bidea","Pause":"Pausarazi","Play":"Erreproduzitu","Previous":"Aurrekoa","Repeat":"Errepikatu","Revoke API password":"Ezeztatu API pasahitza","Shuffle":"Nahastu","Some not playable tracks were skipped.":"Erreproduzitu ezin ziren pista batzuk saltatu egin dira.","This setting specifies the folder which will be scanned for music.":"Hemen musika bilatuko den karpetak zehazten dira.","Tracks":"Pistak","Unknown album":"Diska ezezaguna","Unknown artist":"Artista ezezaguna","Use this address to browse your music collection from any Ampache compatible player.":"Erabili helbide hau zure musika bilduma Ampacherekin bateragarria den edozein erreproduktorekin arakatzeko.","Use your username and following password to connect to this Ampache instance:":"Erabili zure erabiltzaile izena eta hurrengo pasahitza Ampache honetara konektatzeko:"});
    gettextCatalog.setStrings('fa', {"Albums":"آلبوم ها","Artists":"هنرمندان","Description":"توضیحات","Description (e.g. App name)":"توضیحات (همانند نام برنامه)","Generate API password":"تولید رمزعبور API ","Invalid path":"مسیر اشتباه","Music":"موزیک","Next":"بعدی","Pause":"توقف کردن","Play":"پخش کردن","Previous":"قبلی","Repeat":"تکرار","Shuffle":"درهم","Unknown album":"آلبوم نامشخص"});
    gettextCatalog.setStrings('fi_FI', {"Albums":"Albumit","Artists":"Esittäjät","Description":"Kuvaus","Description (e.g. App name)":"Kuvaus (esim. sovelluksen nimi)","Generate API password":"Luo API-salasana","Invalid path":"Virheellinen polku","Music":"Musiikki","Next":"Seuraava","Path to your music collection":"Musiikkikokoelman polku","Pause":"Keskeytä","Play":"Toista","Previous":"Edellinen","Repeat":"Kertaa","Revoke API password":"Kumoa API-salasana","Shuffle":"Sekoita","Some not playable tracks were skipped.":"Ohitettiin joitain sellaisia kappaleita, joita ei voi toistaa.","This setting specifies the folder which will be scanned for music.":"Tämä asetus määrittää kansion, josta musiikkia etsitään.","Tracks":"Kappaleet","Unknown album":"Tuntematon albumi","Unknown artist":"Tuntematon esittäjä","Use this address to browse your music collection from any Ampache compatible player.":"Käytä tätä osoitetta selataksesi musiikkikokoelmaasi miltä tahansa Ampache-yhteensopivalta soittimelta.","Use your username and following password to connect to this Ampache instance:":"Käytä käyttäjätunnustasi ja seuraavaa salasanaa yhditäessäsi tähän Ampache-istuntoon:"});
    gettextCatalog.setStrings('fi', {"Albums":"Albumit","Artists":"Esittäjät","Description":"Kuvaus","Description (e.g. App name)":"Kuvaus (esim. sovelluksen nimi)","Generate API password":"Luo API-salasana","Invalid path":"Virheellinen polku","Music":"Musiikki","Next":"Seuraava","Path to your music collection":"Musiikkikokoelman polku","Pause":"Keskeytä","Play":"Toista","Previous":"Edellinen","Repeat":"Kertaa","Revoke API password":"Kumoa API-salasana","Shuffle":"Sekoita","Some not playable tracks were skipped.":"Ohitettiin joitain sellaisia kappaleita, joita ei voi toistaa.","This setting specifies the folder which will be scanned for music.":"Tämä asetus määrittää kansion, josta musiikkia etsitään.","Tracks":"Kappaleet","Unknown album":"Tuntematon albumi","Unknown artist":"Tuntematon esittäjä","Use this address to browse your music collection from any Ampache compatible player.":"Käytä tätä osoitetta selataksesi musiikkikokoelmaasi miltä tahansa Ampache-yhteensopivalta soittimelta.","Use your username and following password to connect to this Ampache instance:":"Käytä käyttäjätunnustasi ja seuraavaa salasanaa yhditäessäsi tähän Ampache-istuntoon:"});
    gettextCatalog.setStrings('fil', {});
    gettextCatalog.setStrings('fr_CA', {});
    gettextCatalog.setStrings('fr', {"+ New Playlist":"+ Nouvelle liste de lecture","Albums":"Albums","All tracks":"Toutes les pistes","Artists":"Artistes","Click here to start the scan":"Cliquez ici pour lancer le balayage","Description":"Description","Description (e.g. App name)":"Description (ex. nom de l'application)","Generate API password":"Générer un mot de passe de l'API","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Ici, vous pouvez générer des mots de passe à utiliser avec l'API Ampache, parce qu'ils ne peuvent être stockés d'une manière sécurisée en raison de la conception de l'API d'Ampache. Vous pouvez générer autant de mots de passe que vous voulez et vous pouvez les révoquer à tout instant.","Invalid path":"Chemin non valide","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Gardez en mémoire que l'API Ampache est une avant-première et n'est pas encore stable. N'hésitez pas à donner un retour d'expérience de cette fonctionnalité <a href=\"https://github.com/owncloud/music/issues/60\">sur la page dédiée</a>. On aimerait également obtenir une liste des clients avec lesquels tester. Merci.","Music":"Musique","New music available":"Nouvelles pistes disponibles","New music available. Click here to reload the music library.":"Nouvelles pistes disponibles. Cliquer ici pour recharger la bibliothèque musicale.","Next":"Suivant","No music found":"Aucune piste trouvée","Path to your music collection":"Chemin vers votre collection de musique","Pause":"Pause","Play":"Lire","Previous":"Précédent","Repeat":"Répéter","Revoke API password":"Révoquer le mot de passe de l'API","Scanning music …":"Balayage de la musique …","Shuffle":"Lecture aléatoire","Some not playable tracks were skipped.":"Certaines pistes non jouables ont été ignorées.","This setting specifies the folder which will be scanned for music.":"Ce paramètre spécifie quel dossier sera balayé pour trouver de la musique.","Tracks":"Pistes","Unknown album":"Album inconnu","Unknown artist":"Artiste inconnu","Upload music in the files app to listen to it here":"Téléversez de la musique dans l'application Fichiers pour l'écouter ici","Use this address to browse your music collection from any Ampache compatible player.":"Utilisez cette adresse pour naviguer dans votre collection musicale avec un client compatible Ampache.","Use your username and following password to connect to this Ampache instance:":"Utilisez votre nom d'utilisateur et le mot de passe suivant pour vous connecter à cette instance d'Ampache : ","Volume":"Volume","tracks":"pistes","{{ scanningScanned }} of {{ scanningTotal }}":"{{ scanningScanned }} sur {{ scanningTotal }}"});
    gettextCatalog.setStrings('fy_NL', {});
    gettextCatalog.setStrings('gl', {"Albums":"Albumes","Artists":"Interpretes","Description":"Descrición","Description (e.g. App name)":"Descrición (p.ex. o nome da aplicación)","Generate API password":"Xerar o contrasinal da API","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Aquí pode xerar contrasinais para utilizar coa API de Ampache, xa que non poden ser almacenados nunha forma abondo segura por mor do deseño da API de Ampache. Pode xerar tantos contrasinais como queira e revogalos en calquera momento.","Invalid path":"Ruta incorrecta","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Teña presente que a API de Ampache é só unha edición preliminar e é inestábel. Non dubide en informarnos da súa experiencia con esta característica na correspondente páxina de  <a href=\"https://github.com/owncloud/music/issues/60\">incidencias</a>. Gustaríanos tamén, ter unha lista de clientes cos que facer probas. Grazas","Music":"Música","Next":"Seguinte","Path to your music collection":"Ruta á súa colección de música","Pause":"Pausa","Play":"Reproducir","Previous":"Anterior","Repeat":"Repetir","Revoke API password":"Revogar o contrasinal da API","Shuffle":"Ao chou","Some not playable tracks were skipped.":"Omitíronse algunhas pistas non reproducíbeis.","This setting specifies the folder which will be scanned for music.":"Este axuste especifica o cartafol que será analizado na busca de música.","Tracks":"Pistas","Unknown album":"Álbum descoñecido","Unknown artist":"Interprete descoñecido","Use this address to browse your music collection from any Ampache compatible player.":"Utilice este enderezo para navegar pola súa colección de música desde calquera reprodutor compatíbel con Ampache.","Use your username and following password to connect to this Ampache instance:":"Utilice o seu nome de usuario e o seguinte contrasinal para conectarse a esta instancia do Ampache:"});
    gettextCatalog.setStrings('gu', {});
    gettextCatalog.setStrings('he', {"+ New Playlist":"+ רשימת השמעה חדשה","Albums":"אלבומים","All tracks":"כל רצועות המוזיקה","Artists":"אומנים","Click here to start the scan":"לחיצה כאן להתחלת הסריקה","Description":"תיאור","Description (e.g. App name)":"תיאור (לדוגמא שם אפליקציה)","Generate API password":"יצירת סיסמאות API","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"כאן ניתן ליצור סיסמאות לשימוש ב- Ampache API, כיוון שלא ניתן לאחסן אותן בצורה בטוחה בשל העיצוב של ה- Ampache API. ניתן ליצור מספר לא מוגבל של סיסמאות ולבטל אותן בכל זמן.","Invalid path":"נתיב לא חוקי","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"יש לזכור שה- Ampache API הוא רק קדימון והוא אינו יציב. ניתן לדווח את ההתרשמות מתכונה זו בתכתובת <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. כמו כן נשמח לקבל רשימת לקוחות לבדיקה איתם. תודה","Music":"מוזיקה","New music available":"קיימת מוזיקה חדשה","New music available. Click here to reload the music library.":"קיימת מוזיקה חדשה. יש ללחוץ כאן לטעינה מחדש של ספריית המוזיקה.","Next":"הבא","No music found":"לא נמצאה מוזיקה","Path to your music collection":"נתיב לאוסף המוזיקה שלך","Pause":"השהה","Play":"נגן","Previous":"קודם","Repeat":"חזרה","Revoke API password":"ביטול סיסמת API","Scanning music …":"סורק מוזיקה...","Shuffle":"ערבב","Some not playable tracks were skipped.":"מספר קטעי מוסיקה לא תקינים דולגו","This setting specifies the folder which will be scanned for music.":"ההגדרות של התיקייה עליה תבוצע סריקת המוזיקה","Tracks":"קטעי מוסיקה","Unknown album":"אלבום לא ידוע","Unknown artist":"אמן לא ידוע","Upload music in the files app to listen to it here":"יש להעלות מוזיקה ביישום הקבצים להשמעה שלה כאן","Use this address to browse your music collection from any Ampache compatible player.":"ניתן להשתמש בכתובת זו לעיון בספריית המוזיקה שלך מכל נגן התומך באפצ'י.","Use your username and following password to connect to this Ampache instance:":"השתמשו בשם המשתמש שלכם ובסיסמא הבאה לחיבור למריץ אפצ'י זה:","Volume":"עוצמת שמע","tracks":"רצועות מוזיקה","{{ scanningScanned }} of {{ scanningTotal }}":"{{ scanningScanned }} מתוך {{ scanningTotal }}"});
    gettextCatalog.setStrings('hi_IN', {});
    gettextCatalog.setStrings('hi', {"Albums":"एलबम","Artists":"कलाकारों","Description":"विवरण","Music":"गाना","Next":"अगला"});
    gettextCatalog.setStrings('hr', {"Albums":"Albumi","Artists":"Izvođači","Description":"Opis","Description (e.g. App name)":"Opis (primjer: ime aplikacije)","Generate API password":"Generiraj API lozinku","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Ovo je mjesto gdje možete generirati svoju lozinku za Ampache API, iz razloga što ne mogu biti pohranjene sigurno radi dizajna Ampache API -a. Možeš generirati nebrojeno lozinki i povući ih u bilo koje vrijeme.","Invalid path":"Pogrešna putanja","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Imajte na umu, da je Ampache API tek probna verzija i poprilično je nestabilna. Slobodno opišite svoje iskustvo sa ovom značajkom u priradajući <a href=\"https://github.com/owncloud/music/issues/60\">problem</a> . Također, htjeli bi imati listu klijenata za testiranje. Hvala","Music":"Muzika","Next":"Sljedeća","Path to your music collection":"Putanja do tvoje baze muzike","Pause":"Pauza","Play":"Reprodukcija","Previous":"Prethodna","Repeat":"Ponavljanje","Revoke API password":"Povuci API lozinku","Shuffle":"Slučajni izbor","Some not playable tracks were skipped.":"Trake koje se ne mogu reproducirati, preskočene su","This setting specifies the folder which will be scanned for music.":"Ova postavka specificira folder koji će biti pretražen za muziku","Tracks":"Trake","Unknown album":"Nepoznati album","Unknown artist":"Nepoznati izvođač","Use this address to browse your music collection from any Ampache compatible player.":"Upotrijebi ovu adresu kada želiš vidjeti svoju glazbenu kolekciju sa bilo kojeg Ampache kompatibilnog uređaja","Use your username and following password to connect to this Ampache instance:":"upotrijebi svoje korisničko ime i sljedeću lozinku kako bi se spojio na Ampache instancu:"});
    gettextCatalog.setStrings('hu_HU', {"Albums":"Albumok","Artists":"Előadók","Description":"Leírás","Description (e.g. App name)":"Leírás (például az alkalmazás neve)","Generate API password":"API-jelszó előállítása","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Itt hozhat létre jelszavakat, amikkel távolról használhatja az Ampache szolgáltatást. Azért van szükség másik jelszóra, mert az Amapche protokoll miatt a használt jelszó nem tárolható igazán biztonságosan. Bármikor visszavonhatja az Ampache jelszavát és újat hozhat létre (sőt tobbfélét is használhat a különböző eszközeihez).","Invalid path":"Érvénytelen útvonal","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Kérjük vegye figyelembe, hogy az Ampache támogatás még nem tekinthető stabilnak, ez még csak tesztváltozat. <a href=\"https://github.com/owncloud/music/issues/60\">Ezen a webcímen</a> számolhat be a tapasztalatairól. Jó lenne minél több kliensprogrammal tesztelni a szolgáltatást. Köszönöm!","Music":"Zene","Next":"Következő","Path to your music collection":"A zenegyűjtemény útvonala","Pause":"Szünet","Play":"Lejátszás","Previous":"Előző","Repeat":"Ismétlés","Revoke API password":"API-jelszó visszavonása","Shuffle":"Keverés","Some not playable tracks were skipped.":"Néhány szám kimaradt, amit a rendszer nem tud lejátszani.","This setting specifies the folder which will be scanned for music.":"Ez a beállítás határozza meg, hogy melyik mappát figyelje a rendszer, amikor az zenei tartalmakat keresi.","Tracks":"Számok","Unknown album":"Ismeretlen album","Unknown artist":"Ismeretlen előadó","Use this address to browse your music collection from any Ampache compatible player.":"Ezt a címet használva a zenegyűjtemény bármely Ampache-kompatibilis lejátszóval böngészhető.","Use your username and following password to connect to this Ampache instance:":"Használja a felhasználónevét és a következő jelszót, ha csatlakozni kíván ehhez az Ampache kiszolgálóhoz:"});
    gettextCatalog.setStrings('hu', {"Albums":"Albumok","Artists":"Előadók","Description":"Leírás","Description (e.g. App name)":"Leírás (például az alkalmazás neve)","Generate API password":"API-jelszó előállítása","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Itt hozhat létre jelszavakat, amikkel távolról használhatja az Ampache szolgáltatást. Azért van szükség másik jelszóra, mert az Amapche protokoll miatt a használt jelszó nem tárolható igazán biztonságosan. Bármikor visszavonhatja az Ampache jelszavát és újat hozhat létre (sőt tobbfélét is használhat a különböző eszközeihez).","Invalid path":"Érvénytelen útvonal","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Kérjük vegye figyelembe, hogy az Ampache támogatás még nem tekinthető stabilnak, ez még csak tesztváltozat. <a href=\"https://github.com/owncloud/music/issues/60\">Ezen a webcímen</a> számolhat be a tapasztalatairól. Jó lenne minél több kliensprogrammal tesztelni a szolgáltatást. Köszönöm!","Music":"Zene","Next":"Következő","Path to your music collection":"A zenegyűjtemény útvonala","Pause":"Szünet","Play":"Lejátszás","Previous":"Előző","Repeat":"Ismétlés","Revoke API password":"API-jelszó visszavonása","Shuffle":"Keverés","Some not playable tracks were skipped.":"Néhány szám kimaradt, amit a rendszer nem tud lejátszani.","This setting specifies the folder which will be scanned for music.":"Ez a beállítás határozza meg, hogy melyik mappát figyelje a rendszer, amikor az zenei tartalmakat keresi.","Tracks":"Számok","Unknown album":"Ismeretlen album","Unknown artist":"Ismeretlen előadó","Use this address to browse your music collection from any Ampache compatible player.":"Ezt a címet használva a zenegyűjtemény bármely Ampache-kompatibilis lejátszóval böngészhető.","Use your username and following password to connect to this Ampache instance:":"Használja a felhasználónevét és a következő jelszót, ha csatlakozni kíván ehhez az Ampache kiszolgálóhoz:"});
    gettextCatalog.setStrings('hy', {"Albums":"Ալբոմներ","Artists":"Արտիստներ","Description":"Նկարագրություն","Description (e.g. App name)":"Նկարագրություն (օր.՝ App name)","Generate API password":"Գեներացնել API գաղտնաբառ","Invalid path":"Անվավեր ուղի","Music":"Երաժշտություն","Next":"Հաջորդ","Path to your music collection":"Քո երաժշտական հավաքածուի ուղին","Play":"Նվագարկել","Previous":"Նախորդ","Repeat":"Կրկնել","Shuffle":"Խառը","Unknown album":"Անհայտ ալբոմ","Unknown artist":"Անհայտ հեղինակ"});
    gettextCatalog.setStrings('ia', {"Description":"Description","Music":"Musica","Next":"Proxime","Pause":"Pausa","Play":"Reproducer","Previous":"Previe","Repeat":"Repeter"});
    gettextCatalog.setStrings('id', {"Albums":"Album","Artists":"Pembuat","Description":"Keterangan","Description (e.g. App name)":"Keterangan (cth. nama Aplikasi)","Generate API password":"Hasilkan sandi API","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Disini Anda dapat menghasilkan sandi untuk digunakan dengan Ampache API, karena mereka tidak dapat disimpan dengan cara yang benar-benar aman karena desain Ampache API. Anda dapat menghasilkan banyak sandi yang Anda inginkan dan mencabut mereka kapan saja.","Invalid path":"Jalur lokasi salah.","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Harus diingat, bahwa Ampache API hanya pratinjau dan tidak stabil. Jangan ragu untuk melaporkan pengalaman Anda dengan fitur ini di <a href=\"https://github.com/owncloud/music/issues/60\">masalah yang sesuai</a>. Saya juga ingin memiliki daftar klien untuk menguji dengannya. Terima kasih","Music":"Musik","Next":"Berikutnya","Path to your music collection":"Jalur lokasi koleksi musik Anda","Pause":"Jeda","Play":"Putar","Previous":"Sebelumnya","Repeat":"Ulangi","Revoke API password":"Cabut sandi API","Shuffle":"Acak","Some not playable tracks were skipped.":"Beberapa trek yang tidak diputar akan dilewati.","This setting specifies the folder which will be scanned for music.":"Pengaturan ini menentukan folder yang akan dipindai untuk musik.","Tracks":"Trek","Unknown album":"Album tidak diketahui","Unknown artist":"Pembuat tidak diketahui","Use this address to browse your music collection from any Ampache compatible player.":"Gunakan alamat ini untuk meramban koleksi musik Anda dari pemutar yang kompatibel dengan Ampache.","Use your username and following password to connect to this Ampache instance:":"Gunakan nama pengguna dan sandi berikut untuk terhubung dengan instansi Ampache:"});
    gettextCatalog.setStrings('io', {});
    gettextCatalog.setStrings('is', {"+ New Playlist":"+ Nýr spilunarlisti","Albums":"Albúm","All tracks":"Öll lög","Artists":"Flytjandi","Click here to start the scan":"Smelltu hér til að hefja skönnun","Description":"Lýsing","Description (e.g. App name)":"Lýsing (t.d. heiti á forriti)","Generate API password":"Framleiða API-lykilorð","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Hér geturðu útbúið lykilorð til að nota með Ampache API, því ekki er hægt að geyma þau á algerlega öruggan máta vegna uppbyggingar Ampache API-samskiptareglnanna. Þú getur búið til eins mörg lykilorð og þér sýnist, og afturkallað þau hvenær sem er.","Invalid path":"Ógild slóð","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Hafðu í huga að Ampache API er bara sýnishorn og að það er óstöðugt. Þú mátt alveg segja frá reynslu þinni af notkun þess á tilheyrandi <a href=\"https://github.com/owncloud/music/issues/60\">færslu</a>. Það væri einnig gott að fá með lista yfir þau forrit sem hafa verið prófuð. Takk fyrir","Music":"Tónlist","New music available":"Ný tónlist í boði","New music available. Click here to reload the music library.":"Ný tónlist í boði. Smelltu hér til að endurhlaða tónlistarsafninu.","Next":"Næst","No music found":"Engin tónlist fannst","Path to your music collection":"Slóð á tónlistarsafnið þitt","Pause":"Í bið","Play":"Spila","Previous":"Fyrra","Repeat":"Endurtaka","Revoke API password":"Afturkalla API-lykilorð","Scanning music …":"Skanna tónlist …","Shuffle":"Stokka","Some not playable tracks were skipped.":"Sumum óspilanlegum hljóðsporum var sleppt.","This setting specifies the folder which will be scanned for music.":"Þetta tilgreinir möppuna þar sem leitað verður að tónlist.","Tracks":"Hljóðspor","Unknown album":"Óþekkt albúm","Unknown artist":"Óþekktur flytjandi","Upload music in the files app to listen to it here":"Sendu inn tónlist í skráaforritinu til að hlusta á hana hér","Use this address to browse your music collection from any Ampache compatible player.":"Notaðu þetta vistfang til að vafra um tónlistarsafnið þitt í öllum Ampache-samhæfðum spilurum.","Use your username and following password to connect to this Ampache instance:":"Notaðu notandanafn þitt og eftirfarandi aðgangsorð til að tengjast þessu Ampache-tilviki:","Volume":"Hljóðstyrkur","tracks":"lög","{{ scanningScanned }} of {{ scanningTotal }}":"{{ scanningScanned }} af {{ scanningTotal }}"});
    gettextCatalog.setStrings('it', {"+ New Playlist":"+ Nuova scaletta","Albums":"Album","All tracks":"Tutte le tracce","Artists":"Artisti","Click here to start the scan":"Fai clic qui per iniziare la scansione","Description":"Descrizione","Description (e.g. App name)":"Descrizione (ad es. Nome applicazione)","Generate API password":"Genera una password API","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Qui puoi generare le password da utilizzare con l'API di Ampache, perché esse non possono essere memorizzate in maniera sicura a causa della forma dell'API di Ampache. Puoi generare tutte le password che vuoi e revocarle quando vuoi.","Invalid path":"Percorso non valido","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Ricorda, l'API di Ampache è solo un'anteprima e non è stabile. Sentiti libero di segnalare la tua esperienza con questa funzionalità nel corrispondente <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. Preferirei inoltre avere un elenco di client da provare. Grazie.","Music":"Musica","New music available":"Nuova musica disponibile","New music available. Click here to reload the music library.":"Nuova musica disponibile. Fai clic qui per ricaricare la raccolta musicale.","Next":"Successivo","No music found":"Nessun musica trovata","Path to your music collection":"Percorso alla tua collezione musicale","Pause":"Pausa","Play":"Riproduci","Previous":"Precedente","Repeat":"Ripeti","Revoke API password":"Revoca la password API","Scanning music …":"Scansione della musica...","Shuffle":"Mescola","Some not playable tracks were skipped.":"Alcune tracce non riproducibili sono state saltate.","This setting specifies the folder which will be scanned for music.":"Questa impostazione specifica la cartella che sarà analizzata alla ricerca di musica.","Tracks":"Tracce","Unknown album":"Album sconosciuto","Unknown artist":"Artista sconosciuto","Upload music in the files app to listen to it here":"Carica la musica nella App File per sentirla da qui","Use this address to browse your music collection from any Ampache compatible player.":"Usa questo indirizzo per sfogliare le tue raccolte musicali da qualsiasi lettore compatibile con Ampache.","Use your username and following password to connect to this Ampache instance:":"Utilizza il tuo nome utente e la password per collegarti a questa istanza di Ampache:","Volume":"Volume","tracks":"tracce","{{ scanningScanned }} of {{ scanningTotal }}":"{{ scanningScanned }} di {{ scanningTotal }}"});
    gettextCatalog.setStrings('ja_JP', {"+ New Playlist":"＋新しいプレイリスト","Albums":"アルバム","All tracks":"すべてのトラック","Artists":"アーティスト","Click here to start the scan":"ここをクリックしてスキャン開始","Description":"説明","Description (e.g. App name)":"説明 (例えばアプリケーション名)","Generate API password":"APIパスワードの生成","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"ここでは、Ampache APIに使用するパスワードを生成することができます。Ampache API のパスワードを本当に安全な方法では保管することができないからです。いつでも望むままに、いくつものパスワードを生成したり、それらを無効にしたりすることができます。","Invalid path":"無効なパス","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Ampache APIはまだプレビュー版で、まだ不安定ですので、注意してください。この機能について、 <a href=\"https://github.com/owncloud/music/issues/60\">issue</a> への動作結果報告を歓迎します。テスト済クライアントのリストも作成したいと考えていますので、よろしくお願いいたします。","Music":"ミュージック","New music available":"新しいミュージックが利用可能です","New music available. Click here to reload the music library.":"新しいミュージックが利用可能です。ここをクリックしてミュージックライブラリをリロード。","Next":"次","No music found":"ミュージックが見つかりません","Path to your music collection":"音楽コレクションのパス","Pause":"一時停止","Play":"再生","Previous":"前","Repeat":"繰り返し","Revoke API password":"API パスワードを無効にする","Scanning music …":"ミュージックをスキャン中...","Shuffle":"シャッフル","Some not playable tracks were skipped.":"一部の再生不可能なトラックをスキップしました。","This setting specifies the folder which will be scanned for music.":"この設定では、音楽ファイルをスキャンするフォルダーを指定します。","Tracks":"トラック","Unknown album":"不明なアルバム","Unknown artist":"不明なアーティスト","Upload music in the files app to listen to it here":"ファイルアプリからミュージックをアップロードするとここで聴けます。","Use this address to browse your music collection from any Ampache compatible player.":"あなたの音楽コレクションをAmpache対応プレイヤーから閲覧するには、このアドレスを使用してください。","Use your username and following password to connect to this Ampache instance:":"このAmpacheインスタンスに接続するには、あなたのユーザー名と以下のパスワードを使用してください:","Volume":"音量","tracks":"トラック"});
    gettextCatalog.setStrings('jv', {"Music":"Gamelan","Next":"Sak bare","Play":"Puter","Previous":"Sak durunge"});
    gettextCatalog.setStrings('ka_GE', {"Description":"გვერდის დახასიათება","Music":"მუსიკა","Next":"შემდეგი","Pause":"პაუზა","Play":"დაკვრა","Previous":"წინა","Repeat":"გამეორება"});
    gettextCatalog.setStrings('ka', {});
    gettextCatalog.setStrings('km', {"Albums":"អាល់ប៊ុម","Artists":"សិល្បករ","Description":"ការ​អធិប្បាយ","Description (e.g. App name)":"ការ​អធិប្បាយ (ឧ. ឈ្មោះ​កម្មវិធី)","Generate API password":"បង្កើត​ពាក្យ​សម្ងាត់ API","Invalid path":"ទីតាំង​មិន​ត្រឹម​ត្រូវ","Music":"តន្ត្រី","Next":"បន្ទាប់","Pause":"ផ្អាក","Play":"លេង","Previous":"មុន","Repeat":"ធ្វើម្ដងទៀត","Shuffle":"បង្អូស","Tracks":"បទ","Unknown album":"អាល់ប៊ុមអត់​ឈ្មោះ","Unknown artist":"សិល្បករអត់​ឈ្មោះ"});
    gettextCatalog.setStrings('kn', {"Next":"ಮುಂದೆ"});
    gettextCatalog.setStrings('ko', {"+ New Playlist":"+ 새 재생 목록","Albums":"앨범","All tracks":"모든 곡","Artists":"음악가","Click here to start the scan":"검색을 시작하려면 누르십시오","Description":"설명","Description (e.g. App name)":"설명(예: 앱 이름)","Generate API password":"API 암호 생성","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Ampache API가 설계된 방법 때문에 Ampache API에 사용할 암호를 완전히 안전한 형태로 저장할 수 없습니다. Ampache API에 사용할 암호를 여기에서 생성하십시오. 필요한 만큼 암호를 생성하고 언제든지 취소할 수 있습니다.","Invalid path":"잘못된 경로","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Ampache API는 아직까지 완전하지 않습니다. 이 기능을 사용하면서 느낀 점을 <a href=\"https://github.com/owncloud/music/issues/60\">보고</a>해 주십시오. 테스트할 클라이언트에 대해서 알려 주셔도 좋습니다. 감사합니다.","Music":"음악","New music available":"새 음악을 사용할 수 있음","New music available. Click here to reload the music library.":"새 음악을 사용할 수 있습니다. 여기를 누르면 라이브러리를 새로 고칩니다.","Next":"다음","No music found":"음악을 찾을 수 없음","Path to your music collection":"내 음악 모음집 경로","Pause":"일시 정지","Play":"재생","Previous":"이전","Repeat":"반복","Revoke API password":"API 암호 취소","Scanning music …":"음악 검색 중 …","Shuffle":"임의 재생","Some not playable tracks were skipped.":"재생할 수 없는 곡을 건너뛰었습니다.","This setting specifies the folder which will be scanned for music.":"이 설정은 음악을 검색할 폴더를 지정합니다.","Tracks":"곡","Unknown album":"알 수 없는 앨범","Unknown artist":"알 수 없는 음악가","Upload music in the files app to listen to it here":"파일 앱에 음악을 업로드하면 여기에서 들을 수 있습니다","Use this address to browse your music collection from any Ampache compatible player.":"Ampache와 호환되는 음악 재생기에 이 주소를 입력하면 음악 모음집을 들을 수 있습니다.","Use your username and following password to connect to this Ampache instance:":"이 Ampache 인스턴스에 연결하려면 사용자 이름과 다음 암호를 사용하십시오:","Volume":"음량","tracks":"곡","{{ scanningScanned }} of {{ scanningTotal }}":"{{ scanningScanned }}/{{ scanningTotal }}"});
    gettextCatalog.setStrings('ku_IQ', {"Description":"پێناسه","Music":"مۆسیقا","Next":"دوواتر","Pause":"وه‌ستان","Play":"لێدان","Previous":"پێشووتر"});
    gettextCatalog.setStrings('lb', {"Albums":"Album","Artists":"Artist","Description":"Beschreiwung","Music":"Musek","Next":"Weider","Pause":"Paus","Play":"Ofspillen","Previous":"Zeréck","Repeat":"Widderhuelen"});
    gettextCatalog.setStrings('lo', {});
    gettextCatalog.setStrings('lt_LT', {"Albums":"Albumai","Artists":"Atlikėjai","Description":"Aprašymas","Generate API password":"Sugeneruoti API slaptažodį","Invalid path":"Netinkamas kelias","Music":"Muzika","Next":"Kitas","Pause":"Pristabdyti","Play":"Groti","Previous":"Ankstesnis","Repeat":"Kartoti","Shuffle":"Maišyti","Unknown album":"Nežinomas albumas","Unknown artist":"Nežinomas atlikėjas"});
    gettextCatalog.setStrings('lv', {"Description":"Apraksts","Music":"Mūzika","Next":"Nākamā","Pause":"Pauzēt","Play":"Atskaņot","Previous":"Iepriekšējā","Repeat":"Atkārtot"});
    gettextCatalog.setStrings('mg', {});
    gettextCatalog.setStrings('mk', {"Albums":"Албуми","Artists":"Артисти","Description":"Опис","Description (e.g. App name)":"Опис (нпр. име на апликацијата)","Generate API password":"Генерирај API лозинка","Invalid path":"Грешна патека","Music":"Музика","Next":"Следно","Path to your music collection":"Патека до вашата музичка колекција","Pause":"Пауза","Play":"Пушти","Previous":"Претходно","Repeat":"Повтори","Revoke API password":"Отповикај ја API лозинката","Shuffle":"Помешај","Some not playable tracks were skipped.":"Некои песни кои не можеа да се пуштат беа прескокнати.","This setting specifies the folder which will be scanned for music.":"Овие поставки го одредуваат фолдерот кој ќе биде прегледан за музика.","Tracks":"Песна","Unknown album":"Непознат албум","Unknown artist":"Непознат артист"});
    gettextCatalog.setStrings('ml_IN', {"Music":"സംഗീതം","Next":"അടുത്തത്","Pause":" നിറുത്ത്","Play":"തുടങ്ങുക","Previous":"മുന്‍പത്തേത്"});
    gettextCatalog.setStrings('ml', {});
    gettextCatalog.setStrings('mn', {"Albums":"Цомог","Artists":"Хамтлаг/Дуучин","Description":"Тайлбар"});
    gettextCatalog.setStrings('mr', {});
    gettextCatalog.setStrings('ms_MY', {"Description":"Keterangan","Music":"Muzik","Next":"Seterus","Pause":"Jeda","Play":"Main","Previous":"Sebelum","Repeat":"Ulang","Shuffle":"Kocok"});
    gettextCatalog.setStrings('mt_MT', {});
    gettextCatalog.setStrings('my_MM', {"Albums":"သီချင်းခွေများ","Artists":"အဆိုတော်များ","Description":"ဖော်ပြချက်","Description (e.g. App name)":"ဖော်ြပချက်(ဥပမာ App ကိုအမည်)","Generate API password":"API ၏ လျှို့ဝှက်သ‌ေကၤတ ကို အလိုလျှာက်ြပပါ","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Ampache API နှင့် အသုံးပြုရန် စကားဝှက်များကို ဒီနေရာတွင် ဖန်တီးပါ အ‌‌ေြကာင်းက Ampache API ၏ ဒီဇိုင်း ‌ေြကာင့် သူတို့ကို လုံြခံု အောင် မသိမ်းပေးထားနိုင်ပါ။ သင့် စိတ်ြကိုက် စကားဝှက် တွေ လိုသလောက် ဖန်တီးပါ မလိုချိန်မှာ အချိန်မရွေး ြပန်ဖျက်နိုင်ပါတယ်။","Invalid path":"လမ်း‌ေြကာင်းမှား","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"စိတ်ထဲ မှတ်ထား ပေး ပါ၊ Ampache API သည် အြပသက်သက် အဆင့် ပဲ ရှိသေးတာမို့ တည်ြငိမ်မှု မရှိေသးပါ။ သင့် သဘောကျ  ဆီလျှာ် တဲ့ <a href=\"https://github.com/owncloud/music/issues/60\"> အ‌ချက် </a>အြဖဟ် သင့် အတွေ့ အၾကံု ကို သတင်းပိုေပး ပါ။ သုံးစွဲသူ အများ နှင့် စမ်းသပ် ရတာ ကျွန်ုပ် လဲ နှစ်သက် ပါတယ်။ ကျေးဇူးပါ","Music":"သီခ်င္း","Next":"ရှေ့သို့","Path to your music collection":"သင် ၏ တေးဂီတ စုစည်းမှု လမ်း‌ေြကာင်း","Pause":"ခေတ္တရပ်","Play":"ဖွင့်","Previous":"နောက်သို့","Repeat":"အြကိမ်ြကိမ်","Revoke API password":"API စကားဝှက်ကိုပြန်ရုတ်သိမ်း","Shuffle":"ရောမွှေ","Some not playable tracks were skipped.":"တချို့ သော အပုဒ်များ ဖွင့်မရ၍ ကျော်ခဲ့သည်။","This setting specifies the folder which will be scanned for music.":"ဒီ အထူးပြု ဆက်တင် ရှိ ဖိုဒါ မှ တေးသီချင်း ကိုရှာဖွေပါလိမ့်မည်။","Tracks":"အပုဒ်များ","Unknown album":"အမည်မသိ သီချင်းခွေ","Unknown artist":"အမည်မသိ အဆိုတော်","Use this address to browse your music collection from any Ampache compatible player.":"Ampache နှင့် သဟဇာတ ြဖစ်သော မည်သည့် သီချင်းဖွင့် စက် မှ မဆို ဤ လိပ်စာကို သုံး၍ သင့် သီချင်း စုစည်းမှု့ ကို ရယူပါ။","Use your username and following password to connect to this Ampache instance:":"သင့် အသုံးြပုသူ အမည် နှင့် အောက်ဖော်ြပ ပါ လှို့ဝှက် စကား ကို ရိုက်၍ Ampache နှင့် ချက်ချင်း ချိတ်ဆက်ပါ"});
    gettextCatalog.setStrings('nb_NO', {"+ New Playlist":"+ Ny spilleliste","Albums":"Album","All tracks":"Alle spor","Artists":"Artister","Click here to start the scan":"Klikk her for å starte skanningen","Description":"Beskrivelse","Description (e.g. App name)":"Beskrivelse (f.eks. applikasjonsnavn)","Generate API password":"Generer API-passord","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Her kan du generere passord som kan brukes med Ampache API, fordi de ikke kan lagres på en virkelig sikker måte pga. utformingen av Ampache API. Du kan generere så mange passord som du vil og trekke dem tilbake når som helst.","Invalid path":"Individuell sti","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Vær klar over at Ampache API bare er en forhåndsversjon og er ustabil. Rapporter gjerne dine erfaringer med denne funksjonen i den tilhørende <a href=\"https://github.com/owncloud/music/issues/60\">saken</a>. Jeg vil også gjerne ha en liste over klienter som jeg kan teste med. Takk","Music":"Musikk","New music available":"ny musikk tilgjengelig","New music available. Click here to reload the music library.":"Ny musikk er tilgengelig. Klikk her for å laste biblioteket på nytt.","Next":"Neste","No music found":"Ingen musikk funnet","Path to your music collection":"Sti til din musikksamling","Pause":"Pause","Play":"Spill","Previous":"Forrige","Repeat":"Gjenta","Revoke API password":"Tilbakestill API-passord","Scanning music …":"Skanner musikk ...","Shuffle":"Tilfeldig","Some not playable tracks were skipped.":"Noen ikke-spillbare spor ble hoppet over.","This setting specifies the folder which will be scanned for music.":"Denne innstillingen spesifiserer mappen som vil bli skannet for musikk.","Tracks":"Spor","Unknown album":"Ukjent album","Unknown artist":"Ukjent artist","Upload music in the files app to listen to it here":"Last opp musikk i Filer-appen for å lytte til den her","Use this address to browse your music collection from any Ampache compatible player.":"Bruk denne adressen til å bla gjennom din musikksamling fra hvilket som helst Ampache-kompitabelt lag.","Use your username and following password to connect to this Ampache instance:":"Benytt ditt brukernavn og følgende passord for å koble til denne Ampache-forekomsten:","Volume":"Lydstyrke","tracks":"spor","{{ scanningScanned }} of {{ scanningTotal }}":"{{ scanningScanned }} av {{ scanningTotal }}"});
    gettextCatalog.setStrings('nb', {"+ New Playlist":"+ Ny spilleliste","Albums":"Album","All tracks":"Alle spor","Artists":"Artister","Click here to start the scan":"Klikk her for å starte skanningen","Description":"Beskrivelse","Description (e.g. App name)":"Beskrivelse (f.eks. applikasjonsnavn)","Generate API password":"Generer API-passord","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Her kan du generere passord som kan brukes med Ampache API, fordi de ikke kan lagres på en virkelig sikker måte pga. utformingen av Ampache API. Du kan generere så mange passord som du vil og trekke dem tilbake når som helst.","Invalid path":"Individuell sti","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Vær klar over at Ampache API bare er en forhåndsversjon og er ustabil. Rapporter gjerne dine erfaringer med denne funksjonen i den tilhørende <a href=\"https://github.com/owncloud/music/issues/60\">saken</a>. Jeg vil også gjerne ha en liste over klienter som jeg kan teste med. Takk","Music":"Musikk","New music available":"ny musikk tilgjengelig","New music available. Click here to reload the music library.":"Ny musikk er tilgengelig. Klikk her for å laste biblioteket på nytt.","Next":"Neste","No music found":"Ingen musikk funnet","Path to your music collection":"Sti til din musikksamling","Pause":"Pause","Play":"Spill","Previous":"Forrige","Repeat":"Gjenta","Revoke API password":"Tilbakestill API-passord","Scanning music …":"Skanner musikk ...","Shuffle":"Tilfeldig","Some not playable tracks were skipped.":"Noen ikke-spillbare spor ble hoppet over.","This setting specifies the folder which will be scanned for music.":"Denne innstillingen spesifiserer mappen som vil bli skannet for musikk.","Tracks":"Spor","Unknown album":"Ukjent album","Unknown artist":"Ukjent artist","Upload music in the files app to listen to it here":"Last opp musikk i Filer-appen for å lytte til den her","Use this address to browse your music collection from any Ampache compatible player.":"Bruk denne adressen til å bla gjennom din musikksamling fra hvilket som helst Ampache-kompitabelt lag.","Use your username and following password to connect to this Ampache instance:":"Benytt ditt brukernavn og følgende passord for å koble til denne Ampache-forekomsten:","Volume":"Lydstyrke","tracks":"spor","{{ scanningScanned }} of {{ scanningTotal }}":"{{ scanningScanned }} av {{ scanningTotal }}"});
    gettextCatalog.setStrings('nds', {"Next":"Nächtes","Pause":"Pause","Play":"Play","Previous":"Vorheriges"});
    gettextCatalog.setStrings('ne', {});
    gettextCatalog.setStrings('nl', {"+ New Playlist":"+ Nieuwe afspeellijst","Albums":"Albums","All tracks":"Alle nummers","Artists":"Artiesten","Click here to start the scan":"Klik hier om de scan te starten","Description":"Beschrijving","Description (e.g. App name)":"Beschrijving (bijv. appnaam)","Generate API password":"Genereren API wachtwoord","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Hier kunt u wachtwoorden genereren voor gebruik met de Ampache API, omdat ze door het ontwerp van de Ampache API niet op een echt veilige manier kunnen worden bewaard. U kunt zoveel wachtwoorden genereren als u wilt en ze op elk moment weer intrekken.","Invalid path":"Ongeldig pad","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Vergeet niet dat de Ampache API volop in ontwikkeling is en dus instabiel is. Rapporteer gerust uw ervaringen met deze functionaliteit in deze <a href=\"https://github.com/owncloud/music/issues/60\">melding</a>. Ik zou ook graag een lijst met clients hebben om te kunnen testen. Bij voorbaat dank!","Music":"Muziek","New music available":"Nieuwe muziek beschikbaar","New music available. Click here to reload the music library.":"Nieuwe muziek beschikbaar. Klik hier om de muziekbibliotheek te herladen.","Next":"Volgende","No music found":"Geen muziek gevonden","Path to your music collection":"Pad naar uw muziekverzameling","Pause":"Pause","Play":"Afspelen","Previous":"Vorige","Repeat":"Herhaling","Revoke API password":"Intrekken API wachtwoord","Scanning music …":"Scannen muziek …","Shuffle":"Shuffle","Some not playable tracks were skipped.":"Sommige niet af te spelen nummers werden overgeslagen.","This setting specifies the folder which will be scanned for music.":"De instelling bepaalt de map die wordt gescand op muziek.","Tracks":"Nummers","Unknown album":"Onbekend album","Unknown artist":"Onbekende artiest","Upload music in the files app to listen to it here":"Upload muziek in de bestandsapp, om hier te luisteren","Use this address to browse your music collection from any Ampache compatible player.":"Gebruik dit adres om door uw muziekverzameling te bladeren vanaf elke Ampache compatibele speler.","Use your username and following password to connect to this Ampache instance:":"Gebruik uw gebruikersnaam en het volgende wachtwoord om te verbinden met deze Ampache installatie:","Volume":"Volume","tracks":"nummers","{{ scanningScanned }} of {{ scanningTotal }}":"{{ scanningScanned }} van {{ scanningTotal }}"});
    gettextCatalog.setStrings('nn_NO', {"Description":"Skildring","Music":"Musikk","Next":"Neste","Pause":"Pause","Play":"Spel","Previous":"Førre","Repeat":"Gjenta"});
    gettextCatalog.setStrings('nqo', {});
    gettextCatalog.setStrings('oc', {"Albums":"Albums","Artists":"Artistas","Description":"Descripcion","Description (e.g. App name)":"Descripcion (ex. nom de l'aplicacion)","Generate API password":"Generar un senhal de l'API","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Aicí, podètz generar de senhals d'utilizar amb l'API Ampache, perque pòdon èsser emmagazinats d'un biais securizat en rason de la concepcion de l'API d'Ampache. Podètz generar autant de senhals coma volètz e los podètz revocar a tot moment.","Invalid path":"Camin invalid","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Gardatz en memòria que l'API Ampache es una avantprimièra e es pas encara establa. Trantalhetz pas a donar un retorn d'experiéncia d'aquesta foncionalitat <a href=\"https://github.com/owncloud/music/issues/60\">sus la pagina dedicada</a>. Nos agradariá tanbzn d'obténer una lista dels clients amb los quals podèm testar. Mercé.","Music":"Musica","Next":"Seguent","Path to your music collection":"Camin cap a vòstra colleccion de musica","Pause":"Pausa","Play":"Legir","Previous":"Precedent","Repeat":"Repetir","Revoke API password":"Revocar lo senhal de l'API","Shuffle":"Lectura aleatòria","Some not playable tracks were skipped.":"Certanas pistas pas jogables son estadas ignoradas.","This setting specifies the folder which will be scanned for music.":"Aqueste paramètre especifica quin dorsièr serà balejat per trobar de musica.","Tracks":"Pistas","Unknown album":"Album desconegut","Unknown artist":"Artista desconegut","Use this address to browse your music collection from any Ampache compatible player.":"Utilizatz aquesta adreça per navigar dins vòstra colleccion musicala amb un client compatible Ampache.","Use your username and following password to connect to this Ampache instance:":"Utilizatz vòstre nom d'utilizaire e lo senhal seguent per vos connectar a aquesta instància d'Ampache : "});
    gettextCatalog.setStrings('or_IN', {});
    gettextCatalog.setStrings('pa', {"Music":"ਸੰਗੀਤ"});
    gettextCatalog.setStrings('pl', {"+ New Playlist":"+ Nowa Lista odtwarzania","Albums":"Albumy","All tracks":"Wszystkie ściezki","Artists":"Artyści","Click here to start the scan":"Kliknij tutaj aby rozpocząć skanowanie","Description":"Opis","Description (e.g. App name)":"Opis (np. Nazwa aplikacji)","Generate API password":"Wygeneruj hasło API","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Tutaj możesz wygenerować hasła do używania API Ampache, ponieważ nie mogą one być przechowywane w rzeczywiście bezpieczny sposób z powodu architektury API Ampache. Możesz wygenerować tyle haseł ile chcesz i odwołać je w dowolnym momencie.","Invalid path":"niewłaściwa ścieżka","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Miej na uwadze, że API Ampache jest tylko poglądowe i niestabilne. Możesz swobodnie raportować swoje doświadczenia z tą funkcją w odpowiednim <a href=\"https://github.com/owncloud/music/issues/60\">dokumencie</a>. Chciałbym mieć również listę klientów z którymi będę przeprowadzać testy. Dzięki","Music":"Muzyka","New music available":"Dostępne są nowe utwory","New music available. Click here to reload the music library.":"Dostępne są nowe utwory. Kliknij tutaj aby załadować ponownie bibliotekę muzyki.","Next":"Następny","No music found":"Nie znaleziono muzyki","Path to your music collection":"Ścieżka do Twojej kolekcji muzyki","Pause":"Wstrzymaj","Play":"Odtwarzaj","Previous":"Poprzedni","Repeat":"Powtarzaj","Revoke API password":"Odwołaj hasło API","Scanning music …":"Skanowanie muzyki ...","Shuffle":"Losowo","Some not playable tracks were skipped.":"Niektóre nieodtwarzalne ścieżki zostały pominięte.","This setting specifies the folder which will be scanned for music.":"To ustawienie określa folder, który będzie skanowany pod kątem muzyki.","Tracks":"Utwory","Unknown album":"Nieznany album","Unknown artist":"Nieznany artysta","Use this address to browse your music collection from any Ampache compatible player.":"Użyj tego adresu aby przeglądać swoją kolekcję muzyczną na dowolnym odtwarzaczu kompatybilnym z Ampache.","Use your username and following password to connect to this Ampache instance:":"Użyj nazwy użytkownika i następującego hasła do połączenia do tej instancji Ampache:","Volume":"Głośność","tracks":"ścieżki","{{ scanningScanned }} of {{ scanningTotal }}":"{{ scanningScanned }} z {{ scanningTotal }}"});
    gettextCatalog.setStrings('pt_BR', {"+ New Playlist":"+ Nova playlist","Albums":"Albuns","All tracks":"Todas as faixas","Artists":"Artistas","Click here to start the scan":"Clique aqui para iniciar a busca","Description":"Descrição","Description (e.g. App name)":"Descrição (por exemplo, nome do App)","Generate API password":"Gerar senha API","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Aqui você pode gerar senhas para usar com a API Ampache, porque eles não podem ser armazenados de uma forma muito segura devido ao design da API Ampache. Você pode gerar o maior número de senhas que você quiser e revogá-las a qualquer hora.","Invalid path":"Caminho inválido","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Tenha em mente, que a API Ampache é apenas uma pré-visualização e é instável. Sinta-se livre para relatar sua experiência com esse recurso na questão correspondente <a href=\"https://github.com/owncloud/music/issues/60\">assunto</a>. Eu também gostaria de ter uma lista de clientes para testar. obrigado","Music":"Música","New music available":"Nova música disponível","New music available. Click here to reload the music library.":"Nova música disponível. Clique aqui para recarregar a coleção.","Next":"Próxima","No music found":"Nenhuma música encontrada","Path to your music collection":"Caminho para a sua coleção de músicas","Pause":"Pausa","Play":"Reproduzir","Previous":"Anterior","Repeat":"Repetir","Revoke API password":"Revogar senha API","Scanning music …":"Buscando música ...","Shuffle":"Embaralhar","Some not playable tracks were skipped.":"Algumas faixas não reproduzíveis ​​foram ignoradas.","This setting specifies the folder which will be scanned for music.":"Esta configuração especifica a pasta que será escaneada por músicas.","Tracks":"Trilhas","Unknown album":"Album desconhecido","Unknown artist":"Artista desconhecido","Upload music in the files app to listen to it here":"Envie músicas com o aplicativo de arquivos para escutá-las aqui","Use this address to browse your music collection from any Ampache compatible player.":"Utilize este endereço para navegar por sua coleção de música a partir de qualquer leitor compatível com Ampache.","Use your username and following password to connect to this Ampache instance:":"Use o seu nome de usuário e senha a seguir para se conectar a essa instância Ampache:","Volume":"Volume","tracks":"faixas","{{ scanningScanned }} of {{ scanningTotal }}":"{{ scanningScanned }} de {{ scanningTotal }}"});
    gettextCatalog.setStrings('pt_PT', {"+ New Playlist":"+ Nova Lista de Reprodução","Albums":"Álbuns","All tracks":"Todas as faixas","Artists":"Artistas","Click here to start the scan":"Clique aqui para iniciar a pesquisa","Description":"Descrição","Description (e.g. App name)":"Descrição (ex: Nome da Aplicação)","Generate API password":"Gerar palavra-passe da API","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Aqui pode gerar as palavras-passe para utilizar com a API do Ampache, porque elas não podem ser guardadas de um modo realmente seguro devido ao desenho da API do Ampache. Pode gerar as palavras-passe que quiser e revogá-las em qualquer altura.","Invalid path":"Caminho inválido","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Lembre-se que a API do Ampache é apenas provisória e instável. Esteja à vontade para relatar a sua experiência com esta característica na <a href=\"https://github.com/owncloud/music/issues/60\">questão</a> correspondente. Também gostaria de ter uma lista de clientes para testar. Obrigado","Music":"Música","New music available":"Nova música disponivel","New music available. Click here to reload the music library.":"Nova música disponivel. Clique aqui para recarregar a biblioteca de músicas.","Next":"Seguinte","No music found":"Nenhuma música encontrada","Path to your music collection":"Caminho para a sua coleção de música","Pause":"Pausa","Play":"Reproduzir","Previous":"Anterior","Repeat":"Repetir","Revoke API password":"Revogar palavra-passe da API","Scanning music …":"A pesquisar música...","Shuffle":"Baralhar","Some not playable tracks were skipped.":"Foram ignoradas algumas faixas com problemas","This setting specifies the folder which will be scanned for music.":"Esta definição especifica a pasta onde vai ser rastreada a música.","Tracks":"Faixas","Unknown album":"Álbum desconhecido","Unknown artist":"Artista desconhecido","Upload music in the files app to listen to it here":"Envie música na aplicação de ficheiros para as ouvir aqui","Use this address to browse your music collection from any Ampache compatible player.":"Utilize este endereço para navegar na sua coleção de música em qualquer leitor compatível com o Ampache.","Use your username and following password to connect to this Ampache instance:":"Use o seu nome de utilizador e a seguinte palavra-passe para ligar a esta instância do Ampache:","Volume":"Volume","tracks":"faixas","{{ scanningScanned }} of {{ scanningTotal }}":"{{ scanningScanned }} de {{ scanningTotal }}"});
    gettextCatalog.setStrings('ro', {"Albums":"Albume","Artists":"Artiști","Description":"Descriere","Description (e.g. App name)":"Descriere (ex. Numele aplicației)","Generate API password":"Generează parolă API","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Aici poți genera parole pentru a le folosi în API-ul Ampache, deoarece ele nu pot fi stocate într-un mod securizat din cauza construcției API-ului Ampache. Poți genera oricâte parole vrei și le poți revoca oricând.","Invalid path":"Cale invalidă","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Ține minte faptul că API-ul Ampache este doar în perioada de test și nu este stabil. Simte-te liber să ne împărtășești experiențele tale cu această funcționalitate la această <a href=\"https://github.com/owncloud/music/issues/60\">pagină</a>. Am dori de asemenea să avem o listă cu clienți pe care să putem efectua teste. Mulțumim","Music":"Muzică","Next":"Următor","Path to your music collection":"Calea spre colecția cu muzica dvs.","Pause":"Pauză","Play":"Redă","Previous":"Anterior","Repeat":"Repetă","Revoke API password":"Revocă parola API","Shuffle":"Amestecă","Some not playable tracks were skipped.":"Unele piese care nu pot fi redate au fost sărite.","This setting specifies the folder which will be scanned for music.":"Această setare specifică directorul în care se vor căuta fișiere audio.","Tracks":"Piese","Unknown album":"Album necunoscut","Unknown artist":"Artist necunoscut","Use this address to browse your music collection from any Ampache compatible player.":"Folosește această adresă pentru a naviga în colecția ta muzicală din orice unealtă de redare audio.","Use your username and following password to connect to this Ampache instance:":"Folosește numele tău de utilizator și parola următoare pentru a te conecta la această instanță Ampache:"});
    gettextCatalog.setStrings('ru_RU', {"Delete":"Удалить","Music":"Музыка"});
    gettextCatalog.setStrings('ru', {"+ New Playlist":"Новый список проигрывания","Albums":"Альбомы","All tracks":"Все дорожки","Artists":"Исполнители","Click here to start the scan":"Кликните здесь, чтобы начать сканирование","Description":"Описание","Description (e.g. App name)":"Описание (например Название приложения)","Generate API password":"Генерация пароля для API","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Здесь вы можете генерировать пароли для использования API Ampache, так как они не могут быть сохранены действительно безопасным способом из-за особенностей API Ampache. Вы можете создать столько паролей, сколько необходимо, и отказаться от них в любое время.","Invalid path":"Некорректный путь","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Следует помнить, что API Ampache является предварительной и поэтому неустойчивой реализацией. Не стесняйтесь делиться опытом работы с этой функцией в соответствующем <a href=\"https://github.com/owncloud/music/issues/60\">разделе</a>. Я также хотел бы создать список клиентов для тестирования. Спасибо","Music":"Музыка","New music available":"Доступна новая музыка","New music available. Click here to reload the music library.":"Доступна новая музыка. Кликните здесь, чтобы перезагрузить музыкальную библиотеку.","Next":"Следующий","No music found":"Музыка не найдена","Path to your music collection":"Путь до вашей музыкальной коллекции","Pause":"Пауза","Play":"Проиграть","Previous":"Предыдущий","Repeat":"Повтор","Revoke API password":"Отозвать пароль для API","Scanning music …":"Сканирую музыку…","Shuffle":"Перемешать","Some not playable tracks were skipped.":"Некоторые не проигрываемые композиции были пропущены.","This setting specifies the folder which will be scanned for music.":"Эта настройка определяет каталог, в котором будет проведено сканирование музыки.","Tracks":"Композиции","Unknown album":"Неизвестный альбом","Unknown artist":"Неизвестный исполнитель","Upload music in the files app to listen to it here":"Закачайте музыку в приложении Файлы, чтобы послушать её здесь","Use this address to browse your music collection from any Ampache compatible player.":"Используйте этот адрес, чтобы просмотреть вашу музыкальную коллекцию с любого плеера совместимого с Ampache.","Use your username and following password to connect to this Ampache instance:":"Используйте свой логин и пароль ниже для подключения к данному экземпляру Ampache:","Volume":"Громкость","tracks":"дорожки","{{ scanningScanned }} of {{ scanningTotal }}":"{{ scanningScanned }} из {{ scanningTotal }}"});
    gettextCatalog.setStrings('si_LK', {"Description":"විස්තරය","Music":"සංගීතය","Next":"ඊලඟ","Pause":"විරාමය","Play":"ධාවනය","Previous":"පෙර","Repeat":"පුනරාවර්ථන"});
    gettextCatalog.setStrings('sk_SK', {"Albums":"Albumy","Artists":"Interpreti","Description":"Popis","Description (e.g. App name)":"Popis (napr. App name)","Generate API password":"Vygenerovanie hesla API","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Tu môžete vytvárať heslá pre Ampache API, pretože tieto nemôžu byť uložené skutočne bezpečným spôsobom z dôvodu dizajnu Ampache API. Je možné vygenerovať ľubovoľné množstvo hesiel a kedykoľvek ich zneplatniť.","Invalid path":"Neplatná cesta","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Myslite na to, že Ampache API je stále vo vývoji a nie je stabilné. Môžete nás informovať o skúsenostiach s touto funkciou odoslaním hlásenia v príslušnom <a href=\"https://github.com/owncloud/music/issues/60\">tikete</a>. Chcel by som tiež zostaviť zoznam záujemcov o testovanie. Vďaka","Music":"Hudba","Next":"Ďalšia","Path to your music collection":"Cesta k vašej hudobnej zbierke","Pause":"Pauza","Play":"Prehrať","Previous":"Predošlá","Repeat":"Opakovať","Revoke API password":"Zneplatniť heslo API","Shuffle":"Zamiešať","Some not playable tracks were skipped.":"Niektoré neprehrateľné skladby boli vynechané.","This setting specifies the folder which will be scanned for music.":"Toto nastavenie určuje priečinok, v ktorom bude vyhľadaná hudba.","Tracks":"Skladby","Unknown album":"Neznámy album","Unknown artist":"Neznámy umelec","Use this address to browse your music collection from any Ampache compatible player.":"Použite túto adresu pre prístup k hudobnej zbierke z akéhokoľvek prehrávača podporujúceho Ampache.","Use your username and following password to connect to this Ampache instance:":"Použite svoje používateľské meno a heslo pre pripojenie k tejto inštancii Ampache:"});
    gettextCatalog.setStrings('sk', {"Albums":"Albumy","Artists":"Interpreti","Description":"Popis","Description (e.g. App name)":"Popis (napr. App name)","Generate API password":"Vygenerovanie hesla API","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Tu môžete vytvárať heslá pre Ampache API, pretože tieto nemôžu byť uložené skutočne bezpečným spôsobom z dôvodu dizajnu Ampache API. Je možné vygenerovať ľubovoľné množstvo hesiel a kedykoľvek ich zneplatniť.","Invalid path":"Neplatná cesta","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Myslite na to, že Ampache API je stále vo vývoji a nie je stabilné. Môžete nás informovať o skúsenostiach s touto funkciou odoslaním hlásenia v príslušnom <a href=\"https://github.com/owncloud/music/issues/60\">tikete</a>. Chcel by som tiež zostaviť zoznam záujemcov o testovanie. Vďaka","Music":"Hudba","Next":"Ďalšia","Path to your music collection":"Cesta k vašej hudobnej zbierke","Pause":"Pauza","Play":"Prehrať","Previous":"Predošlá","Repeat":"Opakovať","Revoke API password":"Zneplatniť heslo API","Shuffle":"Zamiešať","Some not playable tracks were skipped.":"Niektoré neprehrateľné skladby boli vynechané.","This setting specifies the folder which will be scanned for music.":"Toto nastavenie určuje priečinok, v ktorom bude vyhľadaná hudba.","Tracks":"Skladby","Unknown album":"Neznámy album","Unknown artist":"Neznámy umelec","Use this address to browse your music collection from any Ampache compatible player.":"Použite túto adresu pre prístup k hudobnej zbierke z akéhokoľvek prehrávača podporujúceho Ampache.","Use your username and following password to connect to this Ampache instance:":"Použite svoje používateľské meno a heslo pre pripojenie k tejto inštancii Ampache:"});
    gettextCatalog.setStrings('sl', {"+ New Playlist":"+ Nov seznam predvajanja","Albums":"Albumi","All tracks":"Vsi posnetki","Artists":"Izvajalci","Click here to start the scan":"Kliknite za začetek preiskovanja za nove predmete","Description":"Opis","Description (e.g. App name)":"Opis (na primer ime programa)","Generate API password":"Ustvari geslo API","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"TU je mogoče ustvariti gesla za uporabo z Ampache API, ker jih ni mogoče shraniti na resnično varen način, zaradi programske zasnove Ampache. Dovoljeno je ustvariti poljubno število gesel, do katerih je neomejen dostop.","Invalid path":"Neveljavna pot","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Imejte v mislih, da je Ampache API namenjen le predogledu in ni povsem stabilna programska oprema. Vaših odzivov in izkušenj o uporabi bomo zelo veseli. Objavite jih prek <a href=\"https://github.com/owncloud/music/issues/60\">spletnega obrazca</a>. Priporočljivo je dodati tudi seznam odjemalcev. Za sodelovanje se vam vnaprej najlepše zahvaljujemo.","Music":"Glasba","New music available":"Na voljo je nova glasba","New music available. Click here to reload the music library.":"Na voljo je nova glasba. Kliknite za ponovno nalaganje glasbene zbirke.","Next":"Naslednja","No music found":"Ni zaznanih glasbenih datotek","Path to your music collection":"Pot do zbirke glasbe","Pause":"Premor","Play":"Predvajaj","Previous":"Predhodna","Repeat":"Ponovi","Revoke API password":"Razveljavi geslo API","Scanning music …":"Poteka preiskovanje glasbe ...","Shuffle":"Premešaj","Some not playable tracks were skipped.":"Nekateri posnetki, ki jih ni mogoče predvajati, so bili preskočeni.","This setting specifies the folder which will be scanned for music.":"Nastavitev določa mapo, ki bo preiskana za glasbo.","Tracks":"Sledi","Unknown album":"Neznan album","Unknown artist":"Neznan izvajalec","Use this address to browse your music collection from any Ampache compatible player.":"Uporabite ta naslov za brskanje po zbirki glasbe preko kateregakoli predvajalnika, ki podpira sistem Ampache.","Use your username and following password to connect to this Ampache instance:":"Uporabite uporabniško ime in navedeno geslo za povezavo z Ampache:","Volume":"Glasnost","{{ scanningScanned }} of {{ scanningTotal }}":"{{ scanningScanned }} od {{ scanningTotal }}"});
    gettextCatalog.setStrings('sq', {"+ New Playlist":"+ Luajlistë e Re","Albums":"Albume","All tracks":"Krejt pjesët","Artists":"Artistë","Click here to start the scan":"Klikoni që të fillojë skanimi","Description":"Përshkrim","Description (e.g. App name)":"Përshkrim (p.sh. Emër aplikacioni)","Generate API password":"Prodhoni fjalëkalim API","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Këtu mund të proshoni fjalëkalime për përdoruim me API-n e Ampache-it, ngaqë këta s’mund të depozitohen në mënyrë vërtet të sigurt, për shkak të konceptimit të API-t të Ampache-it. Mund të prodhoni as fjalëkalime të doni, dhe t’i shfuqizoni kur të doni.","Invalid path":"Shteg i pavlefshëm","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Mbani parasysh që API i Ampache-it është thjesht paraprak dhe i paqëndrueshëm. Mos ngurroni të njoftoni përvojën tuaj me këtë veçori te <a href=\"https://github.com/owncloud/music/issues/60\">tema</a> përkatëse. Do të doja të kisha edhe një listë klientësh me të cilën ta testoj. Faleminderit!","Music":"Muzikë","New music available":"Ka gati muzikë të re","New music available. Click here to reload the music library.":"Ka gati muzikë të re. Klikoni këtu që të ringarkohet fonoteka. ","Next":"Pasuesja","No music found":"S’u gjet muzikë","Path to your music collection":"Shteg te koleksioni juaj muzikor","Pause":"Pushim","Play":"Luaje","Previous":"E mëparshmja","Repeat":"Përsërite","Revoke API password":"Shfuqizojeni fjalëkalimin API","Scanning music …":"Po skanohet muzika…","Shuffle":"Përzieji","Some not playable tracks were skipped.":"Disa pjesë që s’luheshin dot u anashkaluan.","This setting specifies the folder which will be scanned for music.":"Ky rregullim përcakton dosjen që do të kontrollohet për muzikë.","Tracks":"Pjesë","Unknown album":"Album i panjohur","Unknown artist":"Artist i panjohur","Upload music in the files app to listen to it here":"Ngarkoni muzikë te aplikacioni i kartelave që ta dëgjoni këtu","Use this address to browse your music collection from any Ampache compatible player.":"Përdoreni këtë adresë që të shfletoni koleksionin tuaj muzikor prej cilitdo luajtësi muzike që funksionon për Ampache.","Use your username and following password to connect to this Ampache instance:":"Përdorni emrin tuaj të përdoruesit dhe fjalëkalimin që të lidheni te kjo instancë Ampache-i:","Volume":"Volum","tracks":"gjurmë","{{ scanningScanned }} of {{ scanningTotal }}":"{{ scanningScanned }} nga {{ scanningTotal }} gjithsej"});
    gettextCatalog.setStrings('sr@latin', {"Albums":"Albumi","Artists":"Izvođači","Description":"Opis","Description (e.g. App name)":"Opis (npr. Ime aplikacije)","Generate API password":"Generiši API lozinku","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Ovde možete da generišete lozinke za korišćenje sa Ampache API-jem za to što one ne mogu biti sačuvane na veoma siguran način zbog dizajna Ampache API-ja. Možete da generišete koliko god želite lozinki i da ih opozovete u bilo kom trenutku.","Invalid path":"Neispravna putanja","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Imajte na umu da je Ampache API samo probna verzija i da je nestabilna. Slobodno prijavite Vaša iskustva sa ovom opcijom obraćajući se na odgovarajuću <a href=\"https://github.com/owncloud/music/issues/60\">stavku</a>. Takođe je dobrodošla lista klijenata za testiranje ove opcije. Hvala","Music":"Muzika","Next":"Sledeća","Path to your music collection":"Putanja do Vaše muzičke kolekcije","Pause":"Pauziraj","Play":"Pusti","Previous":"Prethodna","Repeat":"Ponavljaj","Revoke API password":"Opozovi API lozinku","Shuffle":"Nasumično","Some not playable tracks were skipped.":"Neke numere koje nije bilo moguće pustiti su preskočene.","This setting specifies the folder which will be scanned for music.":"Ovo podešavanje određuje direktorijum koji će biti skeniran u potrazi za muzikom.","Tracks":"Numere","Unknown album":"Nepoznati album","Unknown artist":"Nepoznati izvođač","Use this address to browse your music collection from any Ampache compatible player.":"Koristite ovu adresu da pregledate Vašu muzičku kolekciju iz bilo kog Ampache kompatibilnog plejera.","Use your username and following password to connect to this Ampache instance:":"Koristite Vaše korisničko ime i sledeću lozinku da se povežete na ovu Ampache instancu:"});
    gettextCatalog.setStrings('sr', {"Albums":"Албуми","Artists":"Извођачи","Description":"Опис","Description (e.g. App name)":"Опис (нпр. назив апликације)","Generate API password":"Генериши АПИ лозинку","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Овде можете генерисати лозинке да бисте користили са Ampache API, јер не могу да се чувају у сигуран начин због дизајна Ampache API. Можете генерисати колико желите лозинки и можете их обрисати било када.","Invalid path":"Неисправна путања","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Имајте на уму да је Ампаш АПИ у развоју и да је нестабилан. Слободно пријавите ваша искуства са овом функцијом у одговарајућем <a href=\"https://github.com/owncloud/music/issues/60\">издању</a>. Такође бих желео да имам списак клијената за тестирање. Хвала","Music":"Музика","Next":"Следећа","Path to your music collection":"Путања до ваше музичке колекције","Pause":"Паузирај","Play":"Пусти","Previous":"Претходна","Repeat":"Понављај","Revoke API password":"Опозови АПИ лозинку","Shuffle":"Измешај","Some not playable tracks were skipped.":"Прескочене су нумере које се не могу пустити.","This setting specifies the folder which will be scanned for music.":"Ова поставка наводи фасциклу у којој ће бити тражена музика.","Tracks":"Нумере","Unknown album":"Непознат албум","Unknown artist":"Непознат извођач","Use this address to browse your music collection from any Ampache compatible player.":"Користи ову адресу за прегледање ваше музичке колекције из било ког Ампаш компатибилног плејера.","Use your username and following password to connect to this Ampache instance:":"Користите ваше корисничко име и следећу лозинку за повезивање на овај Ампаш:"});
    gettextCatalog.setStrings('su', {});
    gettextCatalog.setStrings('sv', {"Albums":"Album","Artists":"Artister","Description":"Beskrivning","Description (e.g. App name)":"Beskrivning (ex. App-namn)","Generate API password":"Generera API-lösenord","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Här kan du generera lösenord för användning med Ampaches API, eftersom de inte kan lagras på ett riktigt säkert sätt på grund av Ampachi API:ns design. Du kan generera så många lösenord du vill och upphäva dem när som helst.","Invalid path":"Ogiltig sökväg","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Kom ihåg, att Ampaches API endast är en förhandsvisning och är ostabil. Du är välkommen att rapportera din upplevelse med denna funktionen i motsvarande <a href=\"https://github.com/owncloud/music/issues/60\">problem</a>. Jag skulle också vilja ha en lista över klienter att testa med.\nTack","Music":"Musik","Next":"Nästa","Path to your music collection":"Sökvägen till din musiksamling","Pause":"Paus","Play":"Spela","Previous":"Föregående","Repeat":"Upprepa","Revoke API password":"Upphäv API-lösenord","Shuffle":"Blanda","Some not playable tracks were skipped.":"Några icke spelbara spår hoppades över","This setting specifies the folder which will be scanned for music.":"Denna inställning specificerar vilken mapp som kommer skannas efter musik","Tracks":"Spår","Unknown album":"Okänt album","Unknown artist":"Okänd artist","Use this address to browse your music collection from any Ampache compatible player.":"Använd denna adress för att bläddra igenom din musiksamling från valfri Ampache-kompatibel enhet.","Use your username and following password to connect to this Ampache instance:":"Använd ditt användarnamn och följande lösenord för att ansluta mot denna Ampache instansen:"});
    gettextCatalog.setStrings('sw_KE', {});
    gettextCatalog.setStrings('ta_IN', {});
    gettextCatalog.setStrings('ta_LK', {"Description":"விவரிப்பு","Music":"இசை","Next":"அடுத்த","Pause":"இடைநிறுத்துக","Play":"Play","Previous":"முன்தைய","Repeat":"மீண்டும்"});
    gettextCatalog.setStrings('te', {"Music":"సంగీతం","Next":"తదుపరి","Previous":"గత"});
    gettextCatalog.setStrings('tg_TJ', {});
    gettextCatalog.setStrings('th_TH', {"+ New Playlist":"+ เพลย์ลิสต์ใหม่","Albums":"อัลบัม","All tracks":"แทร็กทั้งหมด","Artists":"ศิลปิน","Click here to start the scan":"คลิกที่นี่เพื่อเริ่มการสแกน","Description":"คำอธิบาย","Description (e.g. App name)":"รายละเอียด (ยกตัวอย่าง ชื่อแอพฯ)","Generate API password":"สุ่มรหัสผ่าน API","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"ที่นี่คุณสามารถสร้างรหัสผ่านที่จะใช้กับ Ampache API เพราะไม่สามารถเก็บไว้ในที่mujปลอดภัย เนื่องจากการออกแบบของ Ampache API คุณสามารถสร้างรหัสผ่านให้มากที่สุดเท่าที่คุณต้องการและยกเลิกได้ตลอดเวลา","Invalid path":"เส้นทางไม่ถูกต้อง","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"โปรดทราบว่า Ampache API เป็นเพียงตัวอย่างและไม่เสถียร อย่าลังเลที่จะมาช่วยกันรายงานบัค <a href=\"https://github.com/owncloud/music/issues/60\"> ที่นี่ </a> เรายังต้องการผู้ที่จะร่วมทดสอบ ขอบคุณ","Music":"เพลง","New music available":"มีเพลงใหม่มาแล้ว","New music available. Click here to reload the music library.":"มีเพลงใหม่ คลิกที่นี่เพื่อโหลดไลบรารีเพลงใหม่","Next":"ถัดไป","No music found":"ไม่พบเพลง","Path to your music collection":"เส้นทางที่จะเก็บเพลงของคุณ","Pause":"หยุดชั่วคราว","Play":"เล่น","Previous":"ก่อนหน้า","Repeat":"ทำซ้ำ","Revoke API password":"ยกเลิกรหัสผ่าน API","Scanning music …":"กำลังสแกนเพลง...","Shuffle":"สับเปลี่ยน","Some not playable tracks were skipped.":"บางเพลงที่ไม่สามารถเล่นได้จะถูกข้ามไป","This setting specifies the folder which will be scanned for music.":"ตั้งค่าเพื่อระบุโฟลเดอร์ที่จะสแกนหาเพลงฟัง","Tracks":"เพลง","Unknown album":"ไม่ทราบอัลบั้ม","Unknown artist":"ไม่รู้จักศิลปิน","Upload music in the files app to listen to it here":"อัพโหลดเพลงในไฟล์แอพฯเพื่อฟังได้ที่นี่","Use this address to browse your music collection from any Ampache compatible player.":"ใช้ที่อยู่นี้เพื่อเรียกคอลเลคชันเพลงจากเครื่องเล่น Ampache ที่เข้ากันได้","Use your username and following password to connect to this Ampache instance:":"ใช้ชื่อผู้ใช้และรหัสผ่านของคุณต่อไปนี้เพื่อเชื่อมต่อไปยัง Ampache ตัวอย่างเช่น:","Volume":"ปริมาณ","tracks":"เพลง","{{ scanningScanned }} of {{ scanningTotal }}":"{{ scanningScanned }} จากทั้งหมด {{ scanningTotal }}"});
    gettextCatalog.setStrings('th', {"+ New Playlist":"+ เพลย์ลิสต์ใหม่","Albums":"อัลบัม","All tracks":"แทร็กทั้งหมด","Artists":"ศิลปิน","Click here to start the scan":"คลิกที่นี่เพื่อเริ่มการสแกน","Description":"คำอธิบาย","Description (e.g. App name)":"รายละเอียด (ยกตัวอย่าง ชื่อแอพฯ)","Generate API password":"สุ่มรหัสผ่าน API","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"ที่นี่คุณสามารถสร้างรหัสผ่านที่จะใช้กับ Ampache API เพราะไม่สามารถเก็บไว้ในที่mujปลอดภัย เนื่องจากการออกแบบของ Ampache API คุณสามารถสร้างรหัสผ่านให้มากที่สุดเท่าที่คุณต้องการและยกเลิกได้ตลอดเวลา","Invalid path":"เส้นทางไม่ถูกต้อง","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"โปรดทราบว่า Ampache API เป็นเพียงตัวอย่างและไม่เสถียร อย่าลังเลที่จะมาช่วยกันรายงานบัค <a href=\"https://github.com/owncloud/music/issues/60\"> ที่นี่ </a> เรายังต้องการผู้ที่จะร่วมทดสอบ ขอบคุณ","Music":"เพลง","New music available":"มีเพลงใหม่มาแล้ว","New music available. Click here to reload the music library.":"มีเพลงใหม่ คลิกที่นี่เพื่อโหลดไลบรารีเพลงใหม่","Next":"ถัดไป","No music found":"ไม่พบเพลง","Path to your music collection":"เส้นทางที่จะเก็บเพลงของคุณ","Pause":"หยุดชั่วคราว","Play":"เล่น","Previous":"ก่อนหน้า","Repeat":"ทำซ้ำ","Revoke API password":"ยกเลิกรหัสผ่าน API","Scanning music …":"กำลังสแกนเพลง...","Shuffle":"สับเปลี่ยน","Some not playable tracks were skipped.":"บางเพลงที่ไม่สามารถเล่นได้จะถูกข้ามไป","This setting specifies the folder which will be scanned for music.":"ตั้งค่าเพื่อระบุโฟลเดอร์ที่จะสแกนหาเพลงฟัง","Tracks":"เพลง","Unknown album":"ไม่ทราบอัลบั้ม","Unknown artist":"ไม่รู้จักศิลปิน","Upload music in the files app to listen to it here":"อัพโหลดเพลงในไฟล์แอพฯเพื่อฟังได้ที่นี่","Use this address to browse your music collection from any Ampache compatible player.":"ใช้ที่อยู่นี้เพื่อเรียกคอลเลคชันเพลงจากเครื่องเล่น Ampache ที่เข้ากันได้","Use your username and following password to connect to this Ampache instance:":"ใช้ชื่อผู้ใช้และรหัสผ่านของคุณต่อไปนี้เพื่อเชื่อมต่อไปยัง Ampache ตัวอย่างเช่น:","Volume":"ปริมาณ","tracks":"เพลง","{{ scanningScanned }} of {{ scanningTotal }}":"{{ scanningScanned }} จากทั้งหมด {{ scanningTotal }}"});
    gettextCatalog.setStrings('tl_PH', {});
    gettextCatalog.setStrings('tr', {"+ New Playlist":"+Yeni Çalma Listesi","Albums":"Albümler","All tracks":"Tüm Parçalar","Artists":"Sanatçılar","Click here to start the scan":"Taramayı başlatmak için buraya tıklayın","Description":"Açıklama","Description (e.g. App name)":"Açıklama (örn. Uygulama adı)","Generate API password":"API parolası oluştur","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Ampache API'sinin tasarımından dolayı bu parolalar yeterince güvenli bir şekilde depolanamadığından, burada Ampache API'si ile kullanılacak parolaları oluşturabilirsiniz. İstediğiniz kadar parola oluşturup; ardından istediğiniz zaman geçersiz kılabilirsiniz.","Invalid path":"Geçersiz yol","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Ampache API'nin henüz bir önizleme olup, kararlı olmadığını unutmayın. Bu özellikle ilgili deneyiminizi ilgili <a href=\"https://github.com/owncloud/music/issues/60\">sorunlar</a> kısmında bildirmekten çekinmeyin. Ayrıca test edilmesi gereken istemcilerin listesini de edinmek isterim. Teşekkürler.","Music":"Müzik","New music available":"Yeni müzik mevcut","New music available. Click here to reload the music library.":"Yeni müzik mevcut. Kütüphaneyi yeniden taramak için buraya tıklayın.","Next":"Sonraki","No music found":"Müzik bulunamadı","Path to your music collection":"Müzik koleksiyonunuzun yolu","Pause":"Beklet","Play":"Oynat","Previous":"Önceki","Repeat":"Tekrarla","Revoke API password":"API parolasını geçersiz kıl","Scanning music …":"Müzik taranıyor ...","Shuffle":"Karıştır","Some not playable tracks were skipped.":"Bazı oynatılamayan parçalar atlandı.","This setting specifies the folder which will be scanned for music.":"Bu ayar, müzik için taranacak klasörü belirtir.","Tracks":"Parçalar","Unknown album":"Bilinmeyen albüm","Unknown artist":"Bilinmeyen sanatçı","Upload music in the files app to listen to it here":"Müziği burada dinlemek için dosya uygulaması içinde yükleyin","Use this address to browse your music collection from any Ampache compatible player.":"Herhangi Ampache uyumlu çalardan müzik koleksiyonunuza göz atmak için bu adresi kullanın.","Use your username and following password to connect to this Ampache instance:":"Bu Ampache örneğine bağlanmak için kullanıcı adınızı ve aşağıdaki parolayı kullanın:","Volume":"Ses","tracks":"parçalar","{{ scanningScanned }} of {{ scanningTotal }}":"Toplam {{ scanningTotal }} parçanın {{ scanningScanned }} tanesi tarandı"});
    gettextCatalog.setStrings('tzm', {});
    gettextCatalog.setStrings('ug', {"Description":"چۈشەندۈرۈش","Music":"نەغمە","Next":"كېيىنكى","Pause":"ۋاقىتلىق توختا","Play":"چال","Previous":"ئالدىنقى","Repeat":"قايتىلا"});
    gettextCatalog.setStrings('uk', {"Albums":"Альбоми","Artists":"Виконавці","Description":"Опис","Description (e.g. App name)":"Опис (наприклад назва додатку)","Generate API password":"Сгенерувати пароль для API","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Тут ви можете згенерувати пароль для використання з Ampache API, оскільки вони не можуть бути збережені дійсно безпечним чином через конструкцію Ampache API. Ви можете створити стільки паролей, скільки необхідно, та відмовитись від них в будь який час.","Invalid path":"Невірний шлях","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Пам'ятайте, що Ampache API є демо-версією і тому не стабільна. Ми будемо вдячні, якщо ви поділитеся досвідом роботи з цією функцією у відповідному <a href=\"https://github.com/owncloud/music/issues/60\">розділі</a>. Я також хотів би створити список клієнтів для тестування. Дякую.","Music":"Музика","Next":"Наступний","Path to your music collection":"Шлях до вашої музичної колекції","Pause":"Пауза","Play":"Грати","Previous":"Попередній","Repeat":"Повторювати","Revoke API password":"Відкликати API пароль","Shuffle":"Перемішати","Some not playable tracks were skipped.":"Деякі треки, що не відтворюються, були пропущені.","This setting specifies the folder which will be scanned for music.":"Цей параметр вказує теку, в якій буде проведено пошук музики.","Tracks":"Доріжки","Unknown album":"Невідомий альбом","Unknown artist":"Невідомий виконавець","Use this address to browse your music collection from any Ampache compatible player.":"Використовуйте цю адресу, щоб переглядати вашу музичну колекцію в будь-якому програвачі, що підтримує Ampache.","Use your username and following password to connect to this Ampache instance:":"Використовуйте власний логін та пароль для з'єднання з даним Ampache:"});
    gettextCatalog.setStrings('ur_PK', {"Description":"تصریح","Next":"اگلا","Repeat":"دہرایں"});
    gettextCatalog.setStrings('ur', {});
    gettextCatalog.setStrings('uz', {});
    gettextCatalog.setStrings('vi', {"Albums":"Album","Artists":"Nghệ sỹ","Description":"Mô tả","Description (e.g. App name)":"Mô tả (vd: Tên ứng dụng)","Generate API password":"Tạo password API","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Ở đây bạn có thể tạo mật khẩu để sử dụng với các API Ampache, bởi vì nó không thể được lưu trữ trong một cách thực sự an toàn do thiết kế của API Ampache. Bạn có thể tạo ra nhiều mật khẩu khi bạn muốn và thu hồi chúng bất cứ lúc nào.","Invalid path":"Đường dẫn không hợp lệ","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Hãy nhớ, rằng các API Ampache chỉ là một bản xem trước và không ổn định. Hãy báo cáo kinh nghiệm của bạn với tính năng này  <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. Tôi cũng muốn có một danh sách khách hàng để thử nghiệm. Cảm ơn. Thanks","Music":"Âm nhạc","Next":"Kế tiếp","Path to your music collection":"Đường dẫn đến bộ sưu tập nhạc của bạn","Pause":"Tạm dừng","Play":"Play","Previous":"Lùi lại","Repeat":"Lặp lại","Revoke API password":"Hủy password API","Shuffle":"Ngẫu nhiên","Some not playable tracks were skipped.":"Một số bài không thể phát đã được bỏ qua","This setting specifies the folder which will be scanned for music.":"Thiết lập này xác định thư mục đó sẽ được quét để tìm nhạc.","Tracks":"Bài","Unknown album":"Không tìm thấy album","Unknown artist":"Không tìm thấy nghệ sĩ","Use this address to browse your music collection from any Ampache compatible player.":"Sử dụng địa chỉ này để duyệt bộ sưu tập nhạc của bạn từ bất kỳ máy nghe nhạc tương thích Ampache.","Use your username and following password to connect to this Ampache instance:":"Sử dụng tên đăng nhập và mật khẩu sau để kết nối đến Ampache:"});
    gettextCatalog.setStrings('yo', {});
    gettextCatalog.setStrings('zh_CN', {"+ New Playlist":"+ 新播放列表","Albums":"专辑页","Artists":"艺术家","Click here to start the scan":"点击开始扫描","Description":"描述","Description (e.g. App name)":"描述 (例如 App 名称)","Generate API password":"生成 API 密码","Invalid path":"无效路径","Music":"音乐","Next":"下一曲","Path to your music collection":"音乐集路径","Pause":"暂停","Play":"播放","Previous":"前一首","Repeat":"重复","Revoke API password":"撤销 API 密码","Scanning music …":"扫描音乐中……","Shuffle":"随机","Some not playable tracks were skipped.":"部分无法播放的音轨已被跳过。","This setting specifies the folder which will be scanned for music.":"将会在此设置指定的文件夹中扫描音乐文件。","Tracks":"音轨","Unknown album":"未知专辑","Unknown artist":"未知艺术家","Use this address to browse your music collection from any Ampache compatible player.":"使用此地址在任何与 Ampache 兼容的音乐播放器中查看您的音乐集。","Use your username and following password to connect to this Ampache instance:":"使用您的用户名和密码连接到此 Ampache 服务：","Volume":"音量"});
    gettextCatalog.setStrings('zh_HK', {"Albums":"相簿","Artists":"歌手","Description":"描述","Music":"音樂","Next":"下一首","Pause":"暫停","Play":"播放","Previous":"上一首","Repeat":"重複"});
    gettextCatalog.setStrings('zh_TW', {"Albums":"專輯","Artists":"演出者","Description":"描述","Description (e.g. App name)":"描述（例如應用程式名稱）","Generate API password":"產生 API 密碼","Invalid path":"無效的路徑","Music":"音樂","Next":"下一個","Path to your music collection":"您的音樂資料夾的路徑","Pause":"暫停","Play":"播放","Previous":"上一個","Repeat":"重覆","Revoke API password":"撤銷 API 密碼","Shuffle":"隨機播放","Some not playable tracks were skipped.":"部份無法播放的曲目已跳過","This setting specifies the folder which will be scanned for music.":"我們會在這個資料夾內掃描音樂檔案","Tracks":"曲目","Unknown album":"未知的專輯","Unknown artist":"未知的表演者"});
/* jshint +W100 */
}]);
