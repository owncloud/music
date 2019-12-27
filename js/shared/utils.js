/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2018, 2019
 */

/** @namespace */
var OC_Music_Utils = {

	/**
	 * Nextcloud 14 has a new overall layout structure which requires some
	 * changes on the application logic.
	 */
	newLayoutStructure: function() {
		// Detect the new structure from the presence of the #content-wrapper element.
		return $('#content-wrapper').length === 0;
	},

	/**
	 * Newer versions of Nextcloud come with a "dark theme" which may be activated
	 * from the accessibility settings. Test if the theme is active.
	 */
	darkThemeActive: function() {
		// The name of the theme was originally 'themedark' but changed to simply 'dark' in NC18.
		return OCA.hasOwnProperty('Accessibility')
			&& (OCA.Accessibility.theme == 'themedark' || OCA.Accessibility.theme == 'dark');
	},

	/**
	 * Test if the string @a str starts with another string @a search
	 */
	startsWith: function(str, search) {
		return str !== null && search !== null && str.slice(0, search.length) === search;
	},

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

			var appView = document.getElementById('app-view');
			var header = document.getElementById('header');
			var viewPortTop = header.offsetHeight - topMargin;
			var viewPortBottom = header.offsetHeight + appView.offsetHeight + bottomMargin;

			var rect = el.getBoundingClientRect();
			return rect.bottom >= viewPortTop && rect.top <= viewPortBottom;
		}
		else {
			return false;
		}
	}
};