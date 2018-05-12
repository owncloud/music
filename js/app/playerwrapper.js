var PlayerWrapper = function() {
	this.underlyingPlayer = 'aurora';
	this.aurora = {};
	this.sm2 = {};
	this.sm2ready = false;
	this.duration = 0;
	this.volume = 100;

	return this;
};

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
