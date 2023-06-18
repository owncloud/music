/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright 2019 - 2023 Pauli Järvinen
 *
 */

import * as ng from "angular";

ng.module('Music').service('inViewService', ['$rootScope', function($rootScope : ng.IRootScopeService) {

	let dirty = true;
	let headerHeight = 0;
	let appViewHeight = 0;

	$rootScope.$on('resize', () => dirty = true);

	function updateHeights() : void {
		const appView = document.getElementById('app-view');
		const header = document.getElementById('header');

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
		isElementInViewPort: function(el : HTMLElement, topMargin = 0, bottomMargin = 0) : boolean {
			if (el) {
				if (dirty) {
					updateHeights();
				}

				let viewPortTop = headerHeight - topMargin;
				let viewPortBottom = headerHeight + appViewHeight + bottomMargin;

				let rect = el.getBoundingClientRect();
				return rect.bottom >= viewPortTop && rect.top <= viewPortBottom;
			}
			else {
				return false;
			}
		}
	};
}]);
