/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright 2024 Pauli Järvinen
 *
 */

import * as ng from "angular";

ng.module('Music').service('albumartService', [function() {

	function setCoverImage(element : JQuery, imageUrl : string) {
		// remove placeholder stuff
		element.html('');
		element.css('background-color', '');

		element.css('background-image', 'url(' + imageUrl + ')');
	}

	function setPlaceholder(element : JQuery, text : string, seed : string = null) {
		if (text) {
			// remove background image
			element.css('background-image', '');
			// add placeholder stuff (imageplaceholder is an extension registered by the OC/NC server)
			(element as any).imageplaceholder(seed ?? text, text);
			// remove inlined size-related style properties set by imageplaceholder() to allow
			// dynamic changing between mobile and desktop styles when window size changes
			element.css('line-height', '');
			element.css('font-size', '');
			element.css('width', '');
			element.css('height', '');
		}
	}

	return {
		setArt: function(element : JQuery, art : any) {
			if (art) {
				// the `art` may actually be an album, artist, podcast channel, playlist, or radio station object
				if (art.cover) {
					setCoverImage(element, art.cover);
				} else if (art.image) {
					setCoverImage(element, art.image);
				} else if (art.stream_url) {
					setPlaceholder(element, art.name || '?', art.stream_url + art.name);
				} else if (art.name) {
					setPlaceholder(element, art.name, art.artist?.name + art.name);
				} else if (art.title) {
					setPlaceholder(element, art.title);
				}
			}
		}
	};
}]);
