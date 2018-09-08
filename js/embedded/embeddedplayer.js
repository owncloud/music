/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2017, 2018
 */

function EmbeddedPlayer(readyCallback, onClose, onNext, onPrev) {

	var player = new PlayerWrapper();
	player.init(readyCallback);

	var volume = Cookies.get('oc_music_volume') || 50;
	player.setVolume(volume);
	var playing = false;
	var nextPrevEnabled = false;
	var playDelayTimer = null;
	var currentFileId = null;

	// UI elements (jQuery)
	var musicControls = null;
	var playButton = null;
	var pauseButton = null;
	var prevButton = null;
	var nextButton = null;
	var coverImage = null;
	var titleText = null;
	var artistText = null;


	function togglePlayback() {
		// discard command while switching to new track is ongoing
		if (!playDelayTimer) {
			player.togglePlayback();
			playing = !playing;

			if (playing) {
				playButton.css('display', 'none');
				pauseButton.css('display', 'inline-block');
			} else {
				playButton.css('display', 'inline-block');
				pauseButton.css('display', 'none');
			}
		}
	}

	function close() {
		player.stop();
		musicControls.css('display', 'none');
		onClose();
	}

	function createPlayButton() {
		return $(document.createElement('img'))
			.attr('id', 'play')
			.attr('class', 'control svg')
			.attr('src', OC.imagePath('music', 'play-big'))
			.attr('alt', t('music', 'Play'))
			.css('display', 'inline-block')
			.click(togglePlayback);
	}

	function createPauseButton() {
		return $(document.createElement('img'))
			.attr('id', 'pause')
			.attr('class', 'control svg')
			.attr('src', OC.imagePath('music', 'pause-big'))
			.attr('alt', t('music', 'Pause'))
			.css('display', 'none')
			.click(togglePlayback);
	}

	function createPrevButton() {
		return $(document.createElement('img'))
			.attr('id', 'prev')
			.attr('class', 'control svg small disabled')
			.attr('src', OC.imagePath('music', 'play-previous'))
			.attr('alt', t('music', 'Previous'))
			.click(function() {
				if (nextPrevEnabled && onPrev) {
					onPrev();
				}
			});
	}

	function createNextButton() {
		return $(document.createElement('img'))
			.attr('id', 'next')
			.attr('class', 'control svg small disabled')
			.attr('src', OC.imagePath('music', 'play-next'))
			.attr('alt', t('music', 'Next'))
			.click(function() {
				if (nextPrevEnabled && onNext) {
					onNext();
				}
			});
	}

	function createCoverImage() {
		return $(document.createElement('div')).attr('id', 'albumart');
	}

	function createProgressInfo() {
		var container = $(document.createElement('div')).attr('class', 'progress-info');

		var text = $(document.createElement('span')).attr('class', 'progress-text');

		var seekBar = $(document.createElement('div')).attr('class', 'seek-bar');
		var playBar = $(document.createElement('div')).attr('class', 'play-bar');
		var bufferBar = $(document.createElement('div')).attr('class', 'buffer-bar');

		seekBar.append(playBar);
		seekBar.append(bufferBar);

		container.append(text);
		container.append(seekBar);

		// Progress updating
		var playTime_s = 0;
		var songLength_s = 0;

		function formatTime(seconds) {
			var minutes = Math.floor(seconds/60);
			seconds = Math.floor(seconds - (minutes * 60));
			return minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
		}

		function updateProgress() {
			var ratio = 0;
			if (songLength_s === 0) {
				text.text(t('music', 'Loading…'));
			} else {
				text.text(formatTime(playTime_s) + '/' + formatTime(songLength_s));
				ratio = playTime_s / songLength_s;
			}
			playBar.css('width', 100 * ratio + '%');
		}

		function setCursorType(type) {
			seekBar.css('cursor', type);
			playBar.css('cursor', type);
			bufferBar.css('cursor', type);
		}

		player.on('loading', function () {
			playTime_s = 0;
			songLength_s = 0;
			updateProgress();
			bufferBar.css('width', '0');
			setCursorType('default');
		});
		player.on('ready', function () {
			if (player.seekingSupported()) {
				setCursorType('pointer');
			}
		});
		player.on('buffer', function (percent) {
			bufferBar.css('width', Math.round(percent) + '%');
		});
		player.on('progress', function (msecs) {
			playTime_s = Math.round(msecs/1000);
			updateProgress();
		});
		player.on('duration', function(msecs) {
			songLength_s = Math.round(msecs/1000);
			updateProgress();
		});

		// Seeking
		seekBar.click(function (event) {
			var posX = $(this).offset().left;
			var percentage = (event.pageX - posX) / seekBar.width();
			player.seek(percentage);
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
			.attr('src', OC.imagePath('music', 'sound'));

		var volumeSlider = $(document.createElement('input'))
			.attr('id', 'volume-slider')
			.attr('min', '0')
			.attr('max', '100')
			.attr('type', 'range')
			.attr('value', volume)
			.on('input change', function() {
				volume = $(this).val();
				player.setVolume(volume);
				Cookies.set('oc_music_volume', volume, { expires: 3650 });
			});

		volumeControl.append(volumeIcon);
		volumeControl.append(volumeSlider);

		return volumeControl;
	}

	function createCloseButton() {
		return $(document.createElement('img'))
			.attr('id', 'close')
			.attr('class', 'control small svg')
			.attr('src', OC.imagePath('music', 'close'))
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

		musicControls.append(prevButton);
		musicControls.append(playButton);
		musicControls.append(pauseButton);
		musicControls.append(nextButton);
		musicControls.append(coverImage);
		musicControls.append(createInfoProgressContainer());
		musicControls.append(createVolumeControl());
		musicControls.append(createCloseButton());

		var parentContainer = $('div#app-content');
		var viewWidth = function() {
			var width = parentContainer.width();
			if (!OC_Music_Utils.newLayoutStructure()) {
				// On NC14, the structure has been changed so that scroll bar width
				// is not included in the #app-content width.
				width -= getScrollBarWidth();
			}
			return width;
		};

		// On share page, there's no #app-content. Use #preview element as parent, instead.
		// The #preview element's width does not include the scroll bar.
		if (parentContainer.length === 0) {
			parentContainer = $('div#preview');
			viewWidth = function() {
				return parentContainer.width();
			};
			musicControls.css('left', '0');
		}

		parentContainer.append(musicControls);

		// Resize music controls bar to fit the scroll bar when window size changes or details pane opens/closes.
		// Also the internal layout of the bar is responsive to the available width.
		resizeControls = function() {
			var width = viewWidth();
			musicControls.css('width', width);
			if (width > 768) {
				musicControls.removeClass('tablet mobile extra-narrow');
			} else if (width > 500) {
				musicControls.addClass('tablet');
				musicControls.removeClass('mobile extra-narrow');
			} else if (width > 360) {
				musicControls.addClass('tablet mobile');
				musicControls.removeClass('extra-narrow');
			} else {
				musicControls.addClass('tablet mobile extra-narrow');
			}
		};
		parentContainer.resize(resizeControls);
		resizeControls();

		player.on('end', onNext);
	}

	function musicAppLinkElements() {
		return $('#song-info *, #albumart');
	}

	function loadFileInfoFromUrl(url, fallbackTitle, fileId, callback /*optional*/) {
		$.get(url, function(data) {
			// discard results if the file has already changed by the time the
			// result arrives
			if (currentFileId == fileId) {
				titleText.text(data.title);
				artistText.text(data.artist);

				if (data.cover) {
					coverImage.css('background-image', 'url("' + data.cover + '")');
				}

				if (callback) {
					callback(data);
				}
			}
		}).fail(function() {
			titleText.text(fallbackTitle);
			artistText.text('');
		});
	}

	function titleFromFilename(filename) {
		// parsing logic is ported form parseFileName in utility/scanner.php
		var match = filename.match(/^((\d+)\s*[.-]\s+)?(.+)\.(\w{1,4})$/);
		return match ? match[3] : filename;
	}

	function playUrl(url, mime, tempTitle, nextStep) {
		player.stop();
		playing = false;

		// Set placeholders for track info fields, proper data is filled once received
		coverImage.css('background-image', 'url("' + OC.imagePath('core', 'filetypes/audio') +'")');
		titleText.text(t('music', 'Loading…'));
		artistText.text(tempTitle);
		musicAppLinkElements().css('cursor', 'default').off("click");

		// Add a small delay before actually starting to load any data. This is
		// to avoid flooding HTTP requests in case the user rapidly jumps over
		// tracks.
		if (playDelayTimer) {
			clearTimeout(playDelayTimer);
		}
		playDelayTimer = setTimeout(function() {
			playDelayTimer = null;
			player.fromURL(url, mime);
			togglePlayback();
			nextStep();
		}, 300);
	}

	function loadFileInfo(fileId, fallbackTitle) {
		var url  = OC.generateUrl('apps/music/api/file/{fileId}/info', {'fileId':fileId});
		loadFileInfoFromUrl(url, fallbackTitle, fileId, function(data) {
			if (data.in_library) {
				var navigateToMusicApp = function() {
					window.location = OC.generateUrl('apps/music/#/file/{fileId}', {'fileId':fileId});
				};
				musicAppLinkElements()
					.css('cursor', 'pointer')
					.click(navigateToMusicApp)
					.attr('title', t('music', 'Go to album'));
			}
			else {
				musicAppLinkElements().attr('title', t('music', '(file is not within your music collection folder)'));
			}
		});
	}

	function loadSharedFileInfo(shareToken, fileId, fallbackTitle) {
		var url  = OC.generateUrl('apps/music/api/share/{token}/{fileId}/info',
				{'token':shareToken, 'fileId':fileId});
		loadFileInfoFromUrl(url, fallbackTitle, fileId);
	}


	/**
	 * PUBLIC INTEFACE
	 */

	this.show = function() {
		if (!musicControls) {
			createUi();
		}
		musicControls.css('display', 'inline-block');
	};

	this.playFile = function(url, mime, fileId, fileName, /*optional*/ shareToken) {
		currentFileId = fileId;
		var fallbackTitle = titleFromFilename(fileName);
		playUrl(url, mime, fallbackTitle, function() {
			if (shareToken) {
				loadSharedFileInfo(shareToken, fileId, fallbackTitle);
			} else {
				loadFileInfo(fileId, fallbackTitle);
			}
		});
	};

	this.togglePlayback = function() {
		togglePlayback();
	};

	this.close = function() {
		close();
	};

	this.setNextAndPrevEnabled = function(enabled) {
		nextPrevEnabled = enabled;
		if (enabled) {
			nextButton.removeClass('disabled');
			prevButton.removeClass('disabled');
		} else {
			nextButton.addClass('disabled');
			prevButton.addClass('disabled');
		}
	};
}

