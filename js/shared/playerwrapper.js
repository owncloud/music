/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pellaeon Lin <pellaeon@cnmc.tw>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pellaeon Lin 2015
 * @copyright Pauli Järvinen 2016 - 2023
 */

import Hls from 'node_modules/hls.js/dist/hls.light.js';

OCA.Music = OCA.Music || {};

OCA.Music.PlayerWrapper = class {
	#underlyingPlayer = null; // set later as 'aurora' or 'html5'
	#html5audio = null;
	#hls = null;
	#aurora = null;
	#auroraWorkaroundAudio = null;
	#position = 0;
	#duration = 0;
	#buffered = 0; // percent
	#volume = 100;
	#playbackRate = 1.0;
	#ready = false;
	#playing = false;
	#url = null;
	#urlType = null; // set later as one of ['local', 'external', 'external-hls']
	#mime = null;

	constructor() {
		_.extend(this, OC.Backbone.Events);
		this.#initHtml5();
	}

	#initHtml5() {
		this.#html5audio = new Audio();
		this.#html5audio.preload = 'auto';

		if (Hls.isSupported()) {
			this.#hls = new Hls({ enableWorker: false });

			this.#hls.on(Hls.Events.ERROR, (_event, data) => {
				console.error('HLS error: ' + JSON.stringify(_.pick(data, ['type', 'details', 'fatal'])));
				if (data.fatal) {
					this.pause();
					this.trigger('error', this.#url);
					this.#url = null;
				}
			});
		}

		const getBufferedEnd = () => {
			// The buffer may contain holes after seeking but just ignore those.
			// Show the buffering status according the last buffered position.
			const bufCount = this.#html5audio.buffered.length;
			return (bufCount > 0) ? this.#html5audio.buffered.end(bufCount-1) : 0;
		};
		let latestNotifiedBufferState = null;

		// Bind the various callbacks
		this.#html5audio.ontimeupdate = () => {
			// On Firefox, both the last 'progress' event and the 'suspend' event
			// often fire a tad too early, before the 'buffered' state has been
			// updated to its final value. Hence, check here during playback if the
			// buffering state has changed, and fire an extra event if it has.
			if (latestNotifiedBufferState != getBufferedEnd()) {
				this.#html5audio.onprogress();
			}

			this.#position = this.#html5audio.currentTime * 1000;
			this.trigger('progress', this.#position);
		};

		this.#html5audio.ondurationchange = () => {
			this.#duration = this.#html5audio.duration * 1000;
			this.trigger('duration', this.#duration);
		};

		this.#html5audio.onprogress = () => {
			if (this.#html5audio.duration > 0) {
				let bufEnd = getBufferedEnd();
				this.#buffered = bufEnd / this.#html5audio.duration * 100;
				this.trigger('buffer', this.#buffered);
				latestNotifiedBufferState = bufEnd;
			}
		};

		this.#html5audio.onsuspend = this.#html5audio.onprogress;

		this.#html5audio.onended = () => this.trigger('end');
			this.#playing = false;

		this.#html5audio.oncanplay = () => {
			this.#ready = true;
			this.trigger('ready');
		};

		this.#html5audio.onerror = () => {
			if (this.#underlyingPlayer == 'html5') {
				if (this.#url) {
					if (!this.#ready && this.#canPlayWithAurora(this.#mime)) {
						// Load error encountered before playing could start. The file might be in unsupported format
						// like is the case with M4A-ALAC on most browsers. Fall back to Aurora.js if possible.
						console.log('Cannot play with HTML5, falling back to Aurora.js');
						this.#underlyingPlayer = 'aurora';
						this.#initAurora(this.#url);
						if (this.#playing) {
							this.play();
						}
					} else {
						console.log('HTML5 audio: sound load error');
						this.#playing = false;
						this.trigger('error', this.#url);
					}
				} else {
					// an error is fired by the HTML audio when the src is cleared to stop the playback
					this.#playing = false;
					this.trigger('stop', this.#url);
				}
			}
		};

		this.#html5audio.onplaying = () => this.#onPlayStarted();

		this.#html5audio.onpause = () => this.#onPaused();
	}

	// Aurora differs from HTML5 player so that it has to be initialized again for each URL
	#initAurora(url) {
		this.#aurora = window.AV.Player.fromURL(url);

		this.#aurora.on('buffer', (percent) => {
			this.#buffered = percent;
			this.trigger('buffer', percent);
		});
		this.#aurora.on('progress', (currentTime) => {
			this.#position = currentTime;
			this.trigger('progress', currentTime);
		});
		this.#aurora.on('ready', () => {
			this.#ready = true;
			this.trigger('ready');
		});
		this.#aurora.on('end', () => {
			this.#playing = false;
			this.trigger('end');
		});
		this.#aurora.on('duration', (msecs) => {
			this.#duration = msecs;
			this.trigger('duration', msecs);
		});
		this.#aurora.on('error', (message) => {
			console.error('Aurora error: ' + message);
			this.trigger('error', url);
		});

		this.#aurora.preload();

		/**
		 * The mediaSession API doesn't work fully or at all without an Audio element with a valid source. As a workaround, create one which mirrors the
		 * state of the Aurora backend but cannot be heard by the user.
		 */
		if (this.#auroraWorkaroundAudio === null) {
			this.#auroraWorkaroundAudio = new Audio();
			this.#auroraWorkaroundAudio.loop = true;
			this.#auroraWorkaroundAudio.volume = 0.000001; // The volume must be > 0 for this to work on Firefox but we don't want the user to actually hear this
		}
		this.#auroraWorkaroundAudio.src = OCA.Music.DummyAudio.getData();
	}

	#isIe() {
		return $('html').hasClass('ie'); // are we running on Internet Explorer
	}

	#onPlayStarted() {
		this.trigger('play');
	}

	#onPaused() {
		this.#playing = false;
		if (this.#url !== null) {
			this.trigger('pause');
		} else {
			this.trigger('stop');
		}
	}

	play() {
		if (this.#url) {
			this.#playing = true;
			switch (this.#underlyingPlayer) {
			case 'html5':
				this.#html5audio.play();
				break;
			case 'aurora':
				if (this.#aurora) {
					this.#aurora.play();
					this.#auroraWorkaroundAudio.play();
					this.#onPlayStarted(); // Aurora has no callback => fire event synchronously
				}
				break;
			}
		}
	}

	pause() {
		switch (this.#underlyingPlayer) {
			case 'html5':
				this.#html5audio.pause();
				break;
			case 'aurora':
				if (this.#aurora) {
					this.#aurora.pause();
					this.#auroraWorkaroundAudio.pause();
				}
				this.#onPaused(); // Aurora has no callback => fire event synchronously
				break;
		}
	}

	stop() {
		this.#url = null;
		this.#playing = false;

		switch (this.#underlyingPlayer) {
			case 'html5':
				// Amazingly, there's no 'stop' functionality in the HTML5 audio API, nor is there a way to
				// properly remove the src attribute: setting it to null wold be interpreted as addess
				// "<baseURI>/null" and setting it to empty string will make the src equal the baseURI.
				// Still, resetting the source is necessary to detach the player from the mediaSession API.
				// Just be sure to ignore the resulting 'error' events. Unfortunately, this will still print
				// a warning to the console on Firefox.
				this.#html5audio.pause();
				if (this.#urlType == 'external-hls') {
					this.#hls.stopLoad();
					this.#hls.detachMedia();
				}
				// On IE, setting the src to empty string would blow up the whole audio element and it wouldn't
				// recover without a page reload. On the other hand, IE doesn't support mediaSession API so this
				// step isn't crucial.
				if (!this.#isIe()) {
					this.#html5audio.src = '';
				}
				this.#html5audio.currentTime = 0;
				break;
			case 'aurora':
				if (this.#aurora) {
					this.#aurora.stop();
					this.#aurora = null;
					this.#auroraWorkaroundAudio.pause();
					if (!this.#isIe()) {
						this.#auroraWorkaroundAudio.src = '';
					}
				}
				this.#onPaused(); // Aurora has no callback => fire event synchronously
				break;
		}
	}

	isPlaying() {
		return this.#playing;
	}

	seekingSupported() {
		// Seeking is not implemented in aurora/flac.js and does not work on all
		// files with aurora/mp3.js. Hence, we disable seeking with aurora.
		// Also, seeking requires that we know a valid duration for the file/stream;
		// this is not always the case with external streams. On the other hand, when
		// playing a normal local file, the seeking may be requested before we have fetched
		// the duration and that is fine.
		let validDuration = $.isNumeric(this.#duration) && this.#duration > 0;
		return (this.#underlyingPlayer == 'html5' && (this.#urlType == 'local' || validDuration));
	}

	seekMsecs(msecs) {
		if (msecs !== this.playPosition()) {
			if (this.seekingSupported()) {
				switch (this.#underlyingPlayer) {
					case 'html5':
						this.#html5audio.currentTime = msecs / 1000;
						break;
					case 'aurora':
						if (this.#aurora) {
							this.#aurora.seek(msecs);
						}
						break;
				}
			}
			else if (msecs === 0 && this.#duration > 0) {
				// seeking to the beginning can be simulated even when seeking in general is not supported
				let url = this.#url;
				let playing = this.#playing;
				this.fromUrl(url);
				this.trigger('progress', 0);
				if (playing) {
					this.play();
				}
			}
			else {
				console.log('seeking is not supported for this file');
			}
		}
	}

	seek(ratio) {
		this.seekMsecs(ratio * this.#duration);
	}

	seekForward(msecs /*optional*/) {
		msecs = msecs || 10000;
		this.seekMsecs(this.#position + msecs);
	}

	seekBackward(msecs /*optional*/) {
		msecs = msecs || 10000;
		this.seekMsecs(this.#position - msecs);
	}

	playPosition() {
		return this.#position;
	}

	setVolume(percentage) {
		this.#volume = percentage;

		switch (this.#underlyingPlayer) {
			case 'html5':
				this.#html5audio.volume = this.#volume/100;
				break;
			case 'aurora':
				if (this.#aurora) {
					this.#aurora.volume = this.#volume;
				}
				break;
		}
	}

	playbackRateAdjustible() {
		return (this.#underlyingPlayer == 'html5');
	}

	setPlaybackRate(rate) {
		this.#playbackRate = rate;

		// Note: the feature is not supported with the Aurora backend
		this.#html5audio.playbackRate = this.#playbackRate;
	}

	#canPlayWithHtml5(mime) {
		// The m4b format is almost identical with m4a (but intended for audio books).
		// Still, browsers actually able to play m4b files seem to return false when
		// queuring the support for the mime. Hence, a little hack.
		// The m4a files use MIME type 'audio/mp4' while the m4b use 'audio/m4b'.
		return this.#html5audio.canPlayType(mime)
			|| (mime == 'audio/m4b' && this.#html5audio.canPlayType('audio/mp4'));
	}

	#canPlayWithAurora(mime) {
		return ['audio/flac', 'audio/mpeg', 'audio/mp4', 'audio/m4b', 'audio/aac', 'audio/wav',
				'audio/aiff', 'audio/basic', 'audio/x-aiff', 'audio/x-caf'].includes(mime);
	}

	canPlayMime(mime) {
		return this.#canPlayWithHtml5(mime) || this.#canPlayWithAurora(mime);
	}

	#doFromUrl(setupUnderlyingPlayer) {
		this.#duration = 0; // shall be set to a proper value in a callback from the underlying engine
		this.#position = 0;
		this.#ready = false;

		this.stop(); // clear any previous state first
		this.trigger('loading');

		setupUnderlyingPlayer();

		// Set the current volume to the newly created/selected player instance
		this.setVolume(this.#volume);
		this.setPlaybackRate(this.#playbackRate);
	}

	fromUrl(url, mime) {
		this.#doFromUrl(() => {
			this.#url = url;
			this.#urlType = 'local';
			this.#mime = mime;

			if (this.#canPlayWithHtml5(mime)) {
				this.#underlyingPlayer = 'html5';
				this.#html5audio.src = url;
			} else {
				this.#underlyingPlayer = 'aurora';
				this.#initAurora(url);
			}
			console.log('Using ' + this.#underlyingPlayer + ' for type ' + mime + ' URL ' + url);
		});
	}

	fromExtUrl(url, isHls) {
		this.#doFromUrl(() => {
			this.#url = url;
			this.#mime = null;
			this.#underlyingPlayer = 'html5';

			if (isHls && this.#hls !== null) {
				this.#urlType = 'external-hls';
				this.#hls.detachMedia();
				this.#hls.loadSource(url);
				this.#hls.attachMedia(this.#html5audio);
			} else {
				this.#urlType = 'external';
				this.#html5audio.src = url;
			}
			console.log('URL ' + url + ' played as ' + this.#urlType);
		});
	}

	getUrl() {
		return this.#url;
	}

	isReady() {
		return this.#ready;
	}

	getDuration() {
		return this.#duration;
	}

	getBufferPercent() {
		return this.#buffered;
	}
};
