/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2013
 * @copyright Pauli Järvinen 2017 - 2023
 */

import radioIconPath from '../../../img/radio-file.svg';

angular.module('Music').controller('PlayerController', [
'$scope', '$rootScope', 'playlistService', 'Audio', 'gettextCatalog', 'Restangular', '$timeout', '$q', '$document', '$location',
function ($scope, $rootScope, playlistService, Audio, gettextCatalog, Restangular, $timeout, $q, $document, $location) {

	$scope.loading = false;
	$scope.shiftHeldDown = false;
	$scope.player = Audio;
	$scope.currentTrack = null;
	$scope.seekCursorType = 'default';
	$scope.volume = parseInt(OCA.Music.Storage.get('volume')) || 50;  // volume can be 0~100
	$scope.repeat = OCA.Music.Storage.get('repeat') || 'false';
	$scope.shuffle = (OCA.Music.Storage.get('shuffle') === 'true');
	$scope.playbackRate = 1.0;  // rate can be 0.5~3.0
	$scope.position = {
		bufferPercent: 0,
		currentPercent: 0,
		previewPercent: 0,
		currentPreview: null,
		currentPreview_ts: null, // Activation time stamp (Type: Date)
		currentPreview_tf: null, // Transient mouse movement filter (Type: Number/Timer-Handle)
		previewVisible: () => ($scope.position.currentPreview !== null) && !$scope.position.currentPreview_tf,
		current: 0,
		total: 0
	};
	let lastVolume = null;
	let scrobblePending = false;
	let scheduledRadioTitleFetch = null;
	let abortRadioTitleFetch = null;
	const GAPLESS_PLAY_OVERLAP_MS = 500;
	const RADIO_INFO_POLL_PERIOD_MS = 30000;
	const RADIO_INFO_POLL_MAX_ATTEMPTS = 3;
	const PLAYBACK_RATE_STEPPING = 0.25;
	const PLAYBACK_RATE_MIN = 0.5;
	const PLAYBACK_RATE_MAX = 3.0;

	$scope.min = Math.min;
	$scope.abs = Math.abs;

	// shuffle and repeat may be overridden with URL parameters
	if ($location.search().shuffle !== undefined) {
		$scope.shuffle = OCA.Music.Utils.parseBoolean($location.search().shuffle);
	}
	if ($location.search().repeat !== undefined) {
		let val = String($location.search().repeat).toLowerCase();
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
			let entry = playlistService.peekNextTrack();
			if (entry?.track?.id !== undefined) {
				const {mime, url} = getPlayableFileUrl(entry.track) || [null, null];
				if (mime !== null && url !== null) {
					$scope.player.prepareUrl(url, mime);
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
				let timeLeft = $scope.position.total*1000 - currentTime;
				if (timeLeft < GAPLESS_PLAY_OVERLAP_MS) {
					let nextTrackId = playlistService.peekNextTrack()?.track?.id;
					if (nextTrackId !== null && nextTrackId !== $scope.currentTrack.id) {
						onEnd();
					}
				}
			}
		}

		// Show progress again instead of preview after a timeout of 2000ms
		if ($scope.position.currentPreview_ts) {
			let timeSincePreview = Date.now() - $scope.position.currentPreview_ts;
			if (timeSincePreview >= 2000) {
				$scope.seekbarLeave();
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
		OC.Notification.showTemporary(gettextCatalog.getString('Error playing URL:') + ' ' + url);
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

	$scope.$watch('currentTrack', function(newTrack) {
		updateWindowTitle(newTrack);
		// Cancel any pending or ongoing fetch for radio station metadata. If applicable, 
		// the fetch for new data is then initiated within the function playCurrentTrack.
		cancelRadioTitleFetch();
	});

	// display the song name and artist in the title when there is current track
	const titleApp = $('title').html().trim();
	function updateWindowTitle(track) {
		let titleSong = '';
		if (track?.title !== undefined) {
			if (track?.channel) {
				titleSong = track.title + ' (' + track.channel.title + ') - ';
			} else {
				titleSong = track.title + ' (' + track.artist.name + ') - ';
			}
		} else if (track) {
			titleSong = $scope.primaryTitle() + ' - ';
		}
		$('title').html(titleSong + titleApp);
	}

	function cancelRadioTitleFetch() {
		if (scheduledRadioTitleFetch != null) {
			$timeout.cancel(scheduledRadioTitleFetch);
			scheduledRadioTitleFetch = null;
		}
		if (abortRadioTitleFetch != null) {
			abortRadioTitleFetch.resolve();
			abortRadioTitleFetch = null;
		}
	}

	function getRadioTitle(radioTrack, failCounter = 0 /*internal*/) {
		abortRadioTitleFetch = $q.defer();
		const config = {timeout: abortRadioTitleFetch.promise};
		const metaType = radioTrack.metadata?.type; // request the same metadata type as previously got (if any)

		Restangular.one('radio', radioTrack.id).one('info').withHttpConfig(config).get({type: metaType}).then(
			function(response) {
				abortRadioTitleFetch = null;
				radioTrack.metadata = response;

				if (!response) {
					failCounter++;
				} else {
					failCounter = 0;
				}

				// Schedule next title update if the same station is still playing (or paused).
				// The polling is stopped also if there have been many consecutive failures to get any kind of metadata.
				if (failCounter >= RADIO_INFO_POLL_MAX_ATTEMPTS) {
					console.log('The radio station doesn\'t seem to broadcast any metadata, stop polling');
				}
				else if ($scope.currentTrack?.id == radioTrack.id) {
					scheduledRadioTitleFetch = $timeout(function() {
						scheduledRadioTitleFetch = null;
						getRadioTitle(radioTrack, failCounter);
					}, RADIO_INFO_POLL_PERIOD_MS);
				}
			},
			function(_error) {
				abortRadioTitleFetch = null;
				// Do nothing on error. The most common reason to end up here is when we have aborted the request.
				// It's also possible that the server returned an error, and we want to stop the polling in that case too.
				// Simply not finding any metadata does not lead to error, and the error is unlikely to get solved on its own.
			}
		);
	}

	function getPlayableFileUrl(track) {
		for (var mimeType in track.files) {
			if ($scope.player.canPlayMime(mimeType)) {
				return {
					'mime': mimeType,
					'url': OC.filePath('music', '', 'index.php') + '/api/file/' + track.files[mimeType] + '/download'
				};
			}
		}

		return null;
	}

	function setCurrentTrack(playlistEntry, startOffset = 0, gapless = false) {
		let track = playlistEntry ? playlistEntry.track : null;

		if (track !== null) {
			// switch initial state
			$rootScope.started = true;
			$scope.setLoading(true);
			playTrack(track, startOffset, gapless);
		} else {
			$scope.stop();
		}

		// After restoring the previous session upon brwoser restart, at least Firefox sometimes leaves
		// the shift state as "held". To work around this, reset the state whenever the current track changes.
		$scope.shiftHeldDown = false;
	}

	function playCurrentTrack(startOffset = 0) {
		// the playback may have been stopped and currentTrack vanished during the debounce time
		if ($scope.currentTrack) {
			if ($scope.currentTrack.type === 'radio') {
				const currentTrack = $scope.currentTrack;
				Restangular.one('radio', currentTrack.id).one('streamurl').get().then(
					function(response) {
						if ($scope.currentTrack === currentTrack) { // check the currentTack hasn't already changed'
							$scope.player.fromExtUrl(response.url, response.hls);
							$scope.player.play();
						}
					},
					function(_error) {
						// error handling
						OC.Notification.showTemporary(gettextCatalog.getString('Radio station not found'));
					}
				);
				getRadioTitle(currentTrack);
			} else if ($scope.currentTrack.type === 'podcast') {
				$scope.player.fromExtUrl($scope.currentTrack.stream_url, false);
				$scope.player.play();
			} else {
				const {mime, url} = getPlayableFileUrl($scope.currentTrack);
				$scope.player.fromUrl(url, mime);
				scrobblePending = true;
	
				if (startOffset) {
					$scope.player.seekMsecs(startOffset);
				}
				$scope.player.play();
			}
		}
	}

	/*
	 * Create a debounced function which starts playing the currently selected track.
	 * The debounce is used to limit the number of GET requests when repeatedly changing
	 * the playing track like when rapidly and repeatedly clicking the 'Skip next' button.
	 * Too high number of simultaneous GET requests could easily jam a low-power server.
	 */
	let debouncedPlayCurrentTrack = _.debounce(playCurrentTrack, 300);

	function playTrack(track, startOffset = 0, gapless = false) {
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
			$scope.position.currentPreview = null;
			$scope.position.currentPreview_ts = null;
			$scope.position.currentPreview_tf = null;
			$scope.position.bufferPercent = 0;
			$scope.position.total = 0;
		}
	};

	$scope.$watch('volume', function(newValue, oldValue) {
		// Reset last known volume, if a new value is selected via the slider
		if (newValue && lastVolume && lastVolume !== oldValue) {
			lastVolume = null;
		}

		$scope.player.setVolume(newValue);
		OCA.Music.Storage.set('volume', newValue);
	});

	const notifyPlaybackRateNotAdjustible = _.debounce(
		() => OC.Notification.showTemporary(gettextCatalog.getString('Playback speed not adjustible for the current song')),
		1000, {leading: true, trailing: false}
	);
	$scope.$watch('playbackRate', function(newValue, oldValue) {
		$scope.player.setPlaybackRate(newValue);
		if (oldValue && oldValue != newValue && !$scope.player.playbackRateAdjustible()) {
			notifyPlaybackRateNotAdjustible();
		}
	});

	$scope.offsetVolume = function (offset) {
		const value = $scope.volume + offset;
		$scope.volume = Math.max(0, Math.min(100, value)); // Clamp to 0-100
		lastVolume = null;
	};

	$scope.toggleVolume = function() {
		if (lastVolume) {
			$scope.volume = lastVolume;
			lastVolume = null;
		}
		else {
			lastVolume = $scope.volume;
			$scope.volume = 0;
		}
	};

	$scope.toggleShuffle = function() {
		$scope.shuffle = !$scope.shuffle;
		playlistService.setShuffle($scope.shuffle);
		OCA.Music.Storage.set('shuffle', $scope.shuffle.toString());
	};

	$scope.toggleRepeat = function() {
		let nextState = {
			'false'	: 'true',
			'true'	: 'one',
			'one'	: 'false'
		};
		$scope.repeat = nextState[$scope.repeat];
		playlistService.setRepeat($scope.repeat !== 'false'); // the "repeat-one" is handled internally by the PlayerController
		OCA.Music.Storage.set('repeat', $scope.repeat);
	};

	$scope.setTime = function(position, duration) {
		// sometimes the duration is reported slightly incorrectly and position may exceed it by a few seconds
		if (duration > 0 && duration < position) {
			duration = position;
		}

		$scope.position.current = position;
		$scope.position.total = duration;
		$scope.position.currentPercent = (duration > 0) ? position/duration*100 : 0;
	};

	$scope.setBufferPercentage = function(percent) {
		$scope.position.bufferPercent = Math.min(100, percent);
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

	$scope.stepPlaybackRate = function($event, decrease, rollover) {
		$event?.preventDefault();

		// Round current value to nearest step and in/decrease it by one step
		const current = $scope.playbackRate;
		const steps = 1.0 / PLAYBACK_RATE_STEPPING;
		const value = Math.round(current * steps) / steps;
		const newValue = value + (PLAYBACK_RATE_STEPPING * (decrease ? -1 : 1));

		// Clamp and set value
		if (rollover) {
			if (newValue > PLAYBACK_RATE_MAX) {
				$scope.playbackRate = PLAYBACK_RATE_MIN;
			}
			else if (newValue < PLAYBACK_RATE_MIN) {
				$scope.playbackRate = PLAYBACK_RATE_MAX;
			}
			else {
				$scope.playbackRate = newValue;
			}
		}
		else {
			$scope.playbackRate = Math.max(PLAYBACK_RATE_MIN, Math.min(PLAYBACK_RATE_MAX, newValue));
		}
	};

	// Show context menu on long press of the play/pause button
	$scope.playbackBtnLongPress = function($event) {
		// We don't want the normal click event after the long press has been handled. However, preventing it seems to 
		// be implicit on touch devices (for reason unknown) and calling preventDefault() there would trigger the bug
		// https://github.com/john-doherty/long-press-event/issues/27.
		// The following is a bit hacky work-around for this.
		const isTouch = (('ontouchstart' in window) || (navigator.MaxTouchPoints > 0) || (navigator.msMaxTouchPoints > 0));
		if (!isTouch) {
			$event.preventDefault();
		}

		// 50 ms haptic feedback for touch devices
		if ('vibrate' in navigator) {
			navigator.vibrate(50);
		}

		$timeout(() => $scope.playPauseContextMenuVisible = true);
	};
	// Show context menu on right click of play/pause button, surpress the browser context menu
	$scope.playbackBtnContextMenu = function($event) {
		$event.preventDefault();
		$timeout(() => $scope.playPauseContextMenuVisible = true);
	};
	// hide the popup menu when the user clicks anywhere on the page
	$document.click(function(_event) {
		$timeout(() => $scope.playPauseContextMenuVisible = false);
	});

	$scope.next = function(startOffset = 0, gapless = false) {
		let entry = playlistService.jumpToNextTrack();

		// For ordinary tracks, skip the tracks with unsupported MIME types.
		// For external streams, we don't know the MIME type, and we just assume that they can be played.
		if (entry?.track?.files !== undefined) {
			let tracksSkipped = false;

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
			let track = playlistService.jumpToPrevTrack();
			if (track !== null) {
				setCurrentTrack(track);
			}
		}
	};

	// Seekbar preview mouse support
	function updateSeekPreview(xCoordinate) {
		let seekBar = $('.seek-bar');
		let offsetX = xCoordinate - seekBar.offset().left;
		offsetX = Math.min(Math.max(0, offsetX), seekBar.width());
		let ratio = offsetX / seekBar.width();
		let timestamp = ratio * $scope.position.total;

		$scope.position.previewPercent = ratio * 100;
		$scope.position.currentPreview = timestamp;
	}

	$scope.seekbarPreview = function($event) {
		if ($scope.player.seekingSupported()) {
			updateSeekPreview($event.clientX);
			$scope.position.currentPreview_ts = Date.now();
		}
	};

	$scope.seekbarEnter = function() {
		// Simple filter for transient mouse movements
		$scope.position.currentPreview_tf = $timeout(() => $scope.position.currentPreview_tf = null, 100);
	};

	$scope.seekbarLeave = function() {
		$scope.position.currentPreview = null;
		$scope.position.currentPreview_ts = null;
	};

	// Seekbar preview touch support
	$scope.seekbarTouchLeave = function($event) {
		if ($scope.player.seekingSupported() && $event?.type === 'touchend') {
			$scope.player.seek($scope.position.previewPercent / 100);
			$scope.seekbarLeave();
		}
	};

	$scope.seekbarTouchPreview = function($event) {
		if ($scope.player.seekingSupported()) {
			updateSeekPreview($event.targetTouches[0].clientX);
		}
	};

	$scope.seekOffset = function(offset) {
		if ($scope.player.seekingSupported()) {
			const target = $scope.position.current + offset;
			let ratio = target / $scope.position.total;
			// Clamp between the begin and end of the track
			ratio = Math.min(Math.max(0, ratio), 1);
			$scope.player.seek(ratio);
		}
	};

	// Seekbar click / tap
	$scope.seek = function($event) {
		let seekBar = $('.seek-bar');
		let offsetX = $event.clientX - seekBar.offset().left;
		let ratio = offsetX / seekBar.width();
		$scope.player.seek(ratio);
		$scope.seekbarLeave(); // Reset seek preview
	};

	$scope.seekBackward = $scope.player.seekBackward;

	$scope.seekForward = $scope.player.seekForward;

	playlistService.subscribe('play', function(_event, _playingView = null, startOffset = 0) {
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

	function keyboardShortcutsDisabled() {
		// The global disable switch for the keyboard shortcuts has been introduced by NC25
		return (typeof OCP !== 'undefined') 
				&& (typeof OCP?.Accessibility?.disableKeyboardShortcuts === 'function')
				&& OCP.Accessibility.disableKeyboardShortcuts();
	}

	if (!keyboardShortcutsDisabled()) {
		$document.bind('keydown', function(e) {
			if (!OCA.Music.Utils.isTextEntryElement(e.target)) {
				let func = null, args = [];
				switch (e.code) {
					case 'Space':
					case 'KeyK':
						// Play / Pause / Stop
						func = e.shiftKey ? $scope.stop : $scope.togglePlayback;
						break;
					case 'KeyJ':
						// Seek backwards
						func = $scope.seekOffset;
						args = [e.shiftKey ? -30 : e.altKey ? -1 : -5];
						break;
					case 'KeyL':
						// Seek forwards
						func = $scope.seekOffset;
						args = [e.shiftKey ? +30 : e.altKey ? +1 : +5];
						break;
					case 'KeyM':
						// Mute / Unmute
						func = $scope.toggleVolume;
						break;
					case 'NumpadSubtract':
						// Decrease volume
						func = $scope.offsetVolume;
						args = [e.shiftKey ? -20 : e.altKey ? -1 : -5];
						break;
					case 'NumpadAdd':
						// Increase volume
						func = $scope.offsetVolume;
						args = [e.shiftKey ? +20 : e.altKey ? +1 : +5];
						break;
					case 'ArrowLeft':
						// Previous title / seek backwards
						func = e.ctrlKey ? $scope.prev : $scope.seekOffset;
						args = [e.shiftKey ? -30 : e.altKey ? -1 : -5];
						break;
					case 'ArrowRight':
						// Next title / seek forwards
						func = e.ctrlKey ? $scope.next : $scope.seekOffset;
						args = [e.shiftKey ? +30 : e.altKey ? +1 : +5];
						break;
					case 'Comma': // US: <
						// Decrease playback speed
						func = e.shiftKey ? $scope.stepPlaybackRate : null;
						args = [null, true];
						break;
					case 'Period': // US: >
						// Increase playback speed
						func = e.shiftKey ? $scope.stepPlaybackRate : null;
						break;
					case 'Shift':
					case 'ShiftLeft':
					case 'ShiftRight':
						func = (() => $scope.shiftHeldDown = true);
						break;
				}

				if (func) {
					$timeout(func, 0, true, ...args);
					return false;
				}
			}

			return true;
		});

		$document.bind('keyup', function(e) {
			if (e.key === 'Shift') {
				$timeout(() => $scope.shiftHeldDown = false);
				return false;
			}
			return true;
		});

		$(window).blur(function() {
			$timeout(() => $scope.shiftHeldDown = false);
		});
	}

	$scope.primaryTitle = function() {
		return $scope.currentTrack?.title || $scope.currentTrack?.name
			|| $scope.currentTrack?.metadata?.station || gettextCatalog.getString('Internet radio');
	};

	$scope.secondaryTitle = function() {
		return $scope.currentTrack?.artist?.name || $scope.currentTrack?.channel?.title 
			|| $scope.currentTrack?.metadata?.title || $scope.currentTrack?.stream_url;
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
		'smartlist'			: gettextCatalog.getString('Smart playlist'),
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
		let listId = playlistService.getCurrentPlaylistId();
		if (listId !== null) {
			let key = listId.split('-').slice(0, -1).join('-') || listId;
			return playScopeNames[key];
		} else {
			return '';
		}
	}

	$scope.shuffleTooltip = function() {
		let command = gettextCatalog.getString('Shuffle');
		let cmdScope = $scope.shuffle ? playScopeName() : gettextCatalog.getString('Off');
		return command + ' (' + cmdScope + ')';
	};

	$scope.repeatTooltip = function() {
		let command = gettextCatalog.getString('Repeat');
		let cmdScope = '';
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
	let coverArtToken = null;
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
		let registerMediaControlHandler = function(action, handler) {
			try {
				navigator.mediaSession.setActionHandler(action, function() { $scope.$apply((_scope) => handler()); });
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

		$scope.$watchGroup(['currentTrack', 'currentTrack.metadata.title'], function(newValues) {
			const track = newValues[0];
			if (track) {
				if (track.type === 'radio') {
					navigator.mediaSession.metadata = new MediaMetadata({
						title: $scope.primaryTitle(),
						artist: $scope.secondaryTitle(),
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
						artist: track?.artist?.name,
						album: track?.album?.name ?? track?.channel?.title,
						artwork: [{
							sizes: '190x190',
							src: $scope.coverArt() + (coverArtToken ? ('?coverToken=' + coverArtToken) : ''),
							type: ''
						}]
					});
				}
			}
			else {
				navigator.mediaSession.metadata = null;
			}
		});

		$rootScope.$watch('playing', function(isPlaying) {
			navigator.mediaSession.playbackState = isPlaying ? 'playing' : 'paused';
		});
	}

	/**
	 * Desktop notifications
	 */
	if (typeof Notification !== 'undefined') {
		let notification = null;
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

		$scope.$watchGroup(['currentTrack', 'currentTrack.metadata.title'], function(newValues, oldValues) {
			const track = newValues[0];
			const trackChanged = (track != oldValues[0]);
			let enabled = (OCA.Music.Storage.get('song_notifications') !== 'false');

			// while paused, the changes in radio title are not notified but actual track changes are
			if (enabled && track && ($rootScope.playing || trackChanged)) {
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
