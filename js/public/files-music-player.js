function EmbeddedPlayer(readyCallback, onClose, onNext, onPrev) {

	var player = new PlayerWrapper();
	player.init(readyCallback);

	var volume = Cookies.get('oc_music_volume') || 50;
	player.setVolume(volume);
	var playing = false;
	var nextPrevEnabled = false;

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
		// resize music controls bar to fit the scroll bar when window size changes or details pane opens/closes
		var resizeControls = function() {
			musicControls.css('width', parentContainer.width() - getScrollBarWidth());
		};

		// On share page, there's no #app-content. Use #preview element as parent, instead.
		// The #preview element's width does not include the scroll bar.
		if (parentContainer.length === 0) {
			parentContainer = $('div#preview');
			resizeControls = function() {
				musicControls.css('width', parentContainer.width());
			};
			musicControls.css('left', '0');
		}

		parentContainer.append(musicControls);
		parentContainer.resize(resizeControls);
		resizeControls();

		player.on('end', onNext);
	}

	function musicAppLinkElements() {
		return $('#song-info *, #albumart');
	}

	function loadFileInfoFromUrl(url, fileBaseName, callback /*optional*/) {
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
			titleText.text(titleFromFilename(fileBaseName));
		});
	}

	function titleFromFilename(fileBaseName) {
		// Parsing logic is ported form parseFileName in utility/scanner.php.
		// Here, however, we assume that the file extension has been stripped already.
		var match = fileBaseName.match(/^((\d+)\s*[.-]\s+)?(.+)$/);
		return match ? match[3] : fileBaseName;
	}

	function init(url, mime) {
		player.stop();
		playing = false;
		player.fromURL(url, mime);

		// Set placeholders for track info fields, proper data is filled once received
		coverImage.css('background-image', 'url("' + OC.imagePath('core', 'filetypes/audio') +'")');
		titleText.text(t('music', 'Loading…'));
		artistText.text('');

		musicAppLinkElements().css('cursor', 'default').off("click");
	}

	function loadFileInfo(fileId, fileBaseName) {
		var url  = OC.generateUrl('apps/music/api/file/{fileId}/info', {'fileId':fileId});
		loadFileInfoFromUrl(url, fileBaseName, function(data) {
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

	function loadSharedFileInfo(shareToken, fileId, fileBaseName) {
		var url  = OC.generateUrl('apps/music/api/share/{token}/{fileId}/info',
				{'token':shareToken, 'fileId':fileId});
		loadFileInfoFromUrl(url, fileBaseName);
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

	this.init = function(url, mime, fileId, fileBaseName) {
		init(url, mime);
		loadFileInfo(fileId, fileBaseName);
	};

	this.initShare = function(url, mime, fileId, fileBaseName, shareToken) {
		init(url, mime);
		loadSharedFileInfo(shareToken, fileId, fileBaseName);
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


function Playlist() {

	var mFolderUrl = null;
	var mFiles = null;
	var mCurrentIndex = null;

	function jumpToOffset(offset) {
		if (!mFiles || mFiles.length <= 1) {
			return null;
		} else {
			mCurrentIndex = (mCurrentIndex + mFiles.length + offset) % mFiles.length;
			return mFiles[mCurrentIndex];
		}
	}

	function stripExtension(filename) {
		return filename.substr(0, filename.lastIndexOf('.')) || filename;
	}

	function initDone(firstFileId, callback) {
		if (mFiles) {
			mCurrentIndex = _.findIndex(mFiles, {fileid: firstFileId});
		}
		if (callback) {
			callback();
		}
	}

	this.init = function(folderUrl, supportedMimes, firstFileId, shareToken, onDone) {
		if (mFolderUrl != folderUrl || !mFiles) {
			mFolderUrl = folderUrl;
			mFiles = null;

			var propFindParams =
				'<?xml version="1.0"?>' +
				'<d:propfind  xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns">' +
				'	<d:prop>' +
				'		<d:getcontenttype/>' +
				'		<oc:fileid/>' +
				'	</d:prop>' +
				'</d:propfind>';

			var headers = !shareToken ? {} : {
				Authorization: 'Basic ' + btoa(shareToken + ':'),
				Range: 'bytes=0-1000'
			};

			$.ajax({
				url: folderUrl,
				method: "PROPFIND",
				data: propFindParams,
				contentType: "application/xml; charset=utf-8",
				dataType: "xml",
				headers: headers,
				success: function(response) {
					mFiles = [];

					$(response).find("d\\:response").each(function() {
						var mime = $(this).find("d\\:getcontenttype").html();
						if (_.contains(supportedMimes, mime)) {
							var url = $(this).find("d\\:href").html();
							var name = decodeURIComponent(OC.basename(url));
							mFiles.push({
								fileid: $(this).find("oc\\:fileid").html(),
								mime: mime,
								name: name,
								basename: stripExtension(name)
							});
						}
					});

					mFiles = _.sortBy(mFiles, function(f) { return f.basename.toLowerCase(); });
					initDone(firstFileId, onDone);
				},
				fail: function() {
					console.warn('PROPFIND failed for folrder URL ' + folderUrl);
					initDone(firstFileId, onDone);
				}
			});
		}
		else {
			initDone(firstFileId, onDone);
		}
	};

	this.next = function() {
		return jumpToOffset(+1);
	};

	this.prev = function() {
		return jumpToOffset(-1);
	};

	this.reset = function() {
		mFolderUrl = null;
		mFiles = null;
		mCurrentIndex = null;
	};

	this.length = function() {
		return mFiles ? mFiles.length : 0;
	};

	// Expose the utility function. This module is not really a logical
	// place for it but creating another module just for one shared function
	// would be cumbersome.
	this.stripExtension = stripExtension;
}
$(document).ready(function() {
	// Nextcloud 13 has a built-in Music player in its "individual shared music file" page.
	// Initialize our player only if such player is not found.
	if ($('audio').length === 0) {
		initEmbeddedPlayer();
	}
});

function initEmbeddedPlayer() {

	var currentFile = null;

	// wrapper function to start playing a file, implementation differs between
	// normal folders and publicly shared ones 
	var setPlayerFile = null;

	var actionRegisteredForSingleShare = false; // to check that we don't register more than one click handler

	// Register the play action for the supported mime types both synchronously
	// and asynchronously once the player init is done. This is necessary because
	// the types supported by SoundManager2 are known only in the callback but
	// the callback does not fire at all on browsers with no codecs (some versions
	// of Chromium) where we still can support mp3 and flac formats using aurora.js.
	var player = new EmbeddedPlayer(register, onClose, onNext, onPrev);
	register();

	var playlist = new Playlist();

	function onClose() {
		currentFile = null;
		playlist.reset();
	}

	function onNext() {
		jumpToPlaylistFile(playlist.next());
	}

	function onPrev() {
		jumpToPlaylistFile(playlist.prev());
	}

	function jumpToPlaylistFile(file) {
		if (!file) {
			player.close();
		} else {
			currentFile = file.fileid;
			setPlayerFile(file);
			player.togglePlayback();
		}
	}

	function appendToken(url) {
		var delimiter = _.includes(url, '?') ? '&' : '?';
		return url + delimiter + 'requesttoken=' + encodeURIComponent(OC.requestToken);
	}

	function register() {
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
			registerFolderPlayer(supportedMimes);
		}

		// Add player on single-fle-share page if the MIME is supported
		if ($('#header').hasClass('share-file')) {
			registerFileSharePlayer(supportedMimes);
		}
	}

	function isShareView() {
		return ($('#sharingToken').length > 0);
	}

	/**
	 * "Folder player" is used in the Files app and on shared folders
	 */
	function registerFolderPlayer(supportedMimes) {
		// Handle 'play' action on file row
		var onPlay = function(fileName, context) {
			player.show();

			// Check if playing file changes
			var filerow = context.$file;
			if (currentFile != filerow.attr('data-id')) {
				currentFile = filerow.attr('data-id');

				var dir = context.dir;

				var shareToken = null;
				var folderUrl = null;

				player.setNextAndPrevEnabled(false);

				if (isShareView()) {
					shareToken = $('#sharingToken').val();
					setPlayerFile = function(file) {
						var url = context.fileList.getDownloadUrl(file.name, dir);
						player.initShare(url, file.mime, file.fileid, file.basename, shareToken);
					};
					folderUrl = OC.linkTo('', 'public.php/webdav' + dir);
				}
				else {
					setPlayerFile = function(file) {
						var url = appendToken(context.fileList.getDownloadUrl(file.name, dir));
						player.init(url, file.mime, file.fileid, file.basename);
					};
					folderUrl = context.fileList.getDownloadUrl('', dir);
				}

				setPlayerFile({
					mime: filerow.attr('data-mime'),
					fileid: currentFile,
					name: fileName,
					basename: playlist.stripExtension(fileName)
				});

				playlist.init(folderUrl, supportedMimes, currentFile, shareToken, function() {
					player.setNextAndPrevEnabled(playlist.length() > 1);
				});
			}

			// Play/Pause
			player.togglePlayback();
		};

		var registerPlayerForMime = function(mime) {
			OCA.Files.fileActions.register(
					mime,
					'music-play',
					OC.PERMISSION_READ,
					OC.imagePath('music', 'play-big'),
					onPlay,
					t('music', 'Play')
			);
			OCA.Files.fileActions.setDefault(mime, 'music-play');
		};
		_.forEach(supportedMimes, registerPlayerForMime);
	}

	/**
	 * "File share player" is used on individually shared files
	 */
	function registerFileSharePlayer(supportedMimes) {
		var onClick = function() {
			player.show();
			if (!currentFile) {
				currentFile = 1; // bogus id

				player.initShare(
						$('#downloadURL').val(),
						$('#mimetype').val(),
						0,
						playlist.stripExtension($('#filename').val()),
						$('#sharingToken').val()
				);
			}
			player.togglePlayback();
		};

		// Add click handler to the file preview if this is a supported file.
		// The feature is disabled on old IE versions where there's no MutationObserver and
		// $.initialize would not work. Also, make sure to add the handler only once even if this method
		// gets called multiple times.
		if (typeof MutationObserver !== "undefined"
				&& !actionRegisteredForSingleShare
				&& _.contains(supportedMimes, $('#mimetype').val()))
		{
			actionRegisteredForSingleShare = true;

			// The #publicpreview is added dynamically by another script.
			// Augment it with the click handler once it gets added.
			$.initialize('img.publicpreview', function() {
				var previewImg = $(this);
				previewImg.css('cursor', 'pointer');
				previewImg.click(onClick);

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

function PlayerWrapper() {
	this.underlyingPlayer = 'aurora';
	this.aurora = {};
	this.sm2 = {};
	this.sm2ready = false;
	this.duration = 0;
	this.volume = 100;

	return this;
}

PlayerWrapper.prototype = _.extend({}, OC.Backbone.Events);

PlayerWrapper.prototype.init = function(onReadyCallback) {
	var self = this;
	this.sm2 = soundManager.setup({
		html5PollingInterval: 200,
		onready: function() {
			self.sm2ready = true;
			onReadyCallback();
		}
	});
};

PlayerWrapper.prototype.play = function() {
	switch(this.underlyingPlayer) {
		case 'sm2':
			this.sm2.play('ownCloudSound');
			break;
		case 'aurora':
			this.aurora.play();
			break;
	}
};

PlayerWrapper.prototype.stop = function() {
	switch(this.underlyingPlayer) {
		case 'sm2':
			this.sm2.stop('ownCloudSound');
			this.sm2.destroySound('ownCloudSound');
			break;
		case 'aurora':
			if(this.aurora.asset !== undefined) {
				// check if player's constructor has been called,
				// if so, stop() will be available
				this.aurora.stop();
			}
			break;
	}
};

PlayerWrapper.prototype.togglePlayback = function() {
	switch(this.underlyingPlayer) {
		case 'sm2':
			this.sm2.togglePause('ownCloudSound');
			break;
		case 'aurora':
			this.aurora.togglePlayback();
			break;
	}
};

PlayerWrapper.prototype.seekingSupported = function() {
	// Seeking is not implemented in aurora/flac.js and does not work on all
	// files with aurora/mp3.js. Hence, we disable seeking with aurora.
	return this.underlyingPlayer == 'sm2';
};

PlayerWrapper.prototype.seek = function(percentage) {
	if (this.seekingSupported()) {
		console.log('seek to '+percentage);
		switch(this.underlyingPlayer) {
			case 'sm2':
				this.sm2.setPosition('ownCloudSound', percentage * this.duration);
				break;
			case 'aurora':
				this.aurora.seek(percentage * this.duration);
				break;
		}
	}
	else {
		console.log('seeking is not supported for this file');
	}
};

PlayerWrapper.prototype.setVolume = function(percentage) {
	this.volume = percentage;

	switch(this.underlyingPlayer) {
		case 'sm2':
			this.sm2.setVolume('ownCloudSound', this.volume);
			break;
		case 'aurora':
			this.aurora.volume = this.volume;
			break;
	}
};

PlayerWrapper.prototype.canPlayMIME = function(mime) {
	// Function soundManager.canPlayMIME should not be called if SM2 is still in the process
	// of being initialized, as it may lead to dereferencing an uninitialized member (see #629).
	var canPlayWithSm2 = (this.sm2ready && soundManager.canPlayMIME(mime));
	var canPlayWithAurora = (mime == 'audio/flac' || mime == 'audio/mpeg');
	return canPlayWithSm2 || canPlayWithAurora;
};

PlayerWrapper.prototype.fromURL = function(url, mime) {
	// ensure there are no active playback before starting new
	this.stop();

	this.trigger('loading');

	if (soundManager.canPlayMIME(mime)) {
		this.underlyingPlayer = 'sm2';
	} else {
		this.underlyingPlayer = 'aurora';
	}
	console.log('Using ' + this.underlyingPlayer + ' for type ' + mime + ' URL ' + url);

	var self = this;
	switch (this.underlyingPlayer) {
		case 'sm2':
			this.sm2.html5Only = true;
			this.sm2.createSound({
				id: 'ownCloudSound',
				url: url,
				whileplaying: function() {
					self.trigger('progress', this.position);
				},
				whileloading: function() {
					self.duration = this.durationEstimate;
					self.trigger('duration', this.durationEstimate);
					// The buffer may contain holes after seeking but just ignore those.
					// Show the buffering status according the last buffered position.
					var bufCount = this.buffered.length;
					var bufEnd = (bufCount > 0) ? this.buffered[bufCount-1].end : 0;
					self.trigger('buffer', bufEnd / this.durationEstimate * 100);
				},
				onsuspend: function() {
					// Work around an issue in Firefox where the last buffered position will almost
					// never equal the duration. See https://github.com/scottschiller/SoundManager2/issues/114.
					// On Firefox, the buffering is *usually* not suspended and this event fires only when the
					// downloading is completed.
					var isFirefox = (typeof InstallTrigger !== 'undefined');
					if (isFirefox) {
						self.trigger('buffer', 100);
					}
				},
				onfinish: function() {
					self.trigger('end');
				},
				onload: function(success) {
					if (success) {
						self.trigger('ready');
					} else {
						console.log('SM2: sound load error');
					}
				}
			});
			break;

		case 'aurora':
			this.aurora = AV.Player.fromURL(url);
			this.aurora.asset.source.chunkSize=524288;

			this.aurora.on('buffer', function(percent) {
				self.trigger('buffer', percent);
			});
			this.aurora.on('progress', function(currentTime) {
				self.trigger('progress', currentTime);
			});
			this.aurora.on('ready', function() {
				self.trigger('ready');
			});
			this.aurora.on('end', function() {
				self.trigger('end');
			});
			this.aurora.on('duration', function(msecs) {
				self.duration = msecs;
				self.trigger('duration', msecs);
			});
			break;
	}

	// Set the current volume to the newly created player instance
	this.setVolume(this.volume);
};
