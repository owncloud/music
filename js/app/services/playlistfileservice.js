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

	return {
		// Export playlist to file
		exportPlaylist: function(playlist) {

			var onFolderSelected = null; // defined later below

			var onConflict = function(path) {
				OC.dialogs.confirm(
					gettextCatalog.getString('The folder already has a file named "{{ filename }}". Select "Yes" to overwrite it.'+
											' Select "No" to export the list with another name. Close the dialog to cancel.',
											{ filename: playlist.name + '.m3u8' }),
					gettextCatalog.getString('Overwrite existing file'),
					function (overwrite) {
						if (overwrite) {
							onFolderSelected(path, 'overwrite');
						} else {
							onFolderSelected(path, 'keepboth');
						}
					},
					true // modal
				);
			};

			onFolderSelected = function(path, onCollision /*optional*/) {
				playlist.busy = true;
				var args = { path: path, oncollision: onCollision || 'abort' };
				Restangular.one('playlists', playlist.id).all('export').post(args).then(
					function (result) {
						OC.Notification.showTemporary(
							gettextCatalog.getString('Playlist exported to file {{ path }}', { path: result.wrote_to_file }));
						playlist.busy = false;
					},
					function (error) {
						switch (error.status) {
						case 409: // conflict
							onConflict(path);
							break;
						case 404: // not found
							OC.Notification.showTemporary(
								gettextCatalog.getString('Playlist or folder not found'));
							break;
						case 403: // forbidden
							OC.Notification.showTemporary(
								gettextCatalog.getString('Writing to the file is not allowed'));
							break;
						default: // unexpected
							OC.Notification.showTemporary(
								gettextCatalog.getString('Unexpected error'));
							break;
						}
						playlist.busy = false;
					}
				);
			};

			OC.dialogs.filepicker(
					gettextCatalog.getString('Export playlist to a file in the selected folder'),
					onFolderSelected,
					false,
					'httpd/unix-directory',
					true
			);
		},

		// Import playlist contents from a file
		importPlaylist: function(playlist) {
			var onFileSelected = function(file) {
				playlist.busy = true;
				Restangular.one('playlists', playlist.id).all('import').post({filePath: file}).then(
					function(result) {
						libraryService.replacePlaylist(result.playlist);
						var message = gettextCatalog.getString('Imported {{ count }} tracks from the file {{ file }}.',
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

			var selectFile = function() {
				OC.dialogs.filepicker(
						gettextCatalog.getString('Import playlist contents from the selected file'),
						onFileSelected,
						false,
						['audio/mpegurl', 'audio/x-scpls'],
						true
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
			var deferred = $q.defer();

			var onFileSelected = function(file) {
				deferred.notify('import started');

				return Restangular.all('radio/import').post({filePath: file}).then(
					function(result) {
						libraryService.addRadioStations(result.stations);
						var message = gettextCatalog.getString('Imported {{ count }} radio stations from the file {{ file }}.',
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

			OC.dialogs.filepicker(
					gettextCatalog.getString('Import radio stations from the selected file'),
					onFileSelected,
					false,
					['audio/mpegurl', 'audio/x-scpls'],
					true
			);

			return deferred.promise;
		}
	};
}]);
