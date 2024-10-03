/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2024
 */

import { PlayerWrapper } from "./playerwrapper";

declare function t(module : string, text : string) : string;

export class ProgressInfo {

    #player : PlayerWrapper;
	#elem : JQuery<HTMLElement>;
	#songLength_s : number;
	#playTime_s : number;
	#playTimePreview_tf : NodeJS.Timeout; // Transient mouse movement filter
	#playTimePreview_ts : number; // Activation time stamp (epoch time)
	#playTimePreview_s : number; 

    constructor(player : PlayerWrapper) {
        this.#player = player;
		this.#elem = this.#createHtml();
		this.#connectPlayerEvents();
		this.#connectPointerEvents();
    }

	addToContainer(container : JQuery<HTMLElement>) : void {
		container.append(this.#elem);
	}

	hide() : void {
		this.#elem.hide();
	}

	show() : void {
		this.#elem.show();
	}

	#createHtml() : JQuery<HTMLElement> {
		let container = $('<div class="music-progress-info" />');

		let text = $('<span class="progress-text text-loaded" />');
		$('<span class="play-time" />').appendTo(text);
		$('<span class="separator">\xa0/\xa0</span>').appendTo(text);
		$('<span class="song-length" />').appendTo(text);

		let seekBar = $(document.createElement('div')).attr('class', 'seek-bar');
		$(document.createElement('div')).attr('class', 'play-bar solid').appendTo(seekBar);
		$(document.createElement('div')).attr('class', 'play-bar translucent').appendTo(seekBar);
		$(document.createElement('div')).attr('class', 'buffer-bar').appendTo(seekBar);

		let loadingText = $(document.createElement('span')).attr('class', 'progress-text text-loading').text(t('music', 'Loading…')).hide();

		container.append(loadingText);
		container.append(text);
		container.append(seekBar);

		return container;
	}

	#updateProgress() : void {
		const text_playTime = this.#elem.find('.progress-text .play-time');
		const text_separator = this.#elem.find('.progress-text .separator');
		const text_songLength = this.#elem.find('.progress-text .song-length');
		const playBar = this.#elem.find('.seek-bar .play-bar.solid');
		const transBar = this.#elem.find('.seek-bar .play-bar.translucent');

		let ratio = 0;
		let previewRatio = null;
		
		if (Number.isFinite(this.#songLength_s)) {
			// The song has a valid length

			// Filter transient mouse movements
			let preview = this.#playTimePreview_tf ? null : this.#playTimePreview_s;

			text_playTime.text(OCA.Music.Utils.formatPlayTime(preview ?? this.#playTime_s));
			text_playTime.css('font-style', (preview !== null) ? 'italic' : 'normal');
			ratio = this.#playTime_s / this.#songLength_s;

			// Show progress again instead of preview after a timeout of 2000ms
			if (this.#playTimePreview_ts) {
				let timeSincePreview = Date.now() - this.#playTimePreview_ts;
				if (timeSincePreview >= 2000) {
					this.#seekSetPreview(null);
				} else {
					previewRatio = preview / this.#songLength_s;
				}
			}

			text_separator.show();
			text_songLength.show();
		} else {
			text_playTime.text(OCA.Music.Utils.formatPlayTime(this.#playTime_s));
			text_separator.hide();
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

	#setCursorType(type : string) : void {
		this.#elem.find('.seek-bar, .seek-bar *').css('cursor', type);
	}

	#loadingHide() : void {
		this.#elem.find('.text-loading').hide();
		this.#elem.find('.text-loaded').show();
	}

	#loadingShow() : void {
		this.#elem.find('.text-loading').show();
		this.#elem.find('.text-loaded').hide();
	}

	#clearProgress() : void {
		this.#playTimePreview_tf = null;
		this.#playTimePreview_ts = null;
		this.#playTimePreview_s = null;
		this.#playTime_s = 0;
		this.#songLength_s = null;
		this.#elem.find('.buffer-bar').css('width', '0');
		this.#setCursorType('default');
		this.#updateProgress();
	}

	#seekSetPreview(value : number|null) : void {
		this.#playTimePreview_ts = (value !== null) ? Date.now() : null;
		this.#playTimePreview_s = value;

		// manually update is necessary if player is not progressing
		// it also feels choppy if we rely on the progress event only
		this.#updateProgress();
	}

	#connectPlayerEvents() : void {
		const bufferBar = this.#elem.find('.buffer-bar');
		const text_songLength = this.#elem.find('.progress-text .song-length');

		this.#player.on('loading', () => {
			this.#clearProgress();
			this.#loadingShow();
		});
		this.#player.on('ready', () => {
			this.#loadingHide();
		});
		this.#player.on('buffer', (percent : number) => {
			bufferBar.css('width', Math.round(percent) + '%');
		});
		this.#player.on('progress', (msecs : number) => {
			this.#playTime_s = msecs/1000;
			this.#updateProgress();
		});
		this.#player.on('duration', (msecs : number) => {
			this.#songLength_s = msecs/1000;
			text_songLength.text(OCA.Music.Utils.formatPlayTime(this.#songLength_s));
			this.#loadingHide();
			this.#updateProgress();
			if (this.#player.seekingSupported()) {
				this.#setCursorType('pointer');
			}
		});
		this.#player.on('stop', () => {
			this.#clearProgress();
		});
	}

	#connectPointerEvents() : void {
		const seekBar = this.#elem.find('.seek-bar');
		const text_playTime = this.#elem.find('.progress-text .play-time');

		const seekPositionPercentage = (event : JQuery.Event) : number => {
			let posX = $(seekBar).offset().left;
			return (event.pageX - posX) / seekBar.width();
		};
		const seekPositionTotal = (event : JQuery.Event) : number => {
			let percentage = seekPositionPercentage(event);
			return percentage * this.#player.getDuration();
		};

		seekBar.on('click', (event : JQuery.ClickEvent) => {
			let percentage = seekPositionPercentage(event);
			this.#player.seek(percentage);
			this.#seekSetPreview(null); // Reset seek preview
		});

		// Seekbar preview mouse support
		seekBar.on('mousemove', (event : JQuery.MouseMoveEvent) => {
			if (this.#player.seekingSupported()) {
				this.#seekSetPreview(seekPositionTotal(event) / 1000);
			}
		});
		seekBar.on('mouseenter', () => {
			// Simple filter for transient mouse movements
			this.#playTimePreview_tf = setTimeout(() => {
				this.#playTimePreview_tf = null;
				this.#updateProgress();
			}, 100);
		});
		seekBar.on('mouseleave', () => {
			this.#seekSetPreview(null);
			text_playTime.css('font-style', 'normal');
		});

		// Seekbar preview touch support
		seekBar.on('touchmove', ($event : JQuery.TouchMoveEvent) => {
			if (!this.#player.seekingSupported()) return;

			let rect = $event.target.getBoundingClientRect();
			let x = $event.targetTouches[0].clientX - rect.x;
			let offsetX = Math.min(Math.max(0, x), rect.width);
			let ratio = offsetX / rect.width;

			this.#seekSetPreview(ratio * this.#songLength_s);
		});

		seekBar.on('touchend', ($event : JQuery.TouchEndEvent) => {
			if (!this.#player.seekingSupported() || $event?.type !== 'touchend') return;
			
			// Reverse calculate on seek position
			this.#player.seek(this.#playTimePreview_s / this.#songLength_s);
			
			this.#seekSetPreview(null);
			text_playTime.css('font-style', 'normal');
		});
	}
}