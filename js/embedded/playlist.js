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

	var mFolderUrl = null;
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

	function stripExtension(filename) {
		return filename.substr(0, filename.lastIndexOf('.')) || filename;
	}

	function initDone(firstFileId, callback) {
		if (mFiles) {
			mCurrentIndex = _.findIndex(mFiles, {fileid: firstFileId});
		}
		if (callback) {
			callback();
		}
	}

	this.init = function(folderUrl, supportedMimes, firstFileId, shareToken, onDone) {
		if (mFolderUrl != folderUrl || !mFiles) {
			mFolderUrl = folderUrl;
			mFiles = null;

			var propFindParams =
				'<?xml version="1.0"?>' +
				'<d:propfind  xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns">' +
				'	<d:prop>' +
				'		<d:getcontenttype/>' +
				'		<oc:fileid/>' +
				'	</d:prop>' +
				'</d:propfind>';

			var headers = !shareToken ? {} : {
				Authorization: 'Basic ' + btoa(shareToken + ':'),
				Range: 'bytes=0-1000'
			};

			$.ajax({
				url: folderUrl,
				method: "PROPFIND",
				data: propFindParams,
				contentType: "application/xml; charset=utf-8",
				dataType: "xml",
				headers: headers,
				success: function(response) {
					mFiles = [];

					$(response).find("d\\:response").each(function() {
						var mime = $(this).find("d\\:getcontenttype").html();
						if (_.contains(supportedMimes, mime)) {
							var url = $(this).find("d\\:href").html();
							var name = decodeURIComponent(OC.basename(url));
							mFiles.push({
								fileid: $(this).find("oc\\:fileid").html(),
								mime: mime,
								name: name,
								basename: stripExtension(name)
							});
						}
					});

					mFiles = _.sortBy(mFiles, function(f) { return f.basename.toLowerCase(); });
					initDone(firstFileId, onDone);
				},
				fail: function() {
					console.warn('PROPFIND failed for folrder URL ' + folderUrl);
					initDone(firstFileId, onDone);
				}
			});
		}
		else {
			initDone(firstFileId, onDone);
		}
	};

	this.next = function() {
		return jumpToOffset(+1);
	};

	this.prev = function() {
		return jumpToOffset(-1);
	};

	this.reset = function() {
		mFolderUrl = null;
		mFiles = null;
		mCurrentIndex = null;
	};

	this.length = function() {
		return mFiles ? mFiles.length : 0;
	};

	// Expose the utility function. This module is not really a logical
	// place for it but creating another module just for one shared function
	// would be cumbersome.
	this.stripExtension = stripExtension;
}