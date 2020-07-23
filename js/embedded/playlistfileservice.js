/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2020
 */

OCA.Music = OCA.Music || {};

OCA.Music.PlaylistFileService = function() {

	var mFileId = null;
	var mData = null;

	this.readFile = function(fileId, onSuccess, onFail) {

		if (fileId == mFileId && mData !== null) {
			onSuccess(mData);
		}
		else {
			var url = OC.generateUrl('apps/music/api/playlists/file/{fileId}', {'fileId': fileId});
			$.get(url, function(data) {
				mFileId = fileId;
				mData = data;
				onSuccess(data);
			}).fail(onFail);
		}
	};

};

OCA.Music.playlistFileService = new OCA.Music.PlaylistFileService();
