/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2018 - 2020
 */

OCA.Music = OCA.Music || {};

/** @namespace */
OCA.Music.Utils = {

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
	 * Test if the string @a str ends with another string @a search
	 */
	endsWith: function(str, search) {
		return str !== null && search !== null && str.slice(-search.length) === search;
	},

	/**
	 * Capitalizes the firts character of the given string
	 */
	capitalize: function(str) {
		return str && str[0].toUpperCase() + str.slice(1); 
	},

	/**
	 * Creates a track title from the file name, dropping the file extension and any
	 * track number possibly found from the beginning of the file name.
	 */
	titleFromFilename: function(filename) {
		// parsing logic is ported form parseFileName in utility/scanner.php
		var match = filename.match(/^((\d+)\s*[.-]\s+)?(.+)\.(\w{1,4})$/);
		return match ? match[3] : filename;
	},

	/**
	 * Given a file base name, returns the "stem" i.e. the name without extension
	 */
	dropFileExtension: function(filename) {
		return filename.replace(/\.[^/.]+$/, "");
	},

	/**
	 * Join two path fragments together, ensuring there is a single '/' between them.
	 * The first fragment may or may not end with '/' and the second fragment may or may
	 * not start with '/'.
	 */
	joinPath: function(first, second) {
		if (this.endsWith(first, '/')) {
			first = first.slice(0, -1);
		}
		if (this.startsWith(second, '/')) {
			second = second.slice(1);
		}
		return first + '/' + second;
	}
};