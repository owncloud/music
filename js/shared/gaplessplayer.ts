/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2022 - 2025
 */

import * as _ from 'lodash';
import { PlayerWrapper } from './playerwrapper';

declare const OC : any;

class GaplessPlayer {
	#eventDispatcher : typeof OC.Backbone.Events;
	#currentPlayer = new PlayerWrapper();
	#nextPlayer = new PlayerWrapper();

	constructor() {
		this.#eventDispatcher = _.clone(OC.Backbone.Events);
		this.#setupEventPropagation(this.#currentPlayer);
		this.#setupEventPropagation(this.#nextPlayer);
	}

	on(...args : any[]) : void {
		this.#eventDispatcher.on(...args);
	}

	trigger(...args : any[]) : void {
		this.#eventDispatcher.trigger(...args);
	}

	#setupEventPropagation(player : PlayerWrapper) : void {
		player.on('all', (eventName : string, arg : any) => {
			// propagate events only for the currently active instance
			if (player === this.#currentPlayer) {
				this.trigger(eventName, arg, player.getUrl());
			}
		});
	}

	play() : void {
		this.#currentPlayer.play();
	}

	pause() : void {
		this.#currentPlayer.pause();
	}

	togglePlay() : void {
		this.#currentPlayer.togglePlay();
	}

	stop() : void {
		this.#currentPlayer.stop();
	}

	isPlaying() : boolean {
		return this.#currentPlayer.isPlaying();
	}

	seekingSupported() : boolean {
		return this.#currentPlayer.seekingSupported();
	}

	seekMsecs(msecs : number) : void {
		this.#currentPlayer.seekMsecs(msecs);
	}

	seek(ratio : number) : void {
		this.#currentPlayer.seek(ratio);
	}

	seekForward(msecs : number = 10000) : void {
		this.#currentPlayer.seekForward(msecs);
	}

	seekBackward(msecs : number = 10000) : void {
		this.#currentPlayer.seekBackward(msecs);
	}

	playPosition() : number {
		return this.#currentPlayer.playPosition();
	}

	setVolume(percentage : number) : void {
		this.#currentPlayer.setVolume(percentage);
		this.#nextPlayer.setVolume(percentage);
	}

	playbackRateAdjustable() : boolean {
		return this.#currentPlayer.playbackRateAdjustable();
	}

	setPlaybackRate(rate : number) : void {
		this.#currentPlayer.setPlaybackRate(rate);
		this.#nextPlayer.setPlaybackRate(rate);
	}

	canPlayMime(mime : string) : boolean {
		return this.#currentPlayer.canPlayMime(mime);
	}

	isReady() : boolean {
		return this.#currentPlayer.isReady();
	}

	getDuration() : number {
		return this.#currentPlayer.getDuration();
	}

	getBufferPercent() : number {
		return this.#currentPlayer.getBufferPercent();
	}

	getUrl() : string|null {
		return this.#currentPlayer.getUrl();
	}

	fromUrl(url : string, mime : string) : void {
		this.#swapPlayer();

		if (this.#currentPlayer.getUrl() != url) {
			this.#currentPlayer.fromUrl(url, mime);
		} else {
			// The player already has the correct URL loaded or being loaded. Ensure the playing starts from the
			// beginning and fire the relevant events.
			if (this.#currentPlayer.isReady()) {
				this.trigger('ready', undefined, url);
			}
			if (this.#currentPlayer.getDuration() > 0) {
				this.trigger('duration', this.#currentPlayer.getDuration(), url);
			}
			this.#currentPlayer.seek(0);
			if (this.#currentPlayer.getBufferPercent() > 0) {
				this.trigger('buffer', this.#currentPlayer.getBufferPercent(), url);
			}
		}
	}

	fromExtUrl(url : string, isHls : boolean) : void {
		this.#currentPlayer.fromExtUrl(url, isHls);
	}

	prepareUrl(url : string, mime : string) : void {
		if (this.#nextPlayer.getUrl() != url) {
			this.#nextPlayer.fromUrl(url, mime);
		}
	}

	#swapPlayer() : void {
		[this.#currentPlayer, this.#nextPlayer] = [this.#nextPlayer, this.#currentPlayer];
	}
};

OCA.Music.GaplessPlayer = GaplessPlayer;