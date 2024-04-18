/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2018 - 2024
 */

declare var OCA : any;
declare const OC : any;

OCA.Music = OCA.Music || {};

/** @namespace */
OCA.Music.Utils = class {

	/**
	 * Originally in ownCloud and in Nextcloud up to version 13, the #app-content element acted as the main scroll container.
	 * Nextcloud 14 changed this so that the document became the main scrollable container, and this needed some adjustments
	 * to the Music app. Then, Nextcloud 25 changed this back to the original system. In Nextcloud 28, this changed once again
	 * within the Files app but not globally. 
	 */
	static getScrollContainer() : JQuery<any> {
		const appContent = $('#app-content');
		const filesList = $('#app-content-vue .files-list');

		if (appContent.css('overflow-y') === 'auto') {
			return appContent;
		} else if (filesList.css('overflow-y') === 'auto') {
			return filesList;
		} else {
			return $(window.document);
		}
	}

	/**
	 * Nextcloud versions up to 13 and all ownCloud versions use the "legacy layout structure" which requires some
	 * adjustments on our side, too.
	 */
	static isLegacyLayout() : boolean {
		return $('#content-wrapper').length > 0;
	}

	/**
	 * Check if the given HTML element is any kind of text input element.
	 * Refactored version of the original source at https://stackoverflow.com/a/38795917
	 */
	static isTextEntryElement(element : HTMLElement) : boolean {
		let tagName = element.tagName.toLowerCase();
		if (tagName === 'textarea') {
			return true;
		} else if (tagName === 'input') {
			let type = element.getAttribute('type').toLowerCase();
			// if any of these input types is not supported by a browser, it will behave as input type text.
			let inputTypes = ['text', 'password', 'number', 'email', 'tel', 'url', 'search',
								'date', 'datetime', 'datetime-local', 'time', 'month', 'week'];
			return inputTypes.indexOf(type) >= 0;
		} else {
			return false;
		}
	}

	/**
	 * Get the selected locale of the user from Nextcloud/ownCloud personal settings
	 */
	static getLocale() : string {
		return OC.getLocale().replaceAll('_', '-'); // OC returns e.g. 'en_US' but the javascript APIs expect 'en-US'
	}

	/**
	 * Attempt to get a reference with the first supplied function. If the reference is available, then call the second
	 * function giving the reference as an argument. Otherwise retry in a while. Keep trying until the reference is available.
	 */
	static executeOnceRefAvailable<T>(getRef : () => T|null|undefined, callback : (arg : T) => any, attemptInterval_ms = 500) : void {
		const attempt = () => {
			let ref = getRef();
			if (ref) {
				callback(ref);
			} else {
				setTimeout(attempt, attemptInterval_ms);
			}
		};
		attempt();
	}

	/**
	 * Capitalizes the first character of the given string
	 */
	static capitalize(str : string) : string {
		return str && str[0].toUpperCase() + str.slice(1); 
	}

	/**
	 * Convert string to boolean
	 */
	static parseBoolean(value : any) : boolean {
		value = String(value).toLowerCase();
		const falsyValues = ['false', 'no', 'off', '0', 'undefined', 'null'];
		return falsyValues.indexOf(value) < 0;
	}

	/**
	 * Creates a track title from the file name, dropping the file extension and any
	 * track number possibly found from the beginning of the file name.
	 */
	static titleFromFilename(filename : string) : string {
		// parsing logic is ported form parseFileName in utility/scanner.php
		let match = filename.match(/^((\d+)\s*[.-]\s+)?(.+)\.(\w{1,4})$/);
		return match ? match[3] : filename;
	}

	/**
	 * Given a file base name, returns the "stem" i.e. the name without extension
	 */
	static dropFileExtension(filename : string) : string {
		return filename.replace(/\.[^/.]+$/, '');
	}

	/**
	 * Join two path fragments together, ensuring there is a single '/' between them.
	 * The first fragment may or may not end with '/' and the second fragment may or may
	 * not start with '/'.
	 */
	static joinPath(first : string, second : string) : string {
		if (first.endsWith('/')) {
			first = first.slice(0, -1);
		}
		if (second.startsWith('/')) {
			second = second.slice(1);
		}
		return first + '/' + second;
	}

	/**
	 * Format the given seconds value as play time. The format includes hours if and only if the
	 * given input time is more than one hour. The output format looks like 1:01 or 1:01:01.
	 * That is, unlike the rest of the parts, the part before the first ':' does not have a leading zero.
	 */
	static formatPlayTime(input_s : number) : string {
		// Format the given integer with two digits, prepending with a leading zero if necessary
		let fmtTwoDigits = function(integer : number) {
			return (integer < 10 ? '0' : '') + integer;
		};

		let hours = Math.floor(input_s / 3600);
		let minutes = Math.floor((input_s - hours*3600) / 60);
		let seconds = Math.floor(input_s % 60);

		if (hours > 0) {
			return hours + ':' + fmtTwoDigits(minutes) + ':' + fmtTwoDigits(seconds);
		} else {
			return minutes + ':' + fmtTwoDigits(seconds);
		}
	}

	/**
	 * Given a date-and-time string in UTC, return it in local timezone following the locale settings
	 * of the user (from Nextcloud/ownCloud personal settings).
	 */
	static formatDateTime(timestamp : string) : string {
		if (!timestamp) {
			return null;
		} else {
			let date = new Date(timestamp + 'Z');
			return date.toLocaleString(OCA.Music.Utils.getLocale());
		}
	}

	/**
	 * Format a file size given as bytes in human-readable format.
	 * Source: https://stackoverflow.com/a/20732091/4348850
	 */
	static formatFileSize(size : number) : string {
		let i = (size == 0) ? 0 : Math.floor( Math.log(size) / Math.log(1024) );
		return ( size / Math.pow(1024, i) ).toFixed(2) + ' ' + ['B', 'KiB', 'MiB', 'GiB', 'TiB'][i];
	}

	/**
	 * Format baud rate given as bit per second to kilobits per second with integer precission
	 */
	static formatBitrate(bitsPerSecond : number) : string {
		return (bitsPerSecond / 1000).toFixed() + ' kbps';
	}
};