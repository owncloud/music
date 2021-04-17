/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2013
 * @copyright Pauli Järvinen 2017 - 2021
 */

import radioIcon from '../../../img/radio-file.svg';

angular.module('Music').controller('PlayerController', [
'$scope', '$rootScope', 'playlistService', 'Audio', 'gettextCatalog', '$timeout', '$document',
function ($scope, $rootScope, playlistService, Audio, gettextCatalog, $timeout, $document) {

	$scope.loading = false;
	$scope.player = Audio;
	$scope.currentTrack = null;
	$scope.seekCursorType = 'default';
	$scope.volume = parseInt(Cookies.get('oc_music_volume')) || 50;  // volume can be 0~100
	$scope.repeat = Cookies.get('oc_music_repeat') || 'false';
	$scope.shuffle = Cookies.get('oc_music_shuffle') == 'true';
	$scope.position = {
		bufferPercent: '0%',
		currentPercent: '0%',
		current: 0,
		total: 0
	};

	playlistService.setRepeat($scope.repeat !== 'false'); // the "repeat-one" is handled internally by the PlayerController
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
		if ($scope.repeat === 'one') {
			$scope.player.seek(0);
			$scope.player.play();
		} else {
			$scope.next();
		}
	});
	onPlayerEvent('duration', function(msecs) {
		$scope.setTime($scope.position.current, msecs/1000);
		// Seeking may be possible once the duration is available
		$scope.seekCursorType = $scope.player.seekingSupported() ? 'pointer' : 'default';
	});
	onPlayerEvent('error', function(url) {
		OC.Notification.showTemporary(gettextCatalog.getString('Error playing URL: ' + url));
		// Jump automatically to the next track unless we were playing an external stream
		if (!currentTrackIsStream()) {
			$scope.next();
		}
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

	$scope.durationKnown = function() {
		return $.isNumeric($scope.position.total) && $scope.position.total !== 0;
	};

	const titleApp = $('title').html().trim();

	// display the song name and artist in the title when there is current track
	$scope.$watch('currentTrack', function(newTrack) {
		var titleSong = '';
		if (newTrack?.title !== undefined) {
			titleSong = newTrack.title + ' (' + newTrack.artistName + ') - ';
		} else if (newTrack?.name !== undefined) {
			titleSong = newTrack.name + ' - ';
		}
		$('title').html(titleSong + titleApp);
	});

	$scope.getPlayableFileId = function (track) {
		for (var mimeType in track.files) {
			if ($scope.player.canPlayMIME(mimeType)) {
				return {
					'mime': mimeType,
					'id': track.files[mimeType]
				};
			}
		}

		return null;
	};

	function setCurrentTrack(playlistEntry, startOffset /*optional*/) {
		var track = playlistEntry ? playlistEntry.track : null;

		if (track !== null) {
			// switch initial state
			$rootScope.started = true;
			$scope.setLoading(true);
			playTrack(track, startOffset);
		} else {
			$scope.stop();
		}
	}

	/*
	 * Create a debounced function which starts playing the currently selected track.
	 * The debounce is used to limit the number of GET requests when repeatedly changing
	 * the playing track like when rapidly and repeatedly clicking the 'Skip next' button.
	 * Too high number of simultaneous GET requests could easily jam a low-power server.
	 */
	var debouncedPlayCurrentTrack = _.debounce(function(startOffset /*optional*/) {
		if (currentTrackIsStream()) {
			$scope.player.fromURL($scope.currentTrack.stream_url, null);
		}
		else {
			var mimeAndId = $scope.getPlayableFileId($scope.currentTrack);
			var url = OC.filePath('music', '', 'index.php') + '/api/file/' + mimeAndId.id + '/download';
			$scope.player.fromURL(url, mimeAndId.mime);
		}

		if (startOffset) {
			$scope.player.seekMsecs(startOffset);
		}
		$scope.player.play();
	}, 300);

	function playTrack(track, startOffset /*optional*/) {
		$scope.currentTrack = track;

		// Pause any previous playback and don't indicate support for seeking before we actually know it
		$scope.player.pause();
		$scope.seekCursorType = 'default';

		// Star the playback with a small delay. 
		debouncedPlayCurrentTrack(startOffset);
	}

	function currentTrackIsStream() {
		return $scope.currentTrack?.stream_url !== undefined;
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

	$scope.$watch('volume', function(newValue, _oldValue) {
		$scope.player.setVolume(newValue);
		Cookies.set('oc_music_volume', newValue, { expires: 3650 });
	});

	$scope.toggleShuffle = function() {
		$scope.shuffle = !$scope.shuffle;
		playlistService.setShuffle($scope.shuffle);
		Cookies.set('oc_music_shuffle', $scope.shuffle.toString(), { expires: 3650 });
	};

	$scope.toggleRepeat = function() {
		var nextState = {
			'false'	: 'true',
			'true'	: 'one',
			'one'	: 'false'
		};
		$scope.repeat = nextState[$scope.repeat];
		playlistService.setRepeat($scope.repeat !== 'false'); // the "repeat-one" is handled internally by the PlayerController
		Cookies.set('oc_music_repeat', $scope.repeat, { expires: 3650 });
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
		$rootScope.playing = false;
		$rootScope.started = false;
		playlistService.clearPlaylist();
	};

	$scope.next = function(startOffset /*optional*/) {
		var entry = playlistService.jumpToNextTrack();

		// For ordinary tracks, skip the tracks with unsupported MIME types.
		// For external streams, we don't know the MIME type, and we just assume that they can be played.
		if (entry?.track?.files !== undefined) {
			var tracksSkipped = false;

			// get the next track as long as the current one contains no playable
			// audio mimetype
			while (entry !== null && !$scope.getPlayableFileId(entry.track)) {
				tracksSkipped = true;
				startOffset = null; // offset is not meaningful if we couldn't play the requested track
				entry = playlistService.jumpToNextTrack();
			}
			if (tracksSkipped) {
				OC.Notification.showTemporary(gettextCatalog.getString('Some not playable tracks were skipped.'));
			}
		}

		setCurrentTrack(entry, startOffset);
	};

	$scope.prev = function() {
		// Jump to the beginning of the current track if it has already played more than 2 secs.
		// This is disalbed for exteranl streams where jumping to the beginning often does not work.
		if ($scope.position.current > 2.0 && !currentTrackIsStream()) {
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

	playlistService.subscribe('play', function(event, playingView /*optional, ignored*/, startOffset /*optional*/) {
		$scope.next(startOffset); /* fetch track and start playing*/
	});

	playlistService.subscribe('togglePlayback', $scope.togglePlayback);

	$scope.scrollToCurrentTrack = function() {
		if ($scope.currentTrack) {
			const doScroll = function() {
				if (currentTrackIsStream()) {
					$rootScope.$emit('scrollToStation', $scope.currentTrack.id);
				} else {
					$rootScope.$emit('scrollToTrack', $scope.currentTrack.id);
				}
			};

			if ($rootScope.currentView !== $rootScope.playingView) {
				$scope.navigateTo($rootScope.playingView, doScroll);
			} else {
				doScroll();
			}
		}
	};

	$document.bind('keydown', function(e) {
		if (e.target == document.body) {
			var func = null;
			switch (e.which) {
				case 32: //space
					func = e.shiftKey ? $scope.stop : $scope.togglePlayback;
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

	$scope.primaryTitle = function() {
		return $scope.currentTrack?.title ?? $scope.currentTrack?.name ?? null;
	};

	$scope.secondaryTitle = function() {
		return $scope.currentTrack?.artistName ?? $scope.currentTrack?.stream_url ?? null;
	};

	const playScopeNames = {
		'albums'	: gettextCatalog.getString('Albums'),
		'folders'	: gettextCatalog.getString('Folders'),
		'genres'	: gettextCatalog.getString('Genres'),
		'alltracks'	: gettextCatalog.getString('All tracks'),
		'radio'		: gettextCatalog.getString('Internet radio'),
		'album'		: gettextCatalog.getString('Album'),
		'artist'	: gettextCatalog.getString('Artist'),
		'folder'	: gettextCatalog.getString('Folder'),
		'genre'		: gettextCatalog.getString('Genre'),
		'playlist'	: gettextCatalog.getString('Playlist')
	};

	function playScopeName() {
		var listId = playlistService.getCurrentPlaylistId();
		if (listId !== null) {
			var key = listId.split('-', 1)[0];
			return playScopeNames[key];
		} else {
			return '';
		}
	}

	$scope.shuffleTooltip = function() {
		var command = gettextCatalog.getString('Shuffle');
		var cmdScope = $scope.shuffle ? playScopeName() : gettextCatalog.getString('Off');
		return command + ' (' + cmdScope + ')';
	};

	$scope.repeatTooltip = function() {
		var command = gettextCatalog.getString('Repeat');
		var cmdScope = '';
		switch ($scope.repeat) {
			case 'false':
				cmdScope = gettextCatalog.getString('Off');
				break;
			case 'one':
				cmdScope = gettextCatalog.getString('Track');
				break;
			case 'true':
				cmdScope = playScopeName();
				break;
		}
		return command + ' (' + cmdScope + ')';
	};

	/**
	* The coverArtToken is used to enable loading the cover art in the mediaSession of Firefox. There,
	* the loading happens in a context where the normal session cookies are not available. Hence, for the
	* server, this looks like there's no logged in user. The token is used as an alternative means of
	* authentication, which will provide access only to the cover art images.
	*/
	var coverArtToken = null;
	$rootScope.$on('newCoverArtToken', function(_event, token) {
		coverArtToken = token;
	});

	/**
	 * Integration to the media control panel available on Chrome starting from version 73 and Edge from
	 * version 83. In Firefox, the API is enabled by default at least in the version 83, although at least
	 * partial support has been available already starting from the version 74 via the advanced settings.
	 *
	 * The API brings the bindings with the special multimedia keys possibly present on the keyboard,
	 * as well as any OS multimedia controls available e.g. in status pane and/or lock screen.
	 */
	if ('mediaSession' in navigator) {
		var registerMediaControlHandler = function(action, handler) {
			try {
				navigator.mediaSession.setActionHandler(action, function() { $timeout(handler); });
			} catch (error) {
				console.log('The media control "' + action + '"" is not supported by the browser');
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
				if ('stream_url' in track) {
					navigator.mediaSession.metadata = new MediaMetadata({
						title: track.name,
						artist: track.stream_url,
						artwork: [{
							sizes: '190x190',
							src: OC.filePath('music', 'dist', radioIcon),
							type: 'image/svg+xml'
						}]
					});
				}
				else {
					navigator.mediaSession.metadata = new MediaMetadata({
						title: track.title,
						artist: track.artistName,
						album: track.album.name,
						artwork: [{
							sizes: '190x190',
							src: track.album.cover + (coverArtToken ? ('?coverToken=' + coverArtToken) : ''),
							type: ''
						}]
					});
				}
			}
		});
	}

	/**
	 * Desktop notifications
	 */
	if (typeof Notification !== 'undefined') {
		var notification = null;
		const showNotification = _.debounce(function(track) {
			// close any previous notification first
			if (notification !== null) {
				notification.close();
				notification = null;
			}

			let args = {silent: true};
			if ('stream_url' in track) {
				args.body = track.stream_url;
				args.icon = OC.filePath('music', 'dist', radioIcon);
			} else {
				args.body = track.artistName + '\n' + track.album.name;
				args.icon = track.album.cover + (coverArtToken ? ('?coverToken=' + coverArtToken) : '');
			}
			notification = new Notification(track.title ?? track.name, args);
			notification.onclick = $scope.scrollToCurrentTrack;
		}, 500);

		$scope.$watch('currentTrack', function(track) {
			var enabled = (Cookies.get('oc_music_song_notifications') !== 'false');

			if (enabled && track) {
				if (Notification.permission === 'granted') {
					showNotification(track);
				} else if (Notification.permission !== 'denied') {
					Notification.requestPermission().then(function(permission) {
						if (permission === 'granted') {
							showNotification(track);
						}
					});
				}
			} else {
				showNotification.cancel();
				if (notification !== null) {
					notification.close();
					notification = null;
				}
			}
		});
	}
}]);
