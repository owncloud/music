/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright 2013 Morris Jobke
 * @copyright 2016, 2017 Pauli Järvinen
 *
 */

angular.module('Music').directive('albumart', [function() {

	function setCoverImage(element, imageUrl) {
		// remove placeholder stuff
		element.html('');
		element.css('background-color', '');
		// add background image
		element.css('background-image', 'url(' + imageUrl + ')');
	}

	function setPlaceholder(element, text) {
		if(text) {
			// remove background image
			element.css('-ms-filter', '');
			element.css('background-image', '');
			// add placeholder stuff
			element.imageplaceholder(text);
			// remove inlined size-related style properties set by imageplaceholder() to allow
			// dynamic changing between mobile and desktop styles when window size changes
			element.css('line-height', '');
			element.css('font-size', '');
			element.css('width', '');
			element.css('height', '');
		}
	}

	return function(scope, element, attrs, ctrl) {

		var onCoverChanged = function() {
			if(attrs.cover) {
				setCoverImage(element, attrs.cover);
			} else {
				setPlaceholder(element, attrs.albumart);
			}
		};

		var onAlbumartChanged = function() {
			if(!attrs.cover) {
				setPlaceholder(element, attrs.albumart);
			}
		};

		attrs.$observe('albumart', onAlbumartChanged);
		attrs.$observe('cover', onCoverChanged);
	};
}]);

