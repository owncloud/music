/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright 2019 Pauli Järvinen
 *
 */

angular.module('Music').service('inViewService', ['$rootScope', function($rootScope) {

	var dirty = true;
	var headerHeight = null;
	var appViewHeight = null;

	$rootScope.$on('resize', function() {
		dirty = true;
	});

	function updateHeights() {
		var appView = document.getElementById('app-view');
		var header = document.getElementById('header');

		headerHeight = header.offsetHeight;
		appViewHeight = appView.offsetHeight;

		dirty = false;
	}

	return {
		/**
		 * Check if the given element is within the viewport, optionally defining
		 * margins which effectively enlarge (or shrink) the area where the element
		 * is considered to be "within viewport".
		 * @param el And HTML element
		 * @param int topMargin Optional top extension in pixels (use negative value for reduction)
		 * @param int bottomMargin Optional bottom extension in pixels (use negative value for reduction)
		 */
		isElementInViewPort: function(el, topMargin/*optional*/, bottomMargin/*optional*/) {
			if (el) {
				topMargin = topMargin || 0;
				bottomMargin = bottomMargin || 0;

				if (dirty) {
					updateHeights();
				}

				var viewPortTop = headerHeight - topMargin;
				var viewPortBottom = headerHeight + appViewHeight + bottomMargin;

				var rect = el.getBoundingClientRect();
				return rect.bottom >= viewPortTop && rect.top <= viewPortBottom;
			}
			else {
				return false;
			}
		}
	};
}]);
