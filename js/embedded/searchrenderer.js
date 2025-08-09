/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2020 - 2024
 */

import musicIconPath from '../../img/music-dark.svg';

(function() {
	/**
	 * This custom renderer handles rendering Music app search results shown within the Files app
	 * on ownCloud and older versions of Nextcloud.
	 */
	let Music = function() {
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
		renderResult: function($row, _item) {
			$row.find('td.icon')
			.css('background-image', 'url(' + musicIconPath + ')')
			.css('opacity', '.4');
			return $row;
		}
	};

	// The OCA.Search API is no longer available on NC28+
	if (OCA.Search) {
		OCA.Search.Music = Music;
		OCA.Search.music = new Music();
	}
})();