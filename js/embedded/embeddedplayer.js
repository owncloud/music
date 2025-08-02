/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2017 - 2025
 */

import playIconPath from '../../img/play-big.svg';
import pauseIconPath from '../../img/pause-big.svg';
import skipPreviousIconPath from '../../img/skip-previous.svg';
import skipNextIconPath from '../../img/skip-next.svg';
import closeIconPath from '../../img/close.svg';
import radioIconPath from '../../img/radio-file.svg';
import { VolumeControl } from 'shared/volumecontrol';
import { ProgressInfo } from 'shared/progressinfo';
import { BrowserMediaSession } from 'shared/browsermediasession';


OCA.Music = OCA.Music || {};

OCA.Music.EmbeddedPlayer = function() {

	let player = new OCA.Music.PlayerWrapper();
	let volumeControl = new VolumeControl(player);
	let progressInfo = new ProgressInfo(player);
	let browserMediaSession = new BrowserMediaSession(player);

	// callbacks
	let onClose = null;
	let onNext = null;
	let onPrev = null;
	let onMenuOpen = null;
	let onShowList = null;
	let onImportList = null;
	let onImportRadio = null;

	let nextPrevEnabled = false;
	let playDelayTimer = null;
	let currentFileId = null;

	// UI elements (jQuery)
	let musicControls = null;
	let playButton = null;
	let pauseButton = null;
	let prevButton = null;
	let nextButton = null;
	let coverImageContainer = null;
	let titleText = null;
	let artistText = null;
	let playlistText = null;
	let playlistNumberText = null;
	let playlistMenu = null;

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
		musicControls?.css('display', 'none');
		$('footer').css('display', ''); // undo hiding the footer in public shares
		if (onClose) {
			onClose();
		}
	}

	function previous() {
		// Jump to the beginning of the current track if it has already played more than 2 secs
		if (player.playPosition() > 2000 && !isExternalStream()) {
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
		let area = $(document.createElement('div')).attr('id', 'playlist-area');

		playlistText = $(document.createElement('span')).attr('id', 'playlist-name');
		area.append(playlistText);

		playlistNumberText = $(document.createElement('span'));
		area.append(playlistNumberText);

		if (typeof OCA.Music.playlistTabView != 'undefined') {
			let menuContainer = $(document.createElement('div')).attr('id', 'menu-container');
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
		let menu = $(document.createElement('div'))
					.attr('id', 'playlist-menu')
					.attr('class', 'popovermenu bubble');
		let ul = $(document.createElement('ul'));
		menu.append(ul);

		ul.append(createMenuItem('playlist-menu-import-radio', 'icon-radio-nav svg', t('music', 'Import radio to Music'), onImportRadio));
		ul.append(createMenuItem('playlist-menu-import', 'icon-music-dark svg', t('music', 'Import list to Music'), onImportList));
		ul.append(createMenuItem('playlist-menu-show', 'icon-menu', t('music', 'Show playlist'), onShowList));

		return menu;
	}

	function createMenuItem(id, iconClasses, text, onClick) {
		let li = $(document.createElement('li')).attr('id', id);
		let a = $(document.createElement('a')).click(function(event) {
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
		const container = $(document.createElement('div')).attr('id', 'albumart-container');
		container.append($(document.createElement('div')).attr('id', 'albumart'));
		return container;
	}

	function createInfoProgressContainer() {
		titleText = $(document.createElement('span')).attr('id', 'title');
		artistText = $(document.createElement('span')).attr('id', 'artist');

		let songInfo = $(document.createElement('div')).attr('id', 'song-info');
		songInfo.append(titleText);
		songInfo.append($(document.createElement('br')));
		songInfo.append(artistText);

		let infoProgressContainer = $(document.createElement('div')).attr('id', 'info-and-progress');
		infoProgressContainer.append(songInfo);
		progressInfo.addToContainer(infoProgressContainer);
		return infoProgressContainer;
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
		coverImageContainer = createCoverImage();

		musicControls.append(createPlaylistArea());
		musicControls.append(prevButton);
		musicControls.append(playButton);
		musicControls.append(pauseButton);
		musicControls.append(nextButton);
		musicControls.append(coverImageContainer);
		musicControls.append(createInfoProgressContainer());
		volumeControl.addToContainer(musicControls);
		musicControls.append(createCloseButton());

		// Round also the bottom left corner on NC25 on the share page. The bottom right corner is rounded by default.
		if ($('body#body-public').length > 0) {
			musicControls.css('border-bottom-left-radius', 'var(--body-container-radius)');
		}

		let parentContainer = $('div#app-content');
		if (parentContainer.length === 0) {
			// On NC28, the name and type of the app content container has changed
			parentContainer = $('main#app-content-vue');
		}
		if (parentContainer.length === 0) {
			// On share page before NC25, there's no #app-content. Use #preview element as parent, instead.
			parentContainer = $('div#preview');
			musicControls.css('left', '0');
		}
		let getViewWidth = function() {
			let width = parentContainer.width();
			// On the OC share page and in NC14-24, the parent width has the scroll bar width
			// already subtracted.
			if (OCA.Music.Utils.getScrollContainer()[0] !== window.document) {
				width -= OC.Util.getScrollBarWidth();
			}
			return width;
		};

		parentContainer.append(musicControls);

		// setup dark theme support for Nextcloud versions older than 25
		OCA.Music.DarkThemeLegacySupport.applyOnElement(musicControls[0]);

		// Resize music controls bar to fit the scroll bar when window size changes or details pane opens/closes.
		// Also the internal layout of the bar is responsive to the available width.
		let resizeControls = function() {
			let width = getViewWidth();
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

		// While the z-index from CSS is crucial on ownCloud and older Nextcloud versions, NC28+ doesn't need it. In addition, there it
		// causes the "overlay" scrollbar used by Firefox on Windows 11 to be hidden behind the pane which we don't want.
		if (OCA.Music.Utils.getScrollContainer().is($('#app-content-vue .files-list'))) {
			musicControls.css('z-index', 'unset');
		}

		// bind the player events to change the states of the UI controls
		player.on('play', () => {
			playButton.css('display', 'none');
			pauseButton.css('display', 'inline-block');
		});
		player.on('pause', () => {
			playButton.css('display', 'inline-block');
			pauseButton.css('display', 'none');
		});

		if (onNext) {
			player.on('end', onNext);
		}
	}

	function musicAppLinkElements() {
		return $('#song-info *, #albumart');
	}

	function updateMetadata(data) {
		titleText.text(data.title);
		artistText.text(data.artist);

		let cover = data.cover || OC.imagePath('core', 'filetypes/audio');
		coverImageContainer.find(':first-child').css('background-image', 'url("' + cover + '")');

		browserMediaSession.showInfo(data);
	}

	function loadFileInfoFromUrl(url, fallbackTitle, fileId, callback = null) {
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

	function resolveExtUrl(url, token, callback) {
		$.get(OC.generateUrl('apps/music/api/radio/streamurl'), {url: url, token: token}, callback);
	}

	function changePlayingUrl(playCallback) {
		player.stop();

		// enable controls which may be disabled while loading a playlist
		musicControls.find('.control').removeClass('disabled');
		updateNextButtonStatus();

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
		let url  = OC.generateUrl('apps/music/api/files/{fileId}/info', {'fileId':fileId});
		loadFileInfoFromUrl(url, fallbackTitle, fileId, updateMusicAppLink);
	}

	function updateMusicAppLink(data, fileId) {
		if (data.in_library) {
			let navigateToMusicApp = function() {
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
		let url  = OC.generateUrl('apps/music/api/share/{token}/{fileId}/info',
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

	browserMediaSession.registerControls({
		play: play,
		pause: pause,
		stop: close,
		seekBackward: seekBackward,
		seekForward: seekForward,
		previousTrack: previous,
		nextTrack: next
	});

	/**
	 * PUBLIC INTERFACE
	 */

	this.setCallbacks = function(closeCb, nextCb, prevCb, menuCb, showListCb, importListCb, importRadioCb) {
		onClose = closeCb;
		onNext = nextCb;
		onPrev = prevCb;
		onMenuOpen = menuCb;
		onShowList = showListCb;
		onImportList = importListCb;
		onImportRadio = importRadioCb;
	};

	this.show = function(playlistName = null) {
		if (!musicControls) {
			createUi();
		}

		if (playlistName) {
			musicControls.addClass('with-playlist');
			musicControls.find('.control').addClass('disabled');
			updateMetadata({
				title: t('music', 'Loading…'),
				artist: '',
				cover: null
			});
			playlistNumberText.hide();
			playlistText.text(OCA.Music.Utils.dropFileExtension(playlistName));
		} else {
			musicControls.removeClass('with-playlist');
		}

		musicControls.css('display', 'inline-block');

		// On NC25, the footer shown on publicly shared folders is laid over the music controls. Get rid of it.
		$('footer').css('display', 'none');
	};

	this.showBusy = function(isBusy) {
		if (isBusy) {
			coverImageContainer.addClass('icon-loading');
			$('#menu-container').hide();
		} else {
			coverImageContainer.removeClass('icon-loading');
			$('#menu-container').show();
		}
	};

	this.playFile = function(url, mime, fileId, fileName, shareToken = null) {
		currentFileId = fileId;
		let fallbackTitle = OCA.Music.Utils.titleFromFilename(fileName);
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

	this.playExtUrl = function(url, urlToken, caption) {
		currentFileId = null;
		updateMetadata({
			title: caption,
			artist: url,
			cover: radioIconPath
		});
		changePlayingUrl(function() {
			resolveExtUrl(url, urlToken, function(resolved) {
				player.fromExtUrl(resolved.url, resolved.hls);
				play();
			});
		});
	};

	this.setPlaylistIndex = function(currentIndex, totalCount) {
		playlistNumberText.text((currentIndex + 1) + ' / ' + totalCount);
		playlistNumberText.show();
	};

	this.togglePlayback = togglePlayback;

	this.stop = function() {
		player.stop();
	};
	
	this.close = close;

	this.setNextAndPrevEnabled = function(enabled) {
		nextPrevEnabled = enabled;
		updateNextButtonStatus();
		// "Previous" button is enabled regardless of the list size as it can be used to jump to the beginning of the track
	};

	this.isVisible = function() {
		return musicControls !== null && musicControls.is(':visible');
	};

	this.canPlayMime = function(mime) {
		return player.canPlayMime(mime);
	};
};

