/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2018
 */

function Playlist() {

	var mFiles = null;
	var mCurrentIndex = null;

	function jumpToOffset(offset) {
		if (!mFiles || mFiles.length <= 1) {
			return null;
		} else {
			mCurrentIndex = (mCurrentIndex + mFiles.length + offset) % mFiles.length;
			return mFiles[mCurrentIndex];
		}
	}

	this.init = function(folderFiles, supportedMimes, firstFileId) {
		mFiles = _.filter(folderFiles, function(file) {
			return _.contains(supportedMimes, file.mimetype);
		});
		mCurrentIndex = _.findIndex(mFiles, function(file) {
			// types int/string depend on the cloud version, don't use ===
			return file.id == firstFileId; 
		});
	};

	this.next = function() {
		return jumpToOffset(+1);
	};

	this.prev = function() {
		return jumpToOffset(-1);
	};

	this.reset = function() {
		mFiles = null;
		mCurrentIndex = null;
	};

	this.length = function() {
		return mFiles ? mFiles.length : 0;
	};

}