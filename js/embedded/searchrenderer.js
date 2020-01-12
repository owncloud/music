(function() {
	/**
	 * This custom renderer handles rendering Music app search results shown within the Files app
	 */
	var Music = function() {
		this.initialize();
	};
	/**
	 * @memberof OCA.Search
	 */
	Music.prototype = {
		initialize: function() {
			OC.Plugins.register('OCA.Search', this); // for ownCloud and NextCloud <= 13
			OC.Plugins.register('OCA.Search.Core', this); // for Nextcloud >= 14
		},
		attach: function(search) {
			search.setRenderer('music_artist', this.renderResult);
			search.setRenderer('music_album', this.renderResult);
			search.setRenderer('music_track', this.renderResult);
		},
		renderResult: function($row, item) {
			$row.find('td.icon')
			.css('background-image', 'url(' + OC.imagePath('music', 'music-dark') + ')')
			.css('opacity', '.4');
			return $row;
		}
	};
	OCA.Search.Music = Music;
	OCA.Search.music = new Music();
})();