/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2017 - 2022
 */

import playIconPath from '../../img/play-big.svg';
import pauseIconPath from '../../img/pause-big.svg';
import skipPreviousIconPath from '../../img/skip-previous.svg';
import skipNextIconPath from '../../img/skip-next.svg';
import soundOffIconPath from '../../img/sound-off.svg';
import soundIconPath from '../../img/sound.svg';
import closeIconPath from '../../img/close.svg';
import radioIconPath from '../../img/radio-file.svg';


OCA.Music = OCA.Music || {};

OCA.Music.EmbeddedPlayer = function(onClose, onNext, onPrev, onMenuOpen, onShowList, onImportList, onImportRadio) {

	var player = new OCA.Music.PlayerWrapper();

	var volume = parseInt(localStorage.getItem('oc_music_volume')) || 50;  // volume can be 0~100
	player.setVolume(volume);
	var lastVolume = null;
	var nextPrevEnabled = false;
	var playDelayTimer = null;
	var currentFileId = null;
	var playTimePreview_tf = null; // Transient mouse movement filter (Type: Number/Timer-Handle)
	var playTimePreview_ts = null; // Activation time stamp (Type: Date)
	var playTimePreview_s = null;
	var playTime_s = 0;

	// UI elements (jQuery)
	var musicControls = null;
	var playButton = null;
	var pauseButton = null;
	var prevButton = null;
	var nextButton = null;
	var coverImage = null;
	var titleText = null;
	var artistText = null;
	var playlistText = null;
	var playlistNumberText = null;
	var playlistMenu = null;

	function play() {
		// discard command while switching to new track is ongoing
		if (!playDelayTimer) {
			player.play();
		}
	}

	function pause() {
		// discard command while switching to new track is ongoing
		if (!playDelayTimer) {
			player.pause();
		}
	}

	function togglePlayback() {
		if (player.isPlaying()) {
			pause();
		} else {
			play();
		}
	}

	function close() {
		player.stop();
		musicControls.css('display', 'none');
		$('footer').css('display', ''); // undo hiding the footer in public shares
		onClose();
	}

	function previous() {
		// Jump to the beginning of the current track if it has already played more than 2 secs
		if (playTime_s > 2.0 && !isExternalStream()) {
			player.seek(0);
		}
		// Jump to the previous track if the current track has played only 2 secs or less
		else if (nextPrevEnabled && onPrev) {
			onPrev();
		}
		// Jump to the beginning of the current track even if the track has played less than 2 secs
		// but there's no previous track to jump to
		else {
			player.seek(0);
		}
	}

	function next() {
		if (nextPrevEnabled && onNext) {
			onNext();
		}
	}

	function seekBackward() {
		player.seekBackward();
	}

	function seekForward() {
		player.seekForward();
	}

	function isExternalStream() {
		return currentFileId === null;
	}

	function createPlaylistArea() {
		var area = $(document.createElement('div')).attr('id', 'playlist-area');

		playlistText = $(document.createElement('span')).attr('id', 'playlist-name');
		area.append(playlistText);

		playlistNumberText = $(document.createElement('span'));
		area.append(playlistNumberText);

		if (typeof OCA.Music.PlaylistTabView != 'undefined') {
			var menuContainer = $(document.createElement('div')).attr('id', 'menu-container');
			// "more" button which toggles the popup menu open/closed
			menuContainer.append($(document.createElement('button'))
								.attr('class', 'icon-more')
								.attr('alt', t('music', 'Actions'))
								.click(function(event) {
									if (!playlistMenu.is(':visible')) {
										onMenuOpen(playlistMenu);
									}
									playlistMenu.toggleClass('open');
									event.stopPropagation();
								}));
			// clicking anywhere else in the document closes the menu
			$(document).click(function() { playlistMenu.removeClass('open'); });

			playlistMenu = createPopupMenu();
			menuContainer.append(playlistMenu);
			area.append(menuContainer);
		}

		return area;
	}

	function createPopupMenu() {
		var menu = $(document.createElement('div'))
					.attr('id', 'playlist-menu')
					.attr('class', 'popovermenu bubble');
		var ul = $(document.createElement('ul'));
		menu.append(ul);

		ul.append(createMenuItem('playlist-menu-import-radio', 'icon-radio-nav svg', t('music', 'Import radio to Music'), onImportRadio));
		ul.append(createMenuItem('playlist-menu-import', 'icon-music-dark svg', t('music', 'Import list to Music'), onImportList));
		ul.append(createMenuItem('playlist-menu-show', 'icon-menu', t('music', 'Show playlist'), onShowList));

		return menu;
	}

	function createMenuItem(id, iconClasses, text, onClick) {
		var li = $(document.createElement('li')).attr('id', id);
		var a = $(document.createElement('a')).click(function(event) {
			if (!li.hasClass('disabled')) {
				onClick();
			} else {
				event.stopPropagation(); // clicking the disabled item doesn't close the menu
			}
		});
		a.append($(document.createElement('span')).attr('class', iconClasses));
		a.append($(document.createElement('span')).text(text));
		li.append(a);
		return li;
	}

	function createPlayButton() {
		return $(document.createElement('img'))
			.attr('id', 'play')
			.attr('class', 'control svg')
			.attr('src', playIconPath)
			.attr('alt', t('music', 'Play'))
			.css('display', 'inline-block')
			.click(play);
	}

	function createPauseButton() {
		return $(document.createElement('img'))
			.attr('id', 'pause')
			.attr('class', 'control svg')
			.attr('src', pauseIconPath)
			.attr('alt', t('music', 'Pause'))
			.css('display', 'none')
			.click(pause);
	}

	function createPrevButton() {
		return $(document.createElement('img'))
			.attr('id', 'prev')
			.attr('class', 'control svg small')
			.attr('src', skipPreviousIconPath)
			.attr('alt', t('music', 'Previous'))
			.click(previous);
	}

	function createNextButton() {
		return $(document.createElement('img'))
			.attr('id', 'next')
			.attr('class', 'control svg small disabled')
			.attr('src',  skipNextIconPath)
			.attr('alt', t('music', 'Next'))
			.click(next);
	}

	function createCoverImage() {
		return $(document.createElement('div')).attr('id', 'albumart');
	}

	function createProgressInfo() {
		const fmt = OCA.Music.Utils.formatPlayTime; // give a shorthand name for the utility function

		var container = $(document.createElement('div')).attr('class', 'progress-info');

		var text = $(document.createElement('span')).attr('class', 'progress-text');
		var text_playTime = $(document.createElement('span'));
		var text_seperator = $(document.createElement('span')).append('\xa0/\xa0');
		var text_songLength = $(document.createElement('span'));

		text.append(text_playTime);
		text.append(text_seperator);
		text.append(text_songLength);

		var seekBar = $(document.createElement('div')).attr('class', 'seek-bar');
		var playBar = $(document.createElement('div')).attr('class', 'play-bar');
		var transBar = $(document.createElement('div')).attr('class', 'play-bar translucent');
		var bufferBar = $(document.createElement('div')).attr('class', 'buffer-bar');

		seekBar.append(playBar);
		seekBar.append(transBar);
		seekBar.append(bufferBar);

		var loadingText = $(document.createElement('span')).attr('class', 'progress-text').text(t('music', 'Loading…')).hide();

		container.append(loadingText);
		container.append(text);
		container.append(seekBar);

		// Progress updating
		var songLength_s = 0;

		function updateProgress() {
			var ratio = 0;
			var previewRatio = null;
			if ($.isNumeric(songLength_s)) {
				// Filter transient mouse movements
				var preview = playTimePreview_tf ? null : playTimePreview_s;

				text_playTime.text(fmt(preview ?? playTime_s));
				text_playTime.css('font-style', (preview !== null) ? 'italic' : 'normal');
				ratio = playTime_s / songLength_s;

				// Show progress again instead of preview after a timeout of 2000ms
				if (playTimePreview_ts) {
					var timeSincePreview = Date.now() - playTimePreview_ts;
					if (timeSincePreview >= 2000) {
						seekSetPreview(null);
					} else {
						previewRatio = preview / songLength_s;
					}
				}

				text_seperator.show();
				text_songLength.show();
			} else {
				text_playTime.text(fmt(playTime_s));
				text_seperator.hide();
				text_songLength.hide();
			}

			if (previewRatio === null) {
				playBar.css('width', 100 * ratio + '%');
				transBar.css('width', '0');
			} else {
				playBar.css('width', Math.min(ratio, previewRatio) * 100 + '%');
				transBar.css('left', Math.min(ratio, previewRatio) * 100 + '%');
				transBar.css('width', Math.abs(ratio - previewRatio) * 100 + '%');
			}
		}

		function setCursorType(type) {
			seekBar.css('cursor', type);
			playBar.css('cursor', type);
			bufferBar.css('cursor', type);
		}

		function loadingHide() {
			loadingText.hide();
			text.show();
		}

		function loadingShow() {
			loadingText.show();
			text.hide();
		}

		player.on('loading', function() {
			playTimePreview_tf = null;
			playTimePreview_ts = null;
			playTimePreview_s = null;
			playTime_s = 0;
			songLength_s = 0;
			loadingShow();
			updateProgress();
			bufferBar.css('width', '0');
			setCursorType('default');
		});
		player.on('ready', function() {
			// nothing to do
		});
		player.on('buffer', function(percent) {
			bufferBar.css('width', Math.round(percent) + '%');
		});
		player.on('progress', function(msecs) {
			playTime_s = msecs/1000;
			updateProgress();
		});
		player.on('duration', function(msecs) {
			songLength_s = msecs/1000;
			text_songLength.text(fmt(songLength_s));
			loadingHide();
			updateProgress();
			if (player.seekingSupported()) {
				setCursorType('pointer');
			}
		});
		player.on('play', function() {
			playButton.css('display', 'none');
			pauseButton.css('display', 'inline-block');
		});
		player.on('pause', function() {
			playButton.css('display', 'inline-block');
			pauseButton.css('display', 'none');
		});

		// Seeking
		function seekPositionPercentage(event) {
			var posX = $(seekBar).offset().left;
			return (event.pageX - posX) / seekBar.width();
		}
		function seekPositionTotal(event) {
			var percentage = seekPositionPercentage(event);
			return percentage * player.getDuration();
		}
		function seekSetPreview(value) {
			playTimePreview_ts = (value !== null) ? Date.now() : null;
			playTimePreview_s = value;

			// manually update is necessary if player is not progressing
			// it also feels choppy if we rely on the progress event only
			updateProgress();
		}
		seekBar.click(function (event) {
			var percentage = seekPositionPercentage(event);
			player.seek(percentage);
			seekSetPreview(null); // Reset seek preview
		});

		// Seekbar preview mouse support
		seekBar.mousemove(function(event) {
			if (player.seekingSupported()) {
				seekSetPreview(seekPositionTotal(event) / 1000);
			}
		});
		seekBar.mouseenter(function() {
			// Simple filter for transient mouse movements
			playTimePreview_tf = setTimeout(function() {
				playTimePreview_tf = null;
				updateProgress();
			}, 100);
		});
		seekBar.mouseleave(function() {
			seekSetPreview(null);
			text_playTime.css('font-style', 'normal');
		});

		// Seekbar preview touch support
		seekBar.bind('touchmove', function($event) {
			if (!player.seekingSupported()) return;

			var rect = $event.target.getBoundingClientRect();
			var x = $event.targetTouches[0].clientX - rect.x;
			var offsetX = Math.min(Math.max(0, x), rect.width);
			var ratio = offsetX / rect.width;

			seekSetPreview(ratio * songLength_s);
		});

		seekBar.bind('touchend', function($event) {
			if (!player.seekingSupported() || $event?.type !== 'touchend') return;
			
			// Reverse calculate on seek position
			player.seek(playTimePreview_s / songLength_s);
			
			seekSetPreview(null);
			text_playTime.css('font-style', 'normal');
		});

		return container;
	}

	function createInfoProgressContainer() {
		titleText = $(document.createElement('span')).attr('id', 'title');
		artistText = $(document.createElement('span')).attr('id', 'artist');

		var songInfo = $(document.createElement('div')).attr('id', 'song-info');
		songInfo.append(titleText);
		songInfo.append($(document.createElement('br')));
		songInfo.append(artistText);

		var infoProgressContainer = $(document.createElement('div')).attr('id', 'info-and-progress');
		infoProgressContainer.append(songInfo);
		infoProgressContainer.append(createProgressInfo());
		return infoProgressContainer;
	}

	function createVolumeControl() {
		var volumeControl = $(document.createElement('div'))
			.attr('class', 'volume-control');

		var volumeIcon = $(document.createElement('img'))
			.attr('id', 'volume-icon')
			.attr('class', 'control small svg')
			.attr('src', soundIconPath)
			.on('click', function() {
				const setVolume = function(value) {
					volumeSlider.val(value);
					volumeSlider.trigger('input');
				};

				if (lastVolume) {
					setVolume(lastVolume);
					lastVolume = null;
				}
				else {
					lastVolume = volume;
					setVolume('0');
				}
			});

		var volumeSlider = $(document.createElement('input'))
			.attr('id', 'volume-slider')
			.attr('min', '0')
			.attr('max', '100')
			.attr('type', 'range')
			.attr('value', volume)
			.on('input change', function() {
				const value = $(this).val();

				// Reset last known volume, if a new value is selected via the slider
				if (value && lastVolume && lastVolume !== volume) {
					lastVolume = null;
				}

				volume = value;
				player.setVolume(volume);
				localStorage.setItem('oc_music_volume', volume);
				
				// Show correct icon if muted 
				volumeIcon.attr('src', volume == 0 ? soundOffIconPath : soundIconPath);
			});

		volumeControl.append(volumeIcon);
		volumeControl.append(volumeSlider);

		return volumeControl;
	}

	function createCloseButton() {
		return $(document.createElement('img'))
			.attr('id', 'close')
			.attr('class', 'control small svg')
			.attr('src', closeIconPath)
			.attr('alt', t('music', 'Close'))
			.click(close);
	}

	function createUi() {
		musicControls = $(document.createElement('div')).attr('id', 'music-controls');

		playButton = createPlayButton();
		pauseButton = createPauseButton();
		prevButton = createPrevButton();
		nextButton = createNextButton();
		coverImage = createCoverImage();

		musicControls.append(createPlaylistArea());
		musicControls.append(prevButton);
		musicControls.append(playButton);
		musicControls.append(pauseButton);
		musicControls.append(nextButton);
		musicControls.append(coverImage);
		musicControls.append(createInfoProgressContainer());
		musicControls.append(createVolumeControl());
		musicControls.append(createCloseButton());

		// Round also the bottom left corner on NC25 on the share page. The bottom right corner is rounded by default.
		if ($('body#body-public').length > 0) {
			musicControls.css('border-bottom-left-radius', 'var(--body-container-radius)');
		}

		var parentContainer = $('div#app-content');
		if (parentContainer.length === 0) {
			// On share page before NC25, there's no #app-content. Use #preview element as parent, instead.
			parentContainer = $('div#preview');
			musicControls.css('left', '0');
		}
		var getViewWidth = function() {
			var width = parentContainer.width();
			// On the OC share page and in NC14-24, the parent width has the scroll bar width
			// already subtracted.
			if (OCA.Music.Utils.getScrollContainer()[0] === parentContainer[0]) {
				width -= OC.Util.getScrollBarWidth();
			}
			return width;
		};

		parentContainer.append(musicControls);

		// Resize music controls bar to fit the scroll bar when window size changes or details pane opens/closes.
		// Also the internal layout of the bar is responsive to the available width.
		var resizeControls = function() {
			var width = getViewWidth();
			musicControls.css('width', width);
			if (width > 768) {
				musicControls.removeClass('tablet mobile extra-narrow');
			} else if (width > 600) {
				musicControls.addClass('tablet');
				musicControls.removeClass('mobile extra-narrow');
			} else if (width > 360) {
				musicControls.addClass('tablet mobile');
				musicControls.removeClass('extra-narrow');
			} else {
				musicControls.addClass('tablet mobile extra-narrow');
			}

			// On NC25+, the music-controls pane has a rounded bottom-right corner by default, but this makes no sense when the sidebar is open.
			const sidebarOpen = $('#app-sidebar-vue').length > 0;
			musicControls.css('border-bottom-right-radius', sidebarOpen ? '0' : '');
		};
		parentContainer.resize(resizeControls);
		resizeControls();

		player.on('end', onNext);
	}

	function musicAppLinkElements() {
		return $('#song-info *, #albumart');
	}

	function updateMetadata(data) {
		titleText.text(data.title);
		artistText.text(data.artist);

		var cover = data.cover || OC.imagePath('core', 'filetypes/audio');
		coverImage.css('background-image', 'url("' + cover + '")');

		updateMediaSession(data);
	}

	function loadFileInfoFromUrl(url, fallbackTitle, fileId, callback /*optional*/) {
		$.get(url, function(data) {
			// discard results if the file has already changed by the time the
			// result arrives
			if (currentFileId == fileId) {
				updateMetadata(data);

				if (callback) {
					callback(data, fileId);
				}
			}
		}).fail(function() {
			updateMetadata({
				title: fallbackTitle,
				artist: '',
				cover: null
			});
		});
	}

	function resolveExtUrl(url, callback) {
		$.get(OC.generateUrl('apps/music/api/radio/streamurl'), {url: url}, callback);
	}

	function changePlayingUrl(playCallback) {
		player.stop();

		musicAppLinkElements().css('cursor', 'default').off('click').attr('title', '');
		player.trigger('loading');

		// Add a small delay before actually starting to load any data. This is
		// to avoid flooding HTTP requests in case the user rapidly jumps over
		// tracks.
		if (playDelayTimer) {
			clearTimeout(playDelayTimer);
		}
		playDelayTimer = setTimeout(function() {
			playDelayTimer = null;
			playCallback();
		}, 300);
	}

	function loadFileInfo(fileId, fallbackTitle) {
		var url  = OC.generateUrl('apps/music/api/file/{fileId}/info', {'fileId':fileId});
		loadFileInfoFromUrl(url, fallbackTitle, fileId, updateMusicAppLink);
	}

	function updateMusicAppLink(data, fileId) {
		if (data.in_library) {
			var navigateToMusicApp = function() {
				window.location = OC.generateUrl('apps/music/#/file/{fileId}?offset={offset}',
						{'fileId':fileId, 'offset': player.playPosition()});
			};
			musicAppLinkElements()
				.css('cursor', 'pointer')
				.click(navigateToMusicApp)
				.attr('title', t('music', 'Continue playing in Music'));
		}
		else {
			musicAppLinkElements().attr('title', t('music', '(file is not within your music collection folder)'));
		}
	}

	function loadSharedFileInfo(shareToken, fileId, fallbackTitle) {
		var url  = OC.generateUrl('apps/music/api/share/{token}/{fileId}/info',
				{'token':shareToken, 'fileId':fileId});
		loadFileInfoFromUrl(url, fallbackTitle, fileId);
	}

	function updateNextButtonStatus() {
		if (nextPrevEnabled) {
			nextButton.removeClass('disabled');
		} else {
			nextButton.addClass('disabled');
		}
	}

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
				navigator.mediaSession.setActionHandler(action, handler);
			} catch (error) {
				console.log('The media control "' + action + '" is not supported by the browser');
			}
		};

		registerMediaControlHandler('play', play);
		registerMediaControlHandler('pause', pause);
		registerMediaControlHandler('stop', close);
		registerMediaControlHandler('seekbackward', seekBackward);
		registerMediaControlHandler('seekforward', seekForward);
		registerMediaControlHandler('previoustrack', previous);
		registerMediaControlHandler('nexttrack', next);
	}

	function updateMediaSession(data) {
		if ('mediaSession' in navigator) {
			navigator.mediaSession.metadata = new MediaMetadata({
				title: data.title,
				artist: data.artist,
				album: '',
				artwork: [{
					sizes: '200x200',
					src: data.cover,
					type: ''
				}]
			});
		}
	}

	/**
	 * PUBLIC INTEFACE
	 */

	this.show = function(playlistName /*optional*/) {
		if (!musicControls) {
			createUi();
		}

		if (playlistName) {
			musicControls.addClass('with-playlist');
			playlistText.text(OCA.Music.Utils.dropFileExtension(playlistName));
		} else {
			musicControls.removeClass('with-playlist');
		}

		musicControls.css('display', 'inline-block');

		// On NC25, the footer shown on publicly shared folders is laid over the music controls. Get rid of it.
		$('footer').css('display', 'none');
	};

	this.playFile = function(url, mime, fileId, fileName, /*optional*/ shareToken) {
		currentFileId = fileId;
		var fallbackTitle = OCA.Music.Utils.titleFromFilename(fileName);
		// Set placeholders for track info fields, proper data is filled once received
		updateMetadata({
			title: t('music', 'Loading…'),
			artist: fallbackTitle,
			cover: null
		});
		changePlayingUrl(function() {
			player.fromUrl(url, mime);
			play();

			if (shareToken) {
				loadSharedFileInfo(shareToken, fileId, fallbackTitle);
			} else {
				loadFileInfo(fileId, fallbackTitle);
			}
		});
	};

	this.playExtUrl = function(url, caption, /*optional*/ shareToken) {
		currentFileId = null;
		updateMetadata({
			title: caption,
			artist: url,
			cover: radioIconPath
		});
		changePlayingUrl(function() {
			resolveExtUrl(url, function(resolved) {
				if (shareToken && resolved.hls) {
					OC.Notification.showTemporary(t('music', 'Stream type not supported on shared file'));
				} else {
					player.fromExtUrl(resolved.url, resolved.hls);
					play();
				}
			});
		});
	};

	this.setPlaylistIndex = function(currentIndex, totalCount) {
		playlistNumberText.text((currentIndex + 1) + ' / ' + totalCount);
	};

	this.togglePlayback = togglePlayback;

	this.close = close;

	this.setNextAndPrevEnabled = function(enabled) {
		nextPrevEnabled = enabled;
		updateNextButtonStatus();
		// "Previous" button is enabled regardless of the list size as it can be used to jump to the beginning of the track
	};

	this.isVisible = function() {
		return musicControls !== null && musicControls.is(':visible');
	};
};

