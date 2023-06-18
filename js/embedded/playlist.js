/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2018 - 2023
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
		this.#files = _.filter(folderFiles, function(file) {
			// external URLs do not have a valid MIME type set, attempt to play them regardless
			return file.mimetype === null || _.includes(supportedMimes, file.mimetype);
		});
		this.#currentIndex = _.findIndex(this.#files, function(file) {
			// types int/string depend on the cloud version, don't use ===
			return file.id == firstFileId; 
		});
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