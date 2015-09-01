var PlayerWrapper = function() {
	this.underlyingPlayer = 'aurora';
	this.aurora = {};
	this.sm2 = {};
	this.duration = 0;
	var self = this;

	return this;
};

PlayerWrapper.prototype = _.extend({}, OC.Backbone.Events);

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

PlayerWrapper.prototype.seek = function(percentage) {
	console.log('seek to '+percentage);
	switch(this.underlyingPlayer) {
		case 'sm2':
			this.sm2.setPosition(percentage * this.duration);
			break;
		case 'aurora':
			this.aurora.seek(percentage * this.duration);
			break;
	}
};

PlayerWrapper.prototype.fromURL = function(typeAndURL) {
	var self = this;
	console.log(typeAndURL['url']);
	switch(typeAndURL['type']) {
		case 'audio/ogg':
			this.underlyingPlayer = 'sm2';
			break;
		default:
			this.underlyingPlayer = 'aurora';
			break;
	}
	switch(this.underlyingPlayer) {
		case 'sm2':
			this.sm2 = soundManager.setup({
				html5PollingInterval: 200
			});
			this.sm2.html5Only = true;
			this.sm2.createSound({
				id: 'ownCloudSound',
				url: typeAndURL['url'],
				whileplaying: function() {
					self.trigger('progress', this.position);
				},
				whileloading: function() {
					self.duration = this.durationEstimate;
					self.trigger('duration', this.durationEstimate);
					self.trigger('buffer', parseInt(this.bytesLoaded/this.bytesTotal)*100);
				},
				onfinish: function() {
					self.trigger('end');
				},
				onload: function(success) {
					if ( success ) {
					self.trigger('ready');
					} else {
						console.log('SM2: sound load error');
					}
				}
			});
			break;
		case 'aurora':
			this.aurora = AV.Player.fromURL(typeAndURL['url']);
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
	return this;
};
