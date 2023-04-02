/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright 2013 Morris Jobke
 * @copyright 2016 - 2022 Pauli Järvinen
 *
 */

angular.module('Music').directive('albumart', [function() {

	function setCoverImage(element, imageUrl) {
		// remove placeholder stuff
		element.html('');
		element.css('background-color', '');

		element.css('background-image', 'url(' + imageUrl + ')');
	}

	function setPlaceholder(element, text, seed /*optional*/) {
		if (text) {
			// remove background image
			element.css('background-image', '');
			// add placeholder stuff
			element.imageplaceholder(seed || text, text);
			// remove inlined size-related style properties set by imageplaceholder() to allow
			// dynamic changing between mobile and desktop styles when window size changes
			element.css('line-height', '');
			element.css('font-size', '');
			element.css('width', '');
			element.css('height', '');
		}
	}

	return {
		require: '?^inViewObserver',
		scope: {
			'albumart': '<'
		},
		link: function(scope, element, _attrs, ctrl) {
			/**
			 * This directive can be used for two different purposes in two different
			 * contexts:
			 * 1. Within a scrolling container, in which case there should be inViewObserver
			 *    directive as ancestor of this directive. In this case, inViewObserver is 
			 *    passed to here in the argument `ctrl`.
			 * 2. Within the player control pane, in which case there's no ancestor inViewObserver
			 *    and `ctrl` is null. In this case, the directive observes any changes on the
			 *    related attributes of the element.
			 */

			var loadAlbumart = function() {
				const art = scope.albumart;
				if (art) {
					// the "albumart" may actually be an album or podcast channel object
					if (art.cover) {
						setCoverImage(element, art.cover);
					} else if (art.image) {
						setCoverImage(element, art.image);
					} else if (art.name) {
						setPlaceholder(element, art.name, art.artist.name + art.name);
					} else if (art.title) {
						setPlaceholder(element, art.title);
					}
				}
			};

			if (ctrl) {
				ctrl.registerListener({
					onEnterView: loadAlbumart,
					onLeaveView: function() { /* nothing to do */ }
				});
			}
			else {
				scope.$watch('albumart', loadAlbumart);
			}
		}
	};
}]);

