/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2017
 */

$(document).ready(function () {

	var player = new PlayerWrapper();
	player.setVolume(50);
	var currentFile = null;
	var playing = false;
	var shareView = false;

	// UI elements (jQuery)
	var musicControls = null;
	var playButton = null;
	var pauseButton = null;
	var coverImage = null;
	var titleText = null;

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

	function stop() {
		player.stop();
		musicControls.css('display', 'none');
		currentFile = null;
	}

	function createUi() {
		musicControls = $(document.createElement('div'))
			.attr('id', 'music-controls');

		playButton = $(document.createElement('img'))
			.attr('id', 'play')
			.attr('class', 'control svg')
			.attr('src', OC.imagePath('music', 'play-big'))
			.attr('alt', t('music', 'Play'))
			.css('display', 'inline-block')
			.click(togglePlayback);

		pauseButton = $(document.createElement('img'))
			.attr('id', 'pause')
			.attr('class', 'control svg')
			.attr('src', OC.imagePath('music', 'pause-big'))
			.attr('alt', t('music', 'Pause'))
			.css('display', 'none')
			.click(togglePlayback);

		coverImage = $(document.createElement('div'))
			.attr('id', 'albumart');

		titleText = $(document.createElement('span'))
			.attr('id', 'title');

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
			.on('input', function() {
				player.setVolume($(this).val());
			});

		var closeButton = $(document.createElement('img'))
			.attr('id', 'close')
			.attr('class', 'control small svg')
			.attr('src', OC.imagePath('music', 'close'))
			.attr('alt', t('music', 'Close'))
			.click(stop);

		volumeControl.append(volumeIcon);
		volumeControl.append(volumeSlider);

		musicControls.append(playButton);
		musicControls.append(pauseButton);
		musicControls.append(coverImage);
		musicControls.append(titleText);
		musicControls.append(volumeControl);
		musicControls.append(closeButton);

		var parentContainer = $('div#app-content');
		if (parentContainer.length === 0) {
			shareView = true;
			parentContainer = $('div#preview');
			musicControls.css('left', '0');
		}
		parentContainer.append(musicControls);

		// resize music controls bar to fit the scroll bar when window size changes or details pane opens/closes
		var resizeControls = function() {
			musicControls.css('width', parentContainer.innerWidth() - getScrollBarWidth() + 'px');
		};
		parentContainer.resize(resizeControls);
		resizeControls();

		player.on('end', stop);
	}

	function showMusicControls() {
		if (!musicControls) {
			createUi();
		}
		musicControls.css('display', 'inline-block');
	}

	function appendRequestToken(url) {
		var delimiter = url.includes('?') ? '&' : '?';
		return url + delimiter + 'requesttoken=' + encodeURIComponent(OC.requestToken);
	}

	function initPlayer(url, mime, title, cover) {
		if (!shareView) {
			url = appendRequestToken(url);
		}
		player.fromURL(url, mime);
		coverImage.css('background-image', cover);
		titleText.text(title);
	}

	// Handle 'play' action on file row
	function onFilePlay(filename, context) {
		showMusicControls();

		// Check if playing file changes
		var filerow = context.$file;
		if (currentFile != filerow.attr('data-id')) {
			currentFile = filerow.attr('data-id');
			player.stop();
			playing = false;

			initPlayer(
					context.fileList.getDownloadUrl(filename, context.dir),
					filerow.attr('data-mime'),
					filename,
					filerow.find('.thumbnail').css('background-image')
			);
		}

		// Play/Pause
		togglePlayback();
	}

	// add play action to file rows with mime type 'audio/*'
	OCA.Files.fileActions.register(
			'audio',
			'music-play',
			OC.PERMISSION_READ,
			OC.imagePath('music', 'play-big'),
			onFilePlay,
			t('music', 'Play')
	);
	OCA.Files.fileActions.setDefault('audio', 'music-play');

	// on single-file-share page, add click handler to the file preview if it is an audio file
	if ($('#header').hasClass('share-file')) {
		var mime = $('#mimetype').val();
		if (mime.startsWith('audio')) {

			// The #publicpreview is added dynamically by another script.
			// Augment it with the click handler once it gets added.
			$.initialize('img.publicpreview', function() {
				$(this).css('cursor', 'pointer');
				$(this).click(function() {
					showMusicControls();
					if (!currentFile) {
						currentFile = 1; // bogus id

						initPlayer(
								$('#downloadURL').val(),
								mime,
								$('#filename').val(),
								'url(' + $(this).attr('src') + ')'
						);
					}
					togglePlayback();
				});
			});
		}
	}

	return true;
});
