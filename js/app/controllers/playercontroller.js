/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2013
 * @copyright Pauli Järvinen 2017 - 2022
 */

import radioIconPath from '../../../img/radio-file.svg';

angular.module('Music').controller('PlayerController', [
'$scope', '$rootScope', 'playlistService', 'Audio', 'gettextCatalog', 'Restangular', '$timeout', '$document', '$location',
function ($scope, $rootScope, playlistService, Audio, gettextCatalog, Restangular, $timeout, $document, $location) {

	$scope.loading = false;
	$scope.shiftHeldDown = false;
	$scope.player = Audio;
	$scope.currentTrack = null;
	$scope.seekCursorType = 'default';
	$scope.volume = parseInt(localStorage.getItem('oc_music_volume')) || 50;  // volume can be 0~100
	$scope.repeat = localStorage.getItem('oc_music_repeat') || 'false';
	$scope.shuffle = (localStorage.getItem('oc_music_shuffle') === 'true');
	$scope.playbackRate = 1.0;  // rate can be 0.5~3.0
	$scope.position = {
		bufferPercent: '0%',
		currentPercent: '0%',
		current: 0,
		total: 0
	};
	var scrobblePending = false;
	var pendingRadioTitleFetch = null;
	const GAPLESS_PLAY_OVERLAP_MS = 500;

	// shuffle and repeat may be overridden with URL parameters
	if ($location.search().shuffle !== undefined) {
		$scope.shuffle = OCA.Music.Utils.parseBoolean($location.search().shuffle);
	}
	if ($location.search().repeat !== undefined) {
		var val = String($location.search().repeat).toLowerCase();
		if (val !== 'one') {
			val = OCA.Music.Utils.parseBoolean(val).toString();
		}
		$scope.repeat = val;
	}

	playlistService.setRepeat($scope.repeat !== 'false'); // the "repeat-one" is handled internally by the PlayerController
	playlistService.setShuffle($scope.shuffle);

	// Player events may fire synchronously or asynchronously. Utilize $timeout
	// to always handle them asynchronously to run the handler within digest loop
	// but with no nested digests loop (which causes an exception).
	function onPlayerEvent(event, handler) {
		$scope.player.on(event, function(arg, playedUrl) {
			$timeout(function() {
				// Discard the event if the current song has already changed by the time we would handle the event
				if (playedUrl === $scope.player.getUrl()) {
					handler(arg);
				}
			});
		});
	}

	onPlayerEvent('buffer', function (percent) {
		$scope.setBufferPercentage(percent);

		// prepare the next song once buffering this one is done (sometimes the percent never goes above something like 99.996%)
		if (percent > 99 && $scope.currentTrack.type === 'song') {
			var entry = playlistService.peekNextTrack();
			if (entry?.track?.id !== null) {
				const {mime, url} = getPlayableFileUrl(entry.track);
				if (mime !== null && url !== null) {
					$scope.player.prepareURL(url, mime);
				}
			}
		}
	});
	onPlayerEvent('ready', function () {
		$scope.setLoading(false);
	});
	onPlayerEvent('progress', function (currentTime) {
		if (!$scope.loading && $scope.currentTrack) {
			$scope.setTime(currentTime/1000, $scope.position.total);
			$rootScope.$emit('playerProgress', currentTime);

			// Scrobble when the track has been listened for 10 seconds
			if (scrobblePending && currentTime >= 10000) {
				scrobbleCurrentTrack();
			}

			// Gapless jump to the next track when the playback is very close to the end of a local track
			if ($scope.position.total > 0 && $scope.currentTrack.type === 'song' && $scope.repeat !== 'one') {
				var timeLeft = $scope.position.total*1000 - currentTime;
				if (timeLeft < GAPLESS_PLAY_OVERLAP_MS) {
					var nextTrackId = playlistService.peekNextTrack()?.track?.id;
					if (nextTrackId !== null && nextTrackId !== $scope.currentTrack.id) {
						onEnd();
					}
				}
			}
		}
	});
	onPlayerEvent('end', onEnd);
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
		if (pendingRadioTitleFetch != null) {
			$timeout.cancel(pendingRadioTitleFetch);
			pendingRadioTitleFetch = null;
		}
		if ($scope.currentTrack?.type === 'radio') {
			getRadioTitle();
		}
	});
	onPlayerEvent('pause', function() {
		$rootScope.playing = false;
	});
	onPlayerEvent('stop', function() {
		$rootScope.playing = false;
	});

	function onEnd() {
		// Srcrobble now if it hasn't happened before reaching the end of the track
		if (scrobblePending) {
			scrobbleCurrentTrack();
		}
		if ($scope.repeat === 'one') {
			scrobblePending = true;
			$scope.player.seek(0);
			$scope.player.play();
		} else {
			$scope.next(0, /*gapless=*/true);
		}
	}

	function scrobbleCurrentTrack() {
		if ($scope.currentTrack?.type === 'song') {
			Restangular.one('track', $scope.currentTrack.id).all('scrobble').post();
		}
		scrobblePending = false;
	}

	$scope.durationKnown = function() {
		return $.isNumeric($scope.position.total) && $scope.position.total !== 0;
	};

	const titleApp = $('title').html().trim();

	// display the song name and artist in the title when there is current track
	$scope.$watch('currentTrack', function(newTrack) {
		var titleSong = '';
		if (newTrack?.title !== undefined) {
			if (newTrack?.channel) {
				titleSong = newTrack.title + ' (' + newTrack.channel.title + ') - ';
			} else {
				titleSong = newTrack.title + ' (' + newTrack.artistName + ') - ';
			}
		} else if (newTrack?.name !== undefined) {
			titleSong = newTrack.name + ' - ';
		}
		$('title').html(titleSong + titleApp);
	});

	function getPlayableFileUrl(track) {
		for (var mimeType in track.files) {
			if ($scope.player.canPlayMIME(mimeType)) {
				return {
					'mime': mimeType,
					'url': OC.filePath('music', '', 'index.php') + '/api/file/' + track.files[mimeType] + '/download'
				};
			}
		}

		return null;
	}

	function setCurrentTrack(playlistEntry, startOffset /*optional*/, gapless /*optional*/) {
		var track = playlistEntry ? playlistEntry.track : null;

		if (track !== null) {
			// switch initial state
			$rootScope.started = true;
			$scope.setLoading(true);
			playTrack(track, startOffset, gapless /*optional*/);
		} else {
			$scope.stop();
		}

		// After restoring the previous session upon brwoser restart, at least Firefox sometimes leaves
		// the shift state as "held". To work around this, reset the state whenever the current track changes.
		$scope.shiftHeldDown = false;
	}

	function playCurrentTrack(startOffset /*optional*/) {
		if (currentTrackIsStream()) {
			$scope.player.fromURL($scope.currentTrack.stream_url, null);
		}
		else {
			const {mime, url} = getPlayableFileUrl($scope.currentTrack);
			$scope.player.fromURL(url, mime);
			scrobblePending = true;
		}

		if (startOffset) {
			$scope.player.seekMsecs(startOffset);
		}
		$scope.player.play();
	}

	/*
	 * Create a debounced function which starts playing the currently selected track.
	 * The debounce is used to limit the number of GET requests when repeatedly changing
	 * the playing track like when rapidly and repeatedly clicking the 'Skip next' button.
	 * Too high number of simultaneous GET requests could easily jam a low-power server.
	 */
	var debouncedPlayCurrentTrack = _.debounce(playCurrentTrack, 300);

	function playTrack(track, startOffset /*optional*/, gapless /*optional*/) {
		$scope.currentTrack = track;

		// Don't indicate support for seeking before we actually know its status for the new track.
		$scope.seekCursorType = 'default';

		if (gapless) {
			// On gapless jump to next track, let the previous track play to the end while we start
			// playing the new track immediately. The tracks will be interlaced for a few hundred milliseconds.
			playCurrentTrack(startOffset);
		} else {
			// Stop the previous track and start the new playback with a small delay when this is not
			// an automatic "gapless" jump to next track.
			$scope.player.stop();
			debouncedPlayCurrentTrack(startOffset);
		}
	}

	function currentTrackIsStream() {
		return $scope.currentTrack?.stream_url !== undefined;
	}

	$scope.getDraggable = function() {
		return {track: $scope.currentTrack?.id};
	};

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
		localStorage.setItem('oc_music_volume', newValue);
	});

	$scope.$watch('playbackRate', function(newValue, _oldValue) {
		$scope.player.setPlaybackRate(newValue);
	});

	$scope.toggleShuffle = function() {
		$scope.shuffle = !$scope.shuffle;
		playlistService.setShuffle($scope.shuffle);
		localStorage.setItem('oc_music_shuffle', $scope.shuffle.toString());
	};

	$scope.toggleRepeat = function() {
		var nextState = {
			'false'	: 'true',
			'true'	: 'one',
			'one'	: 'false'
		};
		$scope.repeat = nextState[$scope.repeat];
		playlistService.setRepeat($scope.repeat !== 'false'); // the "repeat-one" is handled internally by the PlayerController
		localStorage.setItem('oc_music_repeat', $scope.repeat);
	};

	$scope.setTime = function(position, duration) {
		// sometimes the duration is reported slightly incorrectly and position may exceed it by a few seconds
		if (duration > 0 && duration < position) {
			duration = position;
		}

		$scope.position.current = position;
		$scope.position.total = duration;
		$scope.position.currentPercent = (duration > 0) ? position/duration*100 + '%' : 0;
	};

	$scope.setBufferPercentage = function(percent) {
		$scope.position.bufferPercent = Math.min(100, percent) + '%';
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

	$scope.stepPlaybackRate = function() {
		const stepRates = [0.5, 1.0, 1.5, 2.0, 2.5, 3.0];
		var curStep = 0;
		while (curStep < stepRates.length - 1 && $scope.playbackRate >= stepRates[curStep+1]) {
			++curStep;
		}
		var nextStep = (curStep + 1) % stepRates.length;
		$scope.playbackRate = stepRates[nextStep];
	};

	/** Context menu on long press of the play/pause button */
	document.getElementById('play-pause-button').addEventListener('long-press', function(e) {
		// We don't want the normal click event after the long press has been handled. However, preventing it seems to 
		// be implicit on touch devices (for reason unknown) and calling preventDefault() there would trigger the bug
		// https://github.com/john-doherty/long-press-event/issues/27.
		// The following is a bit hacky work-around for this.
		const isTouch = (('ontouchstart' in window) || (navigator.MaxTouchPoints > 0) || (navigator.msMaxTouchPoints > 0));
		if (!isTouch) {
			e.preventDefault();
		}

		// 50 ms haptic feedback for touch devices
		if ('vibrate' in navigator) {
			navigator.vibrate(50);
		}

		$timeout(() => $scope.playPauseContextMenuVisible = true);
	});
	// hide the popup menu when the user clicks anywhere on the page
	$document.click(function(_event) {
		$timeout(() => $scope.playPauseContextMenuVisible = false);
	});

	$scope.next = function(startOffset /*optional*/, gapless /*optional*/) {
		var entry = playlistService.jumpToNextTrack();

		// For ordinary tracks, skip the tracks with unsupported MIME types.
		// For external streams, we don't know the MIME type, and we just assume that they can be played.
		if (entry?.track?.files !== undefined) {
			var tracksSkipped = false;

			// get the next track as long as the current one contains no playable
			// audio mimetype
			while (entry !== null && !getPlayableFileUrl(entry.track)) {
				tracksSkipped = true;
				startOffset = null; // offset is not meaningful if we couldn't play the requested track
				entry = playlistService.jumpToNextTrack();
			}
			if (tracksSkipped) {
				OC.Notification.showTemporary(gettextCatalog.getString('Some not playable tracks were skipped.'));
			}
		}

		setCurrentTrack(entry, startOffset, gapless);
	};

	$scope.prev = function() {
		// Jump to the beginning of the current track if it has already played more than 2 secs.
		// This is disalbed for radio streams where jumping to the beginning often does not work.
		if ($scope.position.current > 2.0 && $scope.currentTrack?.type !== 'radio') {
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

	playlistService.subscribe('play', function(_event, _playingView /*optional, ignored*/, startOffset /*optional*/) {
		$scope.next(startOffset); /* fetch track and start playing*/
	});

	playlistService.subscribe('togglePlayback', $scope.togglePlayback);

	$scope.scrollToCurrentTrack = function() {
		if ($scope.currentTrack) {
			const doScroll = function() {
				if ($scope.currentTrack.type === 'radio') {
					$rootScope.$emit('scrollToStation', $scope.currentTrack.id);
				} else if ($scope.currentTrack.type === 'podcast') {
					$rootScope.$emit('scrollToPodcastEpisode', $scope.currentTrack.id);
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
		if (!OCA.Music.Utils.isTextEntryElement(e.target)) {
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
				case 16: // shift
					func = (() => $scope.shiftHeldDown = true);
					break;
			}

			if (func) {
				$timeout(func);
				return false;
			}
		}

		return true;
	});

	$document.bind('keyup', function(e) {
		if (e.which == 16) { //shift
			$timeout(() => $scope.shiftHeldDown = false);
			return false;
		}
		return true;
	});

	$(window).blur(function() {
		$timeout(() => $scope.shiftHeldDown = false);
	});

	$scope.primaryTitle = function() {
		return $scope.currentTrack?.title ?? $scope.currentTrack?.name ?? null;
	};

	$scope.secondaryTitle = function() {
		return $scope.currentTrack?.artistName ?? $scope.currentTrack?.channel?.title ?? $scope.currentTrack?.currentTitle ?? $scope.currentTrack?.stream_url ?? null;
	};

	$scope.coverArt = function() {
		return $scope.currentTrack?.album?.cover ?? $scope.currentTrack?.channel?.image ?? null;
	};

	$scope.coverArtTitle = function() {
		return $scope.currentTrack?.album?.name ?? $scope.currentTrack?.channel?.title ?? null;
	};

	const playScopeNames = {
		'albums'			: gettextCatalog.getString('Albums'),
		'folders'			: gettextCatalog.getString('Folders'),
		'genres'			: gettextCatalog.getString('Genres'),
		'alltracks'			: gettextCatalog.getString('All tracks'),
		'radio'				: gettextCatalog.getString('Internet radio'),
		'podcasts'			: gettextCatalog.getString('Podcasts'),
		'album'				: gettextCatalog.getString('Album'),
		'artist'			: gettextCatalog.getString('Artist'),
		'folder'			: gettextCatalog.getString('Folder'),
		'genre'				: gettextCatalog.getString('Genre'),
		'playlist'			: gettextCatalog.getString('Playlist'),
		'podcast-channel'	: gettextCatalog.getString('Channel'),
		'podcast-episode'	: gettextCatalog.getString('Episode'),
	};

	function playScopeName() {
		var listId = playlistService.getCurrentPlaylistId();
		if (listId !== null) {
			var key = listId.split('-').slice(0, -1).join('-') || listId;
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

	function getRadioTitle() {
		const onMetadata = function(streamTitle) {
			//console.log('MetaData recieved: ' + streamTitle);
			if (streamTitle == '') {
				streamTitle = null;
			}
			if ($scope.currentTrack.currentTitle !== streamTitle) {
				$scope.currentTrack.currentTitle = streamTitle;
			}
		};

		if ($rootScope.playing) {
			var currentTrackId = $scope.currentTrack.id;
			Restangular.one('radio', $scope.currentTrack.id).one('info').get().then(
				function(response) {
					if ($scope.currentTrack?.id == currentTrackId) {
						onMetadata(response?.title);
					}
				},
				function(_error) {
					// error handling
					if ($scope.currentTrack?.id == currentTrackId) {
						onMetadata(null);
					}
				}
			);
			pendingRadioTitleFetch = $timeout(getRadioTitle, 32000);
		} else {
			pendingRadioTitleFetch = null;
		}
	}

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

		$scope.$watchGroup(['currentTrack', 'currentTrack.currentTitle'], function(newValues) {
			const track = newValues[0];
			if (track) {
				if (track.type === 'radio') {
					navigator.mediaSession.metadata = new MediaMetadata({
						title: track.name,
						artist: track.currentTitle ?? track.stream_url,
						artwork: [{
							sizes: '190x190',
							src: radioIconPath,
							type: 'image/svg+xml'
						}]
					});
				}
				else {
					navigator.mediaSession.metadata = new MediaMetadata({
						title: track.title,
						artist: track?.artistName,
						album: track?.album?.name ?? track?.channel?.title,
						artwork: [{
							sizes: '190x190',
							src: $scope.coverArt() + (coverArtToken ? ('?coverToken=' + coverArtToken) : ''),
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

			let args = {
				silent: true,
				body: $scope.secondaryTitle() + '\n' + (track?.album?.name ?? ''),
				icon: (track?.type === 'radio')
					? radioIconPath
					: $scope.coverArt() + (coverArtToken ? ('?coverToken=' + coverArtToken) : '')
			};
			notification = new Notification($scope.primaryTitle(), args);
			notification.onclick = $scope.scrollToCurrentTrack;
		}, 500);

		$scope.$watchGroup(['currentTrack', 'currentTrack.currentTitle'], function(newValues) {
			const track = newValues[0];
			var enabled = (localStorage.getItem('oc_music_song_notifications') !== 'false');

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
