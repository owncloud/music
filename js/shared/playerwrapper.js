/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pellaeon Lin <pellaeon@cnmc.tw>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pellaeon Lin 2015
 * @copyright Pauli Järvinen 2016 - 2022
 */

import Hls from 'node_modules/hls.js/dist/hls.light.js';

OCA.Music = OCA.Music || {};

OCA.Music.PlayerWrapper = function() {
	var m_isIe = $('html').hasClass('ie'); // are we running on Internet Explorer
	var m_underlyingPlayer = null; // set later as 'aurora' or 'html5'
	var m_html5audio = null;
	var m_hls = null;
	var m_aurora = null;
	var m_position = 0;
	var m_duration = 0;
	var m_buffered = 0; // percent
	var m_volume = 100;
	var m_playbackRate = 1.0;
	var m_ready = false;
	var m_playing = false;
	var m_url = null;
	var m_urlType = null; // set later as one of ['local', 'external', 'external-hls']
	var m_mime = null;
	var m_self = this;

	_.extend(this, OC.Backbone.Events);

	function initHtml5() {
		m_html5audio = document.createElement('audio');
		m_html5audio.preload = 'auto';

		if (Hls.isSupported()) {
			m_hls = new Hls({ enableWorker: false });

			m_hls.on(Hls.Events.ERROR, function (_event, data) {
				console.error('HLS error: ' + JSON.stringify(_.pick(data, ['type', 'details', 'fatal'])));
				if (data.fatal) {
					m_self.pause();
					m_self.trigger('error', m_url);
					m_url = null;
				}
			});
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
			if (this.duration > 0) {
				var bufEnd = getBufferedEnd();
				m_buffered = bufEnd / this.duration * 100;
				m_self.trigger('buffer', m_buffered);
				latestNotifiedBufferState = bufEnd;
			}
		};

		m_html5audio.onsuspend = function() {
			this.onprogress();
		};

		m_html5audio.onended = function() {
			m_self.trigger('end');
		};

		m_html5audio.oncanplay = function() {
			m_ready = true;
			m_self.trigger('ready');
		};

		m_html5audio.onerror = function() {
			if (m_underlyingPlayer == 'html5') {
				if (m_url) {
					if (!m_ready && canPlayWithAurora(m_mime)) {
						// Load error encountered before playing could start. The file might be in unsupported format
						// like is the case with M4A-ALAC on most browsers. Fall back to Aurora.js if possible.
						console.log('Cannot play with HTML5, falling back to Aurora.js');
						m_underlyingPlayer = 'aurora';
						initAurora(m_url);
						if (m_playing) {
							m_self.play();
						}
					} else {
						console.log('HTML5 audio: sound load error');
						m_playing = false;
						m_self.trigger('error', m_url);
					}
				} else {
					// an error is fired by the HTML audio when the src is cleared to stop the playback
					m_playing = false;
					m_self.trigger('stop', m_url);
				}
			}
		};

		m_html5audio.onplaying = onPlayStarted;

		m_html5audio.onpause = onPaused;
	}
	initHtml5();

	// Aurora differs from HTML5 player so that it has to be initialized again for each URL
	function initAurora(url) {
		m_aurora = window.AV.Player.fromURL(url);

		m_aurora.on('buffer', function(percent) {
			m_buffered = percent;
			m_self.trigger('buffer', percent);
		});
		m_aurora.on('progress', function(currentTime) {
			m_position = currentTime;
			m_self.trigger('progress', currentTime);
		});
		m_aurora.on('ready', function() {
			m_ready = true;
			m_self.trigger('ready');
		});
		m_aurora.on('end', function() {
			m_self.trigger('end');
		});
		m_aurora.on('duration', function(msecs) {
			m_duration = msecs;
			m_self.trigger('duration', msecs);
		});
		m_aurora.on('error', function(message) {
			console.error('Aurora error: ' + message);
			m_self.trigger('error', m_url);
		});

		m_aurora.preload();
	}

	function onPlayStarted() {
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
		if (m_url) {
			m_playing = true;
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
				if (m_urlType == 'external-hls') {
					m_hls.stopLoad();
					m_hls.detachMedia();
				}
				// On IE, setting the src to empty string would blow up the whole audio element and it wouldn't
				// recover without a page reload. On the other hand, IE doesn't support mediaSession API so this
				// step isn't crucial.
				if (!m_isIe) {
					m_html5audio.src = '';
				}
				m_html5audio.currentTime = 0;
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
		return (m_underlyingPlayer == 'html5' && (m_urlType == 'local' || validDuration));
	};

	this.seekMsecs = function(msecs) {
		if (msecs !== this.playPosition()) {
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
				m_self.fromUrl(url);
				m_self.trigger('progress', 0);
				if (playing) {
					this.play();
				}
			}
			else {
				console.log('seeking is not supported for this file');
			}
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

	this.setPlaybackRate = function(rate) {
		m_playbackRate = rate;

		// Note: the feature is not supported with the Aurora backend
		m_html5audio.playbackRate = m_playbackRate;
	};

	function canPlayWithHtml5(mime) {
		// The m4b format is almost identical with m4a (but intended for audio books).
		// Still, browsers actually able to play m4b files seem to return false when
		// queuring the support for the mime. Hence, a little hack.
		// The m4a files use MIME type 'audio/mp4' while the m4b use 'audio/m4b'.
		return m_html5audio.canPlayType(mime)
			|| (mime == 'audio/m4b' && m_html5audio.canPlayType('audio/mp4'));
	}

	function canPlayWithAurora(mime) {
		return ['audio/flac', 'audio/mpeg', 'audio/mp4', 'audio/m4b', 'audio/aac', 'audio/wav',
				'audio/aiff', 'audio/basic', 'audio/x-aiff', 'audio/x-caf'].includes(mime);
	}

	this.canPlayMime = function(mime) {
		return canPlayWithHtml5(mime) || canPlayWithAurora(mime);
	};

	function doFromUrl(setupUnderlyingPlayer) {
		m_duration = 0; // shall be set to a proper value in a callback from the underlying engine
		m_position = 0;
		m_ready = false;

		m_self.stop(); // clear any previous state first
		m_self.trigger('loading');

		setupUnderlyingPlayer();

		// Set the current volume to the newly created/selected player instance
		m_self.setVolume(m_volume);
		m_self.setPlaybackRate(m_playbackRate);
	}

	this.fromUrl = function(url, mime) {
		doFromUrl(function() {
			m_url = url;
			m_urlType = 'local';
			m_mime = mime;

			if (canPlayWithHtml5(mime)) {
				m_underlyingPlayer = 'html5';
				m_html5audio.src = url;
			} else {
				m_underlyingPlayer = 'aurora';
				initAurora(url);
			}
			console.log('Using ' + m_underlyingPlayer + ' for type ' + mime + ' URL ' + url);
		});
	};

	this.fromExtUrl = function(url, isHls) {
		doFromUrl(function() {
			m_url = url;
			m_mime = null;
			m_underlyingPlayer = 'html5';

			if (isHls && m_hls !== null) {
				m_urlType = 'external-hls';
				m_hls.detachMedia();
				m_hls.loadSource(url);
				m_hls.attachMedia(m_html5audio);
			} else {
				m_urlType = 'external';
				m_html5audio.src = url;
			}
			console.log('URL ' + url + ' played as ' + m_urlType);
		});
	};

	this.getUrl = function() {
		return m_url;
	};

	this.isReady = function() {
		return m_ready;
	};

	this.getDuration = function() {
		return m_duration;
	};

	this.getBufferPercent = function() {
		return m_buffered;
	};
};
