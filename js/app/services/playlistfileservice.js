/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2021
 */

angular.module('Music').service('playlistFileService', [
'$rootScope', '$q', 'libraryService', 'gettextCatalog', 'Restangular',
function($rootScope, $q, libraryService, gettextCatalog, Restangular) {

	function onExportConflict(path, name, retryFunc) {
		OC.dialogs.confirm(
			gettextCatalog.getString('The folder already has a file named "{{ filename }}". Select "Yes" to overwrite it.'+
									' Select "No" to export the list with another name. Close the dialog to cancel.',
									{ filename: name + '.m3u8' }),
			gettextCatalog.getString('Overwrite existing file'),
			function (overwrite) {
				if (overwrite) {
					retryFunc(path, 'overwrite');
				} else {
					retryFunc(path, 'keepboth');
				}
			},
			true // modal
		);
	}

	/** return true if a retry attempt was fired and false if the operation was aborted */
	function handleExportError(httpError, path, playlistName, retryFunc) {
		switch (httpError) {
		case 409: // conflict
			onExportConflict(path, playlistName, retryFunc);
			return true;
		case 404: // not found
			OC.Notification.showTemporary(
				gettextCatalog.getString('Playlist or folder not found'));
			return false;
		case 403: // forbidden
			OC.Notification.showTemporary(
				gettextCatalog.getString('Writing to the file is not allowed'));
			return false;
		default: // unexpected
			OC.Notification.showTemporary(
				gettextCatalog.getString('Unexpected error'));
			return false;
		}
	}

	function showFolderPicker(caption, onSelectedCallback) {
		OC.dialogs.filepicker(
				caption,
				(datapath, _returnType) => onSelectedCallback(datapath), // arg _returnType is passed by NC but not by OC
				false, // <! multiselect
				'httpd/unix-directory',
				true // <! modal
		);
	}

	function showPlaylistFilePicker(caption, onSelectedCallback) {
		OC.dialogs.filepicker(
				caption,
				(datapath, _returnType) => onSelectedCallback(datapath), // arg _returnType is passed by NC but not by OC
				false, // <! multiselect
				['audio/mpegurl', 'audio/x-scpls'],
				true // <! modal
		);
	}

	return {

		// Export playlist to file
		exportPlaylist: function(playlist) {

			function onFolderSelected(path, onCollision /*optional*/) {
				playlist.busy = true;
				let args = { path: path, oncollision: onCollision || 'abort' };
				Restangular.one('playlists', playlist.id).all('export').post(args).then(
					function (result) {
						OC.Notification.showTemporary(
							gettextCatalog.getString('Playlist exported to file {{ path }}', { path: result.wrote_to_file }));
						playlist.busy = false;
					},
					function (error) {
						handleExportError(error.status, path, playlist.name, onFolderSelected);
						playlist.busy = false;
					}
				);
			}

			showFolderPicker(
				gettextCatalog.getString('Export playlist to a file in the selected folder'),
				onFolderSelected
			);
		},


		// Export radio stations to file
		exportRadio: function() {
			let deferred = $q.defer();
			let name = gettextCatalog.getString('Internet radio');

			function onFolderSelected(path, onCollision /*optional*/) {
				deferred.notify('started');
				let args = { path: path, name: name, oncollision: onCollision || 'abort' };
				Restangular.all('radio/export').post(args).then(
					function (result) {
						OC.Notification.showTemporary(
							gettextCatalog.getString('Radio stations exported to file {{ path }}', { path: result.wrote_to_file }));
						deferred.resolve();
					},
					function (error) {
						deferred.notify('stopped');
						let retry = handleExportError(error.status, path, name, onFolderSelected);
						if (!retry) {
							deferred.reject();
						}
					}
				);
			}

			showFolderPicker(
				gettextCatalog.getString('Export radio stations to a file in the selected folder'),
				onFolderSelected
			);

			return deferred.promise;
		},

		// Import playlist contents from a file
		importPlaylist: function(playlist) {
			let onFileSelected = function(file) {
				playlist.busy = true;
				Restangular.one('playlists', playlist.id).all('import').post({filePath: file}).then(
					function(result) {
						libraryService.replacePlaylist(result.playlist);
						let message = gettextCatalog.getString('Imported {{ count }} tracks from the file {{ file }}.',
																{ count: result.imported_count, file: file });
						if (result.failed_count > 0) {
							message += ' ' + gettextCatalog.getString('{{ count }} files were skipped.',
																		{ count: result.failed_count });
						}
						OC.Notification.showTemporary(message);
						$rootScope.$emit('playlistUpdated', playlist.id);
						playlist.busy = false;
					},
					function(_error) {
						OC.Notification.showTemporary(
								gettextCatalog.getString('Failed to import playlist from the file {{ file }}',
														{ file: file }));
						playlist.busy = false;
					}
				);
			};

			let selectFile = function() {
				showPlaylistFilePicker(
						gettextCatalog.getString('Import playlist contents from the selected file'),
						onFileSelected
				);
			};

			if (playlist.tracks.length > 0) {
				OC.dialogs.confirm(
						gettextCatalog.getString('The playlist already contains some tracks. Imported tracks' +
												' will be appended after the existing contents. Proceed?'),
						gettextCatalog.getString('Append to an existing playlist?'),
						function (overwrite) {
							if (overwrite) {
								selectFile();
							}
						},
						true // modal
				);
			}
			else {
				selectFile();
			}
		},

		// Import radio stations from a playlist file
		importRadio: function() {
			let deferred = $q.defer();

			let onFileSelected = function(file) {
				deferred.notify('started');

				return Restangular.all('radio/import').post({filePath: file}).then(
					function(result) {
						libraryService.addRadioStations(result.stations);
						let message = gettextCatalog.getString('Imported {{ count }} radio stations from the file {{ file }}.',
																{ count: result.stations.length, file: file });
						if (result.failed_count > 0) {
							message += ' ' + gettextCatalog.getString('{{ count }} entries were skipped.',
																		{ count: result.failed_count });
						}
						OC.Notification.showTemporary(message);
						$rootScope.$emit('playlistUpdated', 'radio');
						deferred.resolve();
					},
					function(_error) {
						OC.Notification.showTemporary(
								gettextCatalog.getString('Failed to import radio stations from the file {{ file }}',
														{ file: file }));
						deferred.reject();
					}
				);
			};

			showPlaylistFilePicker(
					gettextCatalog.getString('Import radio stations from the selected file'),
					onFileSelected
			);

			return deferred.promise;
		}
	};
}]);
