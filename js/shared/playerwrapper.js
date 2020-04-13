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

function PlayerWrapper() {
	var m_underlyingPlayer = 'aurora';
	var m_aurora = {};
	var m_sm2 = {};
	var m_sm2ready = false;
	var m_position = 0;
	var m_duration = 0;
	var m_volume = 100;

	_.extend(this, OC.Backbone.Events);

	this.init = function(onReadyCallback) {
		m_sm2 = soundManager.setup({
			html5PollingInterval: 200,
			onready: function() {
				m_sm2ready = true;
				setTimeout(onReadyCallback, 0);
			}
		});
	};

	this.play = function() {
		switch (m_underlyingPlayer) {
			case 'sm2':
				m_sm2.play('ownCloudSound');
				break;
			case 'aurora':
				m_aurora.play();
				break;
		}
	};

	this.stop = function() {
		switch (m_underlyingPlayer) {
			case 'sm2':
				m_sm2.stop('ownCloudSound');
				m_sm2.destroySound('ownCloudSound');
				break;
			case 'aurora':
				if (m_aurora.asset !== undefined) {
					// check if player's constructor has been called,
					// if so, stop() will be available
					m_aurora.stop();
				}
				break;
		}
	};

	this.togglePlayback = function() {
		switch (m_underlyingPlayer) {
			case 'sm2':
				m_sm2.togglePause('ownCloudSound');
				break;
			case 'aurora':
				m_aurora.togglePlayback();
				break;
		}
	};

	this.seekingSupported = function() {
		// Seeking is not implemented in aurora/flac.js and does not work on all
		// files with aurora/mp3.js. Hence, we disable seeking with aurora.
		return m_underlyingPlayer == 'sm2';
	};

	this.seekMsecs = function(msecs) {
		if (this.seekingSupported()) {
			switch (m_underlyingPlayer) {
				case 'sm2':
					m_sm2.setPosition('ownCloudSound', msecs);
					break;
				case 'aurora':
					m_aurora.seek(msecs);
					break;
			}
		}
		else {
			console.log('seeking is not supported for this file');
		}
	};

	this.seek = function(ratio) {
		this.seekMsecs(ratio * m_duration);
	};

	this.seekForward = function(msecs /*optional*/) {
		msecs = msecs || 10000;
		this.seekMsecs(m_position + msecs);
	};

	this.seekBackward = function(msecs /*optional*/) {
		msecs = msecs || 10000;
		this.seekMsecs(m_position - msecs);
	};

	this.setVolume = function(percentage) {
		m_volume = percentage;

		switch (m_underlyingPlayer) {
			case 'sm2':
				m_sm2.setVolume('ownCloudSound', m_volume);
				break;
			case 'aurora':
				m_aurora.volume = m_volume;
				break;
		}
	};

	this.canPlayMIME = function(mime) {
		// Function soundManager.canPlayMIME should not be called if sm2 is still in the process
		// of being initialized, as it may lead to dereferencing an uninitialized member (see #629).
		var canPlayWithSm2 = (m_sm2ready && soundManager.canPlayMIME(mime));
		var canPlayWithAurora = (mime == 'audio/flac' || mime == 'audio/mpeg');
		return canPlayWithSm2 || canPlayWithAurora;
	};

	this.fromURL = function(url, mime) {
		// ensure there are no active playback before starting new
		this.stop();

		this.trigger('loading');

		if (soundManager.canPlayMIME(mime)) {
			m_underlyingPlayer = 'sm2';
		} else {
			m_underlyingPlayer = 'aurora';
		}
		console.log('Using ' + m_underlyingPlayer + ' for type ' + mime + ' URL ' + url);

		var self = this;
		switch (m_underlyingPlayer) {
			case 'sm2':
				m_sm2.html5Only = true;
				m_sm2.createSound({
					id: 'ownCloudSound',
					url: url,
					whileplaying: function() {
						m_position = this.position;
						self.trigger('progress', m_position);
					},
					whileloading: function() {
						m_duration = this.durationEstimate;
						self.trigger('duration', m_duration);
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
				m_aurora = AV.Player.fromURL(url);
				m_aurora.asset.source.chunkSize=524288;

				m_aurora.on('buffer', function(percent) {
					self.trigger('buffer', percent);
				});
				m_aurora.on('progress', function(currentTime) {
					m_position = currentTime;
					self.trigger('progress', currentTime);
				});
				m_aurora.on('ready', function() {
					self.trigger('ready');
				});
				m_aurora.on('end', function() {
					self.trigger('end');
				});
				m_aurora.on('duration', function(msecs) {
					m_duration = msecs;
					self.trigger('duration', msecs);
				});
				break;
		}

		// Set the current volume to the newly created player instance
		this.setVolume(m_volume);
	};
}
