/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright 2013 Morris Jobke
 * @copyright 2016 - 2019 Pauli Järvinen
 *
 */

angular.module('Music').directive('albumart', [function() {

	function setCoverImage(element, imageUrl) {
		// remove placeholder stuff
		element.html('');
		element.css('background-color', '');

		element.css('background-image', 'url(' + imageUrl + ')');
	}

	function setPlaceholder(element, text) {
		if (text) {
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

	return {
		require: '?^inViewObserver',
		link: function(scope, element, attrs, ctrl) {
			/**
			 * This directive can be used for two different purposes in two different
			 * contexts:
			 * 1. Within a scroling container, in which case there should be inViewObserver
			 *    directive as ancestor of this directive. In this case, inViewObserver is 
			 *    paased to here in the argument `ctrl`.
			 * 2. Within the player control pane, in which case there's no ancestor inViewObserver
			 *    and `ctrl` is null. In this case, the directive observes any changes on the
			 *    related attributes of the element.
			 */

			var onCoverChanged = function() {
				if (attrs.cover) {
					setCoverImage(element, attrs.cover);
				} else {
					setPlaceholder(element, attrs.albumart);
				}
			};

			var onAlbumartChanged = function() {
				if (!attrs.cover) {
					setPlaceholder(element, attrs.albumart);
				}
			};

			if (ctrl) {
				ctrl.registerListener({
					onEnterView: onCoverChanged,
					onLeaveView: function() { /* nothing to do */ }
				});
			}
			else {
				attrs.$observe('albumart', onAlbumartChanged);
				attrs.$observe('cover', onCoverChanged);
			}
		}
	};
}]);

