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
		if (track !== null) {
			// switch initial state
			$rootScope.started = true;
			$scope.currentAlbum = libraryService.findAlbumOfTrack(track.id);
			$scope.setLoading(true);
			playTrack(track);
		} else {
			$scope.currentAlbum = null;
			// switch initial state
			$rootScope.started = false;
		}
	}

	var pathRequestTimer = null;
	function playTrack(track) {
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
						$scope.setPlay(true);

						pathRequestTimer = null;
					}
				}
			);
		}, 300);
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

	$scope.toggle = function(forcePlay) {
		forcePlay = forcePlay || false;
		if ($scope.currentTrack === null) {
			// nothing to do
			return null;
		}
		if (forcePlay) {
			$scope.player.play();
			$scope.setPlay(true);
		} else {
			$scope.player.togglePlayback();
			$rootScope.playing = !$rootScope.playing;
		}
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

	$document.bind('keydown', function(e) {
		if (e.target == document.body) {
			var func = null;
			switch (e.which) {
				case 32: //space
					func = $scope.toggle;
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
}]);
