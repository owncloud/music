/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2017, 2018
 */

function EmbeddedPlayer(readyCallback, onClose) {

	var player = new PlayerWrapper();
	player.init(readyCallback);

	var volume = Cookies.get('oc_music_volume') || 50;
	player.setVolume(volume);
	var playing = false;

	// UI elements (jQuery)
	var musicControls = null;
	var playButton = null;
	var pauseButton = null;
	var coverImage = null;
	var titleText = null;
	var artistText = null;


	function togglePlayback() {
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
		coverImage = createCoverImage();

		musicControls.append(playButton);
		musicControls.append(pauseButton);
		musicControls.append(coverImage);
		musicControls.append(createInfoProgressContainer());
		musicControls.append(createVolumeControl());
		musicControls.append(createCloseButton());

		var parentContainer = $('div#app-content');
		if (parentContainer.length === 0) {
			parentContainer = $('div#content-wrapper');
			musicControls.css('left', '0');
		}
		parentContainer.append(musicControls);

		// resize music controls bar to fit the scroll bar when window size changes or details pane opens/closes
		var resizeControls = function() {
			musicControls.css('width', parentContainer.innerWidth() - getScrollBarWidth() + 'px');
		};
		parentContainer.resize(resizeControls);
		resizeControls();

		player.on('end', close);
	}

	function musicAppLinkElements() {
		return $('#song-info *, #albumart');
	}

	function loadFileInfoFromUrl(url, fileName, callback /*optional*/) {
		$.get(url, function(data) {
			titleText.text(data.title);
			artistText.text(data.artist);

			if (data.cover) {
				coverImage.css('background-image', 'url("' + data.cover + '")');
			}

			if (callback) {
				callback(data);
			}
		}).fail(function() {
			titleText.text(titleFromFilename(fileName));
		});
	}

	function titleFromFilename(filename) {
		// parsing logic is ported form parseFileName in utility/scanner.php
		var match = filename.match(/^((\d+)\s*[.-]\s+)?(.+)\.(\w{1,4})$/);
		return match ? match[3] : filename;
	}

	function init(url, mime, cover) {
		player.stop();
		playing = false;
		player.fromURL(url, mime);

		coverImage.css('background-image', cover);
		titleText.text(t('music', 'Loading…')); // actual title is filled later
		artistText.text('');

		musicAppLinkElements().css('cursor', 'default').off("click");
	}

	function loadFileInfo(fileId, fileName) {
		var url  = OC.generateUrl('apps/music/api/file/{fileId}/info', {'fileId':fileId});
		loadFileInfoFromUrl(url, fileName, function(data) {
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

	function loadSharedFileInfo(shareToken, fileId, fileName) {
		var url  = OC.generateUrl('apps/music/api/share/{token}/{fileId}/info',
				{'token':shareToken, 'fileId':fileId});
		loadFileInfoFromUrl(url, fileName);
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

	this.init = function(url, mime, cover, fileId, fileName) {
		init(url, mime, cover);
		loadFileInfo(fileId, fileName);
	};

	this.initShare = function(url, mime, cover, fileId, fileName, shareToken) {
		init(url, mime, cover);
		loadSharedFileInfo(shareToken, fileId, fileName);
	};

	this.togglePlayback = function() {
		togglePlayback();
	};

}

