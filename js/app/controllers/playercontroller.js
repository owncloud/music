/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2013
 * @copyright Pauli Järvinen 2017 - 2020
 */


angular.module('Music').controller('PlayerController', [
'$scope', '$rootScope', 'playlistService', 'libraryService',
'Audio', 'Restangular', 'gettextCatalog', '$timeout', '$document',
function ($scope, $rootScope, playlistService, libraryService,
		Audio, Restangular, gettextCatalog, $timeout, $document) {

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

	playlistService.setRepeat($scope.repeat);
	playlistService.setShuffle($scope.shuffle);

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
		$scope.setTime(currentTime/1000, $scope.position.total);
		$rootScope.$emit('playerProgress', currentTime);
	});
	onPlayerEvent('end', function() {
		$scope.next();
	});
	onPlayerEvent('duration', function(msecs) {
		$scope.setTime($scope.position.current, msecs/1000);
	});
	onPlayerEvent('error', function(url) {
		var filename = url.split('?').shift().split('/').pop();
		OC.Notification.showTemporary(gettextCatalog.getString('Error playing file: ' + filename));
		$scope.next();
	});
	onPlayerEvent('play', function() {
		$rootScope.playing = true;
	});
	onPlayerEvent('pause', function() {
		$rootScope.playing = false;
	});
	onPlayerEvent('stop', function() {
		$rootScope.playing = false;
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

		if (track !== null) {
			// switch initial state
			$rootScope.started = true;
			$scope.setLoading(true);
			playTrack(track);
		} else {
			$scope.stop();
		}
	}

	var pathRequestTimer = null;
	function playTrack(track) {
		$scope.currentTrack = track;
		$scope.currentAlbum = track.album;

		// Pause any previous playback
		$scope.player.pause();

		// Execute the action with small delay. This is to limit the number of GET requests
		// when repeatedly changing the playing track like when rapidly and repeatedly clicking
		// the Next button. Too high number of simultaneous GET requests could easily jam a
		// low-power server.
		if (pathRequestTimer !== null) {
			$timeout.cancel(pathRequestTimer);
		}

		pathRequestTimer = $timeout(function() {
			// Get path to the track and from a webDAV URL. The webDAV URL is 
			// then passed to PlayerWrapper for playing.
			var mimeAndId = $scope.getPlayableFileId(track);
			Restangular.one('file', mimeAndId.id).one('path').get().then(
				function(result) {
					// It is possible that the active track has already changed again by the time we get
					// the URI. Do not start playback in that case.
					if (track == $scope.currentTrack) {
						var url = OC.linkToRemoteBase('webdav') + result.path +
								'?requesttoken=' + encodeURIComponent(OC.requestToken);
						$scope.player.fromURL(url, mimeAndId.mime);
						$scope.seekCursorType = $scope.player.seekingSupported() ? 'pointer' : 'default';

						$scope.player.play();

						pathRequestTimer = null;
					}
				}
			);
		}, 300);
	}

	$scope.setLoading = function(loading) {
		$scope.loading = loading;
		if (loading) {
			$scope.position.current = 0;
			$scope.position.currentPercent = 0;
			$scope.position.bufferPercent = 0;
			$scope.position.total = 0;
		}
	};

	$scope.$watch('volume', function(newValue, oldValue) {
		$scope.player.setVolume(newValue);
		Cookies.set('oc_music_volume', newValue, { expires: 3650 });
	});

	$scope.toggleShuffle = function() {
		$scope.shuffle = !$scope.shuffle;
		playlistService.setShuffle($scope.shuffle);
		Cookies.set('oc_music_shuffle', $scope.shuffle.toString(), { expires: 3650 });
	};

	$scope.toggleRepeat = function() {
		$scope.repeat = !$scope.repeat;
		playlistService.setRepeat($scope.repeat);
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

	$scope.play = function() {
		if ($scope.currentTrack !== null) {
			$scope.player.play();
		}
	};

	$scope.pause = function() {
		if ($scope.currentTrack !== null) {
			$scope.player.pause();
		}
	};

	$scope.togglePlayback = function() {
		if ($rootScope.playing) {
			$scope.pause();
		} else {
			$scope.play();
		}
	};

	$scope.stop = function() {
		$scope.player.stop();
		$scope.currentTrack = null;
		$scope.currentAlbum = null;
		$rootScope.playing = false;
		$rootScope.started = false;
		playlistService.clearPlaylist();
	};

	$scope.next = function() {
		var entry = playlistService.jumpToNextTrack(),
			tracksSkipped = false;

		// get the next track as long as the current one contains no playable
		// audio mimetype
		while (entry !== null && !$scope.getPlayableFileId(entry.track)) {
			tracksSkipped = true;
			entry = playlistService.jumpToNextTrack();
		}
		if (tracksSkipped) {
			OC.Notification.showTemporary(gettextCatalog.getString('Some not playable tracks were skipped.'));
		}
		setCurrentTrack(entry);
	};

	$scope.prev = function() {
		// Jump to the beginning of the current track if it has already played more than 2 secs
		if ($scope.position.current > 2.0 && $scope.player.seekingSupported()) {
			$scope.player.seek(0);
		}
		// Jump to the previous track if the current track has played only 2 secs or less
		else {
			var track = playlistService.jumpToPrevTrack();
			if (track !== null) {
				setCurrentTrack(track);
			}
		}
	};

	$scope.seek = function($event) {
		var offsetX = $event.offsetX || $event.originalEvent.layerX;
		var ratio = offsetX / $event.currentTarget.clientWidth;
		$scope.player.seek(ratio);
	};

	$scope.seekBackward = $scope.player.seekBackward;

	$scope.seekForward = $scope.player.seekForward;

	playlistService.subscribe('play', $scope.next /* fetch track and start playing*/);

	playlistService.subscribe('togglePlayback', $scope.togglePlayback);

	$scope.scrollToCurrentTrack = function() {
		if ($scope.currentTrack) {
			$rootScope.$emit('scrollToTrack', $scope.currentTrack.id);
		}
	};

	$document.bind('keydown', function(e) {
		if (e.target == document.body) {
			var func = null;
			switch (e.which) {
				case 32: //space
					func = $scope.togglePlayback;
					break;
				case 37: // arrow left
					func = $scope.prev;
					break;
				case 39: // arrow right
					func = $scope.next;
					break;
			}

			if (func) {
				$timeout(func);
				return false;
			}
		}

		return true;
	});

	/**
	 * Integration to the media control panel available on Chrome starting from version 73 and Edge from
	 * version 83. In Firefox, it is still disabled in the version 77, but a partially working support can
	 * be enabled via the advanced settings.
	 *
	 * The API brings the bindings with the special multimedia keys possibly present on the keyboard,
	 * as well as any OS multimedia controls available e.g. in status pane and/or lock screen.
	 */
	if ('mediaSession' in navigator) {
		var registerMediaControlHandler = function(action, handler) {
			try {
				navigator.mediaSession.setActionHandler(action, function() { $timeout(handler); });
			} catch (error) {
				console.log("The media control '" + action + "' is not supported by the browser");
			}
		};

		registerMediaControlHandler('play', $scope.play);
		registerMediaControlHandler('pause', $scope.pause);
		registerMediaControlHandler('stop', $scope.stop);
		registerMediaControlHandler('seekbackward', $scope.seekBackward);
		registerMediaControlHandler('seekforward', $scope.seekForward);
		registerMediaControlHandler('previoustrack', $scope.prev);
		registerMediaControlHandler('nexttrack', $scope.next);

		$scope.$watch('currentTrack', function(track) {
			if (track) {
				navigator.mediaSession.metadata = new MediaMetadata({
					title: track.title,
					artist: track.artistName,
					album: track.album.name,
					artwork: [{
						sizes: "190x190",
						src: track.album.cover,
						type: ""
					}]
				});
			}
		});
	}
}]);
