/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2022, 2023
 */

OCA.Music = OCA.Music || {};

OCA.Music.GaplessPlayer = class {
	#currentPlayer = new OCA.Music.PlayerWrapper();
	#nextPlayer = new OCA.Music.PlayerWrapper();

	constructor() {
		_.extend(this, OC.Backbone.Events);
		this.#setupEventPropagation(this.#currentPlayer);
		this.#setupEventPropagation(this.#nextPlayer);
	}

	#setupEventPropagation(player) {
		player.on('all', (eventName, arg) => {
			// propagate events only for the currently active instance
			if (player === this.#currentPlayer) {
				this.trigger(eventName, arg, player.getUrl());
			}
		});
	}

	play() {
		this.#currentPlayer.play();
	}

	pause() {
		this.#currentPlayer.pause();
	}

	stop() {
		this.#currentPlayer.stop();
	}

	isPlaying() {
		return this.#currentPlayer.isPlaying();
	}

	seekingSupported() {
		return this.#currentPlayer.seekingSupported();
	}

	seekMsecs(msecs) {
		this.#currentPlayer.seekMsecs(msecs);
	}

	seek(ratio) {
		this.#currentPlayer.seek(ratio);
	}

	seekForward(msecs /*optional*/) {
		this.#currentPlayer.seekForward(msecs);
	}

	seekBackward(msecs /*optional*/) {
		this.#currentPlayer.seekForward(msecs);
	}

	playPosition() {
		return this.#currentPlayer.playPosition();
	}

	setVolume(percentage) {
		this.#currentPlayer.setVolume(percentage);
		this.#nextPlayer.setVolume(percentage);
	}

	playbackRateAdjustible() {
		return this.#currentPlayer.playbackRateAdjustible();
	}

	setPlaybackRate(rate) {
		this.#currentPlayer.setPlaybackRate(rate);
		this.#nextPlayer.setPlaybackRate(rate);
	}

	canPlayMime(mime) {
		return this.#currentPlayer.canPlayMime(mime);
	}

	isReady() {
		return this.#currentPlayer.isReady();
	}

	getDuration() {
		return this.#currentPlayer.getDuration();
	}

	getBufferPercent() {
		return this.#currentPlayer.getBufferPercent();
	}

	getUrl() {
		return this.#currentPlayer.getUrl();
	}

	fromUrl(url, mime) {
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

	fromExtUrl(url, isHls) {
		this.#currentPlayer.fromExtUrl(url, isHls);
	}

	prepareUrl(url, mime) {
		if (this.#nextPlayer.getUrl() != url) {
			this.#nextPlayer.fromUrl(url, mime);
		}
	}

	#swapPlayer() {
		[this.#currentPlayer, this.#nextPlayer] = [this.#nextPlayer, this.#currentPlayer];
	}
};
