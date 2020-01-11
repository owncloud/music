/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2018 - 2020
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
	 * Forms an union of a variable number of arrays, but limits the size of the result
	 * to a supplied maximum number of items. The return value contains the union itself
	 * and a flag indicating if the result had to be truncated or not.
	 */
	limitedUnion: function(maxItems /*, one or more arrays */) {
		if (!maxItems) {
			maxItems = Infinity;
		}

		var result = [];
		var truncated = false;

		for (var i = 1; i < arguments.length && !truncated; ++i) {
			var array = arguments[i];

			if (array.length > maxItems) {
				array = _.first(array, maxItems);
				truncated = true;
			}

			result = _.union(result, array);
			if (result.length > maxItems) {
				result = _.first(result, maxItems);
				truncated = true;
			}
		}

		return {
			result: result,
			truncated: truncated
		};
	}
};