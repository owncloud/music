/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2017, 2018
 */

function initEmbeddedPlayer() {

	var player = new PlayerWrapper();

	var volume = Cookies.get('oc_music_volume') || 50;
	player.setVolume(volume);
	var currentFile = null;
	var playing = false;
	var shareView = false;

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

	function stop() {
		player.stop();
		musicControls.css('display', 'none');
		currentFile = null;
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
			.click(stop);
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
		var delimiter = _.includes(url, '?') ? '&' : '?';
		return url + delimiter + 'requesttoken=' + encodeURIComponent(OC.requestToken);
	}

	function musicAppLinkElements() {
		return $('#song-info *, #albumart');
	}

	function initPlayer(url, mime, title, cover) {
		if (!shareView) {
			url = appendRequestToken(url);
		}
		player.fromURL(url, mime);

		coverImage.css('background-image', cover);
		titleText.text(title);
		artistText.text('');

		musicAppLinkElements().css('cursor', 'default').off("click");
	}

	// Handle 'play' action on file row
	function onFilePlay(fileName, context) {
		showMusicControls();

		// Check if playing file changes
		var filerow = context.$file;
		if (currentFile != filerow.attr('data-id')) {
			currentFile = filerow.attr('data-id');
			player.stop();
			playing = false;

			initPlayer(
					context.fileList.getDownloadUrl(fileName, context.dir),
					filerow.attr('data-mime'),
					t('music', 'Loading…'), // actual title is filled later
					filerow.find('.thumbnail').css('background-image')
			);

			if (!shareView) {
				loadFileInfo(currentFile, fileName);
			} else {
				titleText.text(titleFromFilename(fileName));
			}
		}

		// Play/Pause
		togglePlayback();
	}

	function loadFileInfo(fileId, fileName) {
		var url  = OC.generateUrl('apps/music/api/file/{fileId}/info', {'fileId':fileId});
		$.get(url, function(data) {
			titleText.text(data.title);
			artistText.text(data.artist);

			if (data.cover) {
				coverImage.css('background-image', 'url("' + data.cover + '")');
			}

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
		}).fail(function() {
			titleText.text(titleFromFilename(fileName));
		});
	}

	function titleFromFilename(filename) {
		// parsing logic is ported form parseFileName in utility/scanner.php
		var match = filename.match(/^((\d+)\s*[.-]\s+)?(.+)\.(\w{1,4})$/);
		return match ? match[3] : filename;
	}

	var actionRegisteredForSingleShare = false; // to check that we don't register more than one click handler
	function registerFileActions() {
		var audioMimes = [
			'audio/flac',
			'audio/mp4',
			'audio/m4b',
			'audio/mpeg',
			'audio/ogg',
			'audio/wav'
		];
		var supportedMimes = _.filter(audioMimes, player.canPlayMIME, player);

		// Add play action to file rows with supported mime type.
		// Protect against cases where this script gets (accidentally) loaded outside of the Files app.
		if (typeof OCA.Files !== 'undefined') {
			var registerPlayerForMime = function(mime) {
				OCA.Files.fileActions.register(
						mime,
						'music-play',
						OC.PERMISSION_READ,
						OC.imagePath('music', 'play-big'),
						onFilePlay,
						t('music', 'Play')
				);
				OCA.Files.fileActions.setDefault(mime, 'music-play');
			};
			_.forEach(supportedMimes, registerPlayerForMime);
		}

		// On single-file-share page, add click handler to the file preview if this is a supported file.
		// The feature is disabled on old IE versions where there's no MutationObserver and
		// $.initialize would not work. Also, make sure to add the handler only once even if this method
		// gets called multiple times.
		if ($('#header').hasClass('share-file')
				&& typeof MutationObserver !== "undefined"
				&& !actionRegisteredForSingleShare)
		{
			var mime = $('#mimetype').val();
			if (_.contains(supportedMimes, mime)) {
				actionRegisteredForSingleShare = true;

				// The #publicpreview is added dynamically by another script.
				// Augment it with the click handler once it gets added.
				$.initialize('img.publicpreview', function() {
					var previewImg = $(this);
					previewImg.css('cursor', 'pointer');
					previewImg.click(function() {
						showMusicControls();
						if (!currentFile) {
							currentFile = 1; // bogus id

							initPlayer(
									$('#downloadURL').val(),
									mime,
									titleFromFilename($('#filename').val()),
									'url("' + previewImg.attr('src') + '")'
							);
						}
						togglePlayback();
					});

					// At least in ownCloud 10 and Nextcloud 11-13, there is such an oversight
					// that if MP3 file has no embedded cover, then the placeholder is not shown
					// either. Fix that on our own.
					previewImg.error(function() {
						previewImg.attr('src', OC.imagePath('core', 'filetypes/audio'));
						previewImg.css('width', '128px');
						previewImg.css('height', '128px');
					});
				});
			}
		}
	}

	// Register the play action for the supported mime types both synchronously
	// and asynchronously once the player init is done. This is necessary because
	// the types supported by SoundManager2 are known only in the callback but
	// the callback does not fire at all on browsers with no codecs (some versions
	// of Chromium) where we still can support mp3 and flac formats using aurora.js.
	player.init(registerFileActions);
	registerFileActions();

	return true;
}

$(document).ready(function () {
	// Nextcloud 13 has a built-in Music player in its "individual shared music file" page.
	// Initialize our player only if such player is not found.
	if ($('audio').length == 0) {
		initEmbeddedPlayer();
	}
});
