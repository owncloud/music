/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2021 - 2025
 */

import * as ng from 'angular';
import { gettextCatalog } from 'angular-gettext';
import { MusicRootScope } from 'app/config/musicrootscope';
import { IService } from 'restangular';
import { LibraryService } from './libraryservice';

interface Playlist {
	id : number;
	name : string;
	tracks : any[];
	busy : boolean;
}

ng.module('Music').service('playlistFileService', [
'$rootScope', '$q', 'libraryService', 'gettextCatalog', 'Restangular',
function($rootScope : MusicRootScope, $q : ng.IQService, libraryService : LibraryService, gettextCatalog : gettextCatalog, Restangular : IService) {

	function queryOverwrite(path : string, onSelection : CallableFunction) {
		const fileName = path.split('/').pop();

		OC.dialogs.confirm(
			gettextCatalog.getString('The folder already has a file named "{{ filename }}". Select "Yes" to overwrite it.'+
									' Select "No" to save with another name.',
									{ filename: fileName }),
			gettextCatalog.getString('Overwrite existing file'),
			onSelection,
			true // modal
		);
	}

	/** return true if the operation can be retried */
	function handleExportError(httpError : number) : boolean {
		switch (httpError) {
		case 409: // conflict
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

	function showFolderPicker(caption : string, onSelectedCallback : CallableFunction) : void {
		OCA.Music.Dialogs.folderPicker(caption, onSelectedCallback);
	}

	function queryFileName(defaultName : string, onNameGiven : CallableFunction) : void {
		const title = gettextCatalog.getString('File name');
		const promptText = gettextCatalog.getString('Save with given file name');
		OCA.Music.Dialogs.prompt(
			title,
			promptText,
			(accept : boolean, name : string) => {
				if (accept) {
					onNameGiven(name);
				}
			},
			defaultName
		);
	}

	function showPlaylistFilePicker(caption : string, onSelectedCallback : CallableFunction) : void {
		OCA.Music.Dialogs.filePicker(
				caption,
				onSelectedCallback,
				['audio/mpegurl', 'audio/x-scpls', 'application/vnd.ms-wpl']
		);
	}

	return {

		// Export playlist to file
		exportPlaylist(playlist : Playlist) : void {

			let selPath : string = null;

			showFolderPicker(
				gettextCatalog.getString('Export playlist to a file in the selected folder'),
				(path : string) => {
					selPath = path;
					queryFileName(playlist.name + '.m3u8', onFileNameGiven);
				}
			);

			function onFileNameGiven(name : string, onCollision = 'abort') {
				playlist.busy = true;
				let args = { path: selPath, oncollision: onCollision, filename: name };
				Restangular.one('playlists', playlist.id).all('export').post(args).then(
					(result) => {
						OC.Notification.showTemporary(
							gettextCatalog.getString('Playlist exported to file {{ path }}', { path: result.wrote_to_file }));
						playlist.busy = false;
					},
					(error) => {
						playlist.busy = false;
						let retry = handleExportError(error.status);
						if (retry) {
							queryOverwrite(error.data.path, (overwrite : boolean) => {
								if (overwrite) {
									onFileNameGiven(name, 'overwrite');
								} else {
									queryFileName(error.data.suggested_name, onFileNameGiven);
								}
							});
						}
					}
				);
			}
		},

		// Export radio stations to file
		exportRadio() : ng.IPromise<any> {
			let deferred = $q.defer();

			let selPath : string = null;

			showFolderPicker(
				gettextCatalog.getString('Export radio stations to a file in the selected folder'),
				(path : string) => {
					selPath = path;
					queryFileName(gettextCatalog.getString('Internet radio') + '.m3u8', onFileNameGiven);
				}
			);

			function onFileNameGiven(name : string, onCollision = 'abort') {
				deferred.notify('started');
				let args = { path: selPath, name: name, oncollision: onCollision };
				Restangular.all('radio/export').post(args).then(
					(result) => {
						OC.Notification.showTemporary(
							gettextCatalog.getString('Radio stations exported to file {{ path }}', { path: result.wrote_to_file }));
						deferred.resolve();
					},
					(error) => {
						deferred.notify('stopped');
						let retry = handleExportError(error.status);
						if (retry) {
							queryOverwrite(error.data.path, (overwrite : boolean) => {
								if (overwrite) {
									onFileNameGiven(name, 'overwrite');
								} else {
									queryFileName(error.data.suggested_name, onFileNameGiven);
								}
							});
						} else {
							deferred.reject();
						}
					}
				);
			}

			return deferred.promise;
		},

		// Import playlist contents from a file
		importPlaylist: function(playlist : Playlist) : void {
			function onFileSelected(file : string) {
				playlist.busy = true;
				Restangular.one('playlists', playlist.id).all('import').post({filePath: file}).then(
					(result) => {
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
					(_error) => {
						OC.Notification.showTemporary(
								gettextCatalog.getString('Failed to import playlist from the file {{ file }}',
														{ file: file }));
						playlist.busy = false;
					}
				);
			}

			function selectFile() {
				showPlaylistFilePicker(
						gettextCatalog.getString('Import playlist contents from the selected file'),
						onFileSelected
				);
			}

			if (playlist.tracks.length > 0) {
				OC.dialogs.confirm(
						gettextCatalog.getString('The playlist already contains some tracks. Imported tracks' +
												' will be appended after the existing contents. Proceed?'),
						gettextCatalog.getString('Append to an existing playlist?'),
						(overwrite : boolean) => {
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
		importRadio: function() : ng.IPromise<any> {
			let deferred = $q.defer();

			function onFileSelected(file : string) : ng.IPromise<any> {
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
