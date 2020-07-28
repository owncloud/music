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

	this.importFile = function(file, onDone) {
		var name = OCA.Music.Utils.dropFileExtension(file.name);
		var path = OCA.Music.Utils.joinPath(file.path, file.name);

		// first, create a new playlist
		var url = OC.generateUrl('apps/music/api/playlists');
		$.post(url, {name: name}, function(newList) {
			// then, import the playlist file contents to the newly created list
			url = OC.generateUrl('apps/music/api/playlists/{listId}/import', {listId: newList.id});

			$.post(url, {filePath: path}, function(result) {
				var message = t('music', "Imported {count} tracks to a new playlist '{name}'.",
								{ count: result.imported_count, name: name });
				if (result.failed_count > 0) {
					message += ' ' + t('music', '{count} files were skipped.', { count: result.failed_count });
				}
				OC.Notification.showTemporary(message);
				onDone(true);
			}).fail(function() {
				OC.Notification.showTemporary(
						t('music', 'Failed to import the playlist file {file}', { file: file.name }));
				onDone(false);
			});

		}).fail(function() {
			OC.Notification.showTemporary(t('music', 'Failed to create a new playlist'));
			onDone(false);
		});
	};

};

OCA.Music.playlistFileService = new OCA.Music.PlaylistFileService();
