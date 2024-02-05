/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2018 - 2024
 */

OCA.Music = OCA.Music || {};

OCA.Music.Playlist = class {

	#files = null;
	#currentIndex = null;

	#jumpToOffset(offset) {
		if (!this.#files || this.#files.length <= 1) {
			return null;
		} else {
			this.#currentIndex = (this.#currentIndex + this.#files.length + offset) % this.#files.length;
			return this.#files[this.#currentIndex];
		}
	}

	constructor(folderFiles, supportedMimes, firstFileId) {
		// Filter out unsupported files.
		// External URLs do not have a valid MIME type set, attempt to play them regardless.
		this.#files = _.filter(folderFiles, (f) => (f.mimetype === null || supportedMimes.includes(f.mimetype)));

		// Find the initial index corresponding the given file ID.
		// ID type int/string depend on the cloud version, don't use '==='.
		this.#currentIndex = _.findIndex(this.#files, (file) => file.id == firstFileId);

		// Default the start index to 0 if the desired ID is not found (after the filtering).
		if (this.#files.length && this.#currentIndex == -1) {
			this.#currentIndex = 0;
		}
	}

	next() {
		return this.#jumpToOffset(+1);
	}

	prev() {
		return this.#jumpToOffset(-1);
	}

	jumpToIndex(index) {
		if (index < this.#files.length) {
			this.#currentIndex = index;
		}
		return this.currentFile();
	}

	reset() {
		this.#files = null;
		this.#currentIndex = null;
	}

	length() {
		return this.#files ? this.#files.length : 0;
	}

	currentFile() {
		return this.#files ? this.#files[this.#currentIndex] : null;
	}

	currentIndex() {
		return this.#currentIndex;
	}

	files() {
		return this.#files;
	}
};