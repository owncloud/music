/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2020 - 2023
 */

OCA.Music = OCA.Music || {};

/** @namespace */
OCA.Music.PlaylistFileService = class {

	static #fileId = null;
	static #data = null;

	static readFile(fileId, onSuccess, onFail, shareToken = null) {

		if (fileId == this.#fileId && this.#data !== null) {
			onSuccess(this.#data);
		}
		else {
			let url = null;
			// valid shareToken means that we are operating on a public share page, and a different URL is needed
			if (shareToken) {
				url = OC.generateUrl('apps/music/api/share/{token}/{fileId}/parse',
									{'token':shareToken, 'fileId':fileId});
			} else {
				url = OC.generateUrl('apps/music/api/playlists/file/{fileId}', {'fileId': fileId});
			}

			$.get(url, (data) => {
				this.#fileId = fileId;
				this.#data = data;
				onSuccess(data);
			}).fail(onFail);
		}
	}

	static importPlaylist(file, onDone) {
		let name = OCA.Music.Utils.dropFileExtension(file.name);
		let path = OCA.Music.Utils.joinPath(file.path, file.name);

		// first, create a new playlist
		let url = OC.generateUrl('apps/music/api/playlists');
		$.post(url, {name: name}, function(newList) {
			// then, import the playlist file contents to the newly created list
			url = OC.generateUrl('apps/music/api/playlists/{listId}/import', {listId: newList.id});

			$.post(url, {filePath: path}, function(result) {
				let message = t('music', 'Imported {count} tracks to a new playlist \'{name}\'.',
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
	}

	static importRadio(file, onDone) {
		let path = OCA.Music.Utils.joinPath(file.path, file.name);

		let url = OC.generateUrl('apps/music/api/radio/import');

		$.post(url, {filePath: path}, function(result) {
			let message = t('music', 'Imported {count} radio stations.', { count: result.stations.length });
			if (result.failed_count > 0) {
				message += ' ' + t('music', '{count} entries were skipped.', { count: result.failed_count });
			}
			OC.Notification.showTemporary(message);
			onDone(true);
		}).fail(function() {
			OC.Notification.showTemporary(
					t('music', 'Failed to import the playlist file {file}', { file: file.name }));
			onDone(false);
		});
	}

};
