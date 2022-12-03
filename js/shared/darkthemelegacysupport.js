/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2022
 */

OCA.Music = OCA.Music || {};

/** @namespace */
OCA.Music.DarkThemeLegacySupport = {
	applyOnElement: function(element) {
		if (getComputedStyle(element).getPropertyValue('--background-invert-if-dark') == '') {
			// The property is not available => Nextcloud < 25 or ownCloud.
			
			// There doesn't seem to be a reliable method to find out the active dark theme on NC in all cases.
			// Both accessing OCA.Accessibility.theme and looking at the <body> class 'dark' will often give
			// wrong (cached?) results after changing the theme, until a forced page reload is made.

			// Workaround: Try to figure out on our own if the background color is indeed dark. This should work
			// also with any dark third party themes (e.g. Breeze Dark).
			const rgb = getComputedStyle(document.getElementById('app-content')).backgroundColor;
			const m = rgb.match(/^rgb\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)$/i);
			if (m) {
				const [r, g, b] = [m[1], m[2], m[3]];
				// Analyze perceived brightness, based on https://stackoverflow.com/a/12043228
				const luma = 0.2126 * r + 0.7152 * g + 0.0722 * b; // per ITU-R BT.709

				if (luma < 128) { //dark
					element.style.setProperty('--background-invert-if-dark', 'invert(100%)');
				} else { // light
					element.style.setProperty('--background-invert-if-dark', 'no');
				}
			}
		}
	}
};