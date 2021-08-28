/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pellaeon Lin <pellaeon@cnmc.tw>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pellaeon Lin 2015
 * @copyright Pauli Järvinen 2016 - 2020
 */

import Hls from 'node_modules/hls.js/dist/hls.light.js';

OCA.Music = OCA.Music || {};

OCA.Music.PlayerWrapper = function() {
	var m_underlyingPlayer = null; // set later as 'aurora' or 'html5'
	var m_html5audio = null;
	var m_hls = null;
	var m_aurora = null;
	var m_position = 0;
	var m_duration = 0;
	var m_volume = 100;
	var m_playing = false;
	var m_url = null;
	var m_streamingExtUrl = false;
	var m_self = this;

	_.extend(this, OC.Backbone.Events);

	function initHtml5() {
		m_html5audio = document.createElement('audio');
		if (Hls.isSupported()) {
			m_hls = new Hls({ enableWorker: false });
		}

		var getBufferedEnd = function() {
			// The buffer may contain holes after seeking but just ignore those.
			// Show the buffering status according the last buffered position.
			var bufCount = m_html5audio.buffered.length;
			return (bufCount > 0) ? m_html5audio.buffered.end(bufCount-1) : 0;
		};
		var latestNotifiedBufferState = null;

		// Bind the various callbacks
		m_html5audio.ontimeupdate = function() {
			// On Firefox, both the last 'progress' event and the 'suspend' event
			// often fire a tad too early, before the 'buffered' state has been
			// updated to its final value. Hence, check here during playback if the
			// buffering state has changed, and fire an extra event if it has.
			if (latestNotifiedBufferState != getBufferedEnd()) {
				this.onprogress();
			}

			m_position = this.currentTime * 1000;
			m_self.trigger('progress', m_position);
		};

		m_html5audio.ondurationchange = function() {
			m_duration = this.duration * 1000;
			m_self.trigger('duration', m_duration);
		};

		m_html5audio.onprogress = function() {
			var bufEnd = getBufferedEnd();
			m_self.trigger('buffer', bufEnd / this.duration * 100);
			latestNotifiedBufferState = bufEnd;
		};

		m_html5audio.onsuspend = function() {
			this.onprogress();
		};

		m_html5audio.onended = function() {
			m_self.trigger('end');
		};

		m_html5audio.oncanplay = function() {
			m_self.trigger('ready');
		};

		m_html5audio.onerror = function() {
			m_playing = false;
			if (m_url) {
				console.log('HTML5 audio: sound load error');
				m_self.trigger('error', m_url);
			} else {
				// ignore stray errors fired by the HTML audio when the src
				// has been cleared (set to invalid).
			}
		};

		m_html5audio.onplaying = onPlayStarted;

		m_html5audio.onpause = onPaused;
	}
	initHtml5();

	function onPlayStarted() {
		m_playing = true;
		m_self.trigger('play');
	}

	function onPaused() {
		m_playing = false;
		if (m_url !== null) {
			m_self.trigger('pause');
		} else {
			m_self.trigger('stop');
		}
	}

	this.play = function() {
		switch (m_underlyingPlayer) {
			case 'html5':
				m_html5audio.play();
				break;
			case 'aurora':
				if (m_aurora) {
					m_aurora.play();
					onPlayStarted(); // Aurora has no callback => fire event synchronously
				}
				break;
		}
	};

	this.pause = function() {
		switch (m_underlyingPlayer) {
			case 'html5':
				m_html5audio.pause();
				break;
			case 'aurora':
				if (m_aurora) {
					m_aurora.pause();
				}
				onPaused(); // Aurora has no callback => fire event synchronously
				break;
		}
	};

	this.stop = function() {
		m_url = null;

		switch (m_underlyingPlayer) {
			case 'html5':
				// Amazingly, there's no 'stop' functionality in the HTML5 audio API, nor is there a way to
				// properly remove the src attribute: setting it to null wold be interpreted as addess
				// "<baseURI>/null" and setting it to empty string will make the src equal the baseURI.
				// Still, resetting the source is necessary to detach the player from the mediaSession API.
				// Just be sure to ignore the resulting 'error' events. Unfortunately, this will still print
				// a warning to the console on Firefox.
				m_html5audio.pause();
				m_html5audio.src = '';
				break;
			case 'aurora':
				if (m_aurora) {
					m_aurora.stop();
				}
				onPaused(); // Aurora has no callback => fire event synchronously
				break;
		}
	};

	this.isPlaying = function() {
		return m_playing;
	};

	this.seekingSupported = function() {
		// Seeking is not implemented in aurora/flac.js and does not work on all
		// files with aurora/mp3.js. Hence, we disable seeking with aurora.
		// Also, seeking requires that we know a valid duration for the file/stream;
		// this is not always the case with external streams. On the other hand, when
		// playing a normal local file, the seeking may be requested before we have fetched
		// the duration and that is fine.
		var validDuration = $.isNumeric(m_duration) && m_duration > 0;
		return (m_underlyingPlayer == 'html5' && (!m_streamingExtUrl || validDuration));
	};

	this.seekMsecs = function(msecs) {
		if (m_self.seekingSupported()) {
			switch (m_underlyingPlayer) {
				case 'html5':
					m_html5audio.currentTime = msecs / 1000;
					break;
				case 'aurora':
					if (m_aurora) {
						m_aurora.seek(msecs);
					}
					break;
			}
		}
		else if (msecs === 0 && m_duration > 0) {
			// seeking to the beginning can be simulated even when seeking in general is not supported
			var url = m_url;
			var playing = m_playing;
			m_self.stop();
			m_self.fromURL(url);
			m_self.trigger('progress', 0);
			if (playing) {
				this.play();
			}
		}
		else {
			console.log('seeking is not supported for this file');
		}
	};

	this.seek = function(ratio) {
		m_self.seekMsecs(ratio * m_duration);
	};

	this.seekForward = function(msecs /*optional*/) {
		msecs = msecs || 10000;
		m_self.seekMsecs(m_position + msecs);
	};

	this.seekBackward = function(msecs /*optional*/) {
		msecs = msecs || 10000;
		m_self.seekMsecs(m_position - msecs);
	};

	this.playPosition = function() {
		return m_position;
	};

	this.setVolume = function(percentage) {
		m_volume = percentage;

		switch (m_underlyingPlayer) {
			case 'html5':
				m_html5audio.volume = m_volume/100;
				break;
			case 'aurora':
				if (m_aurora) {
					m_aurora.volume = m_volume;
				}
				break;
		}
	};

	function canPlayWithHtml5(mime) {
		// The m4b format is almost identical with m4a (but intended for audio books).
		// Still, browsers actually able to play m4b files seem to return false when
		// queuring the support for the mime. Hence, a little hack.
		// The m4a files use MIME type 'audio/mp4' while the m4b use 'audio/m4b'.
		return m_html5audio.canPlayType(mime)
			|| (mime == 'audio/m4b' && m_html5audio.canPlayType('audio/mp4'));
	}

	this.canPlayMIME = function(mime) {
		var canPlayWithAurora = (mime == 'audio/flac' || mime == 'audio/mpeg');
		return canPlayWithHtml5(mime) || canPlayWithAurora;
	};

	function isHlsUrl(url) {
		url = url.toLowerCase();
		return url.endsWith('.m3u8') || url.endsWith('.m3u');
	}

	this.fromURL = function(url, mime) {
		m_duration = 0; // shall be set to a proper value in a callback from the underlying engine
		m_position = 0;
		this.trigger('loading');

		m_url = url;
		m_streamingExtUrl = (mime === null); // null-mime is used to mark an external stream URL

		if (m_streamingExtUrl || canPlayWithHtml5(mime)) {
			m_underlyingPlayer = 'html5';
		} else {
			m_underlyingPlayer = 'aurora';
		}
		console.log('Using ' + m_underlyingPlayer + ' for type ' + mime + ' URL ' + url);

		switch (m_underlyingPlayer) {
			case 'html5':
				m_html5audio.pause();
				if (m_streamingExtUrl && m_hls !== null && isHlsUrl(url)) {
					m_hls.detachMedia();
					m_hls.loadSource(url);
					m_hls.attachMedia(m_html5audio);
				} else {
					m_html5audio.src = url;
				}
				break;

			case 'aurora':
				if (m_aurora) {
					m_aurora.stop();
				}

				m_aurora = window.AV.Player.fromURL(url);
				m_aurora.asset.source.chunkSize=524288;

				m_aurora.on('buffer', function(percent) {
					m_self.trigger('buffer', percent);
				});
				m_aurora.on('progress', function(currentTime) {
					m_position = currentTime;
					m_self.trigger('progress', currentTime);
				});
				m_aurora.on('ready', function() {
					m_self.trigger('ready');
				});
				m_aurora.on('end', function() {
					m_self.trigger('end');
				});
				m_aurora.on('duration', function(msecs) {
					m_duration = msecs;
					m_self.trigger('duration', msecs);
				});

				break;
		}

		// Set the current volume to the newly created/selected player instance
		this.setVolume(m_volume);
	};
};
