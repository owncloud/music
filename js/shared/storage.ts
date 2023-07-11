/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2023
 */

OCA.Music = OCA.Music || {};

/** @namespace
 * 
 * Implement per-page persistent storage. The native HTML5 localStorage has an instance per
 * origin (protocol + host + port) but the same instance is shared by all pages under the same
 * origin. This would be an issue in case the user runs multiple instances of ownCloud/Nextcloud
 * on the same server.
 */
OCA.Music.Storage = class {

	static get(key : string) : string | null {
		return localStorage.getItem(`${this.#prefix()}::${key}`);
	}

	static set(key : string, value : string) : void {
		localStorage.setItem(`${this.#prefix()}::${key}`, value);
	}

	static #prefix() {
		let path = location.pathname;
		// The path may or may not contain the trailing slash '/'. Techically, it's even possible 
		// to have multiple trailing slashes, in case the user writes the URL like that.
		// Normalize the prefix to never have any.
		while (path.slice(-1) === '/') {
			path = path.slice(0, -1);
		}
		return path;
	}
}