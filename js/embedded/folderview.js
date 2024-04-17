/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2017 - 2024
 */

import playIconPath from '../../img/play-big.svg';
import playIconSvgData from '../../img/play-big.svg?raw';

OCA.Music = OCA.Music || {};

/**
 * "Folder player" is used in the Files app and in the link-shared folders
 */
OCA.Music.FolderView = class {

	#currentFile = null; // may be an audio file or a playlist file
	#playingListFile = false;
	#fileList = null; // FileList from Files (prior to NC28) or Sharing app
	#shareToken = $('#sharingToken').val(); // undefined when not on share page
	#audioMimes = null;
	#playlistMimes = null;

	#player = null;
	#playlist = null;

	constructor(embeddedPlayer, audioMimes, playlistMimes) {
		this.#audioMimes = audioMimes;
		this.#playlistMimes = playlistMimes;
		this.#player = embeddedPlayer;
		this.#player.setCallbacks(
			this.#onClose.bind(this),
			this.#onNext.bind(this),
			this.#onPrev.bind(this),
			this.#onMenuOpen.bind(this),
			this.#onShowList.bind(this),
			this.#onImportList.bind(this),
			this.#onImportRadio.bind(this)
		);
	}

	registerToNcFiles(ncFiles) {
		const audioFileCb = this.#createFileClickCallback(() => this.#openAudioFile());
		const plFileCb = this.#createFileClickCallback(() => this.#openPlaylistFile());

		this.#registerToNcFiles(ncFiles, this.#audioMimes, audioFileCb, 'music_play_audio_file');
		this.#registerToNcFiles(ncFiles, this.#playlistMimes, plFileCb, 'music_play_playlist_file');
	}

	registerToFileActions(fileActions) {
		const audioFileCb = this.#createFileClickCallback(() => this.#openAudioFile());
		const plFileCb = this.#createFileClickCallback(() => this.#openPlaylistFile());

		this.#registerToFileActions(fileActions, this.#audioMimes, audioFileCb, 'music_play_audio_file');
		this.#registerToFileActions(fileActions, this.#playlistMimes, plFileCb, 'music_play_playlist_file');
	}

	onPlaylistItemClick(playlistFile, itemIdx) {
		if (this.#currentFile !== null && playlistFile.id == this.#currentFile.id) {
			if (itemIdx == this.#playlist.currentIndex()) {
				this.#player.togglePlayback();
			} else {
				this.#jumpToPlaylistFile(this.#playlist.jumpToIndex(itemIdx));
			}
		}
		else {
			if (OCA.Files.App) {
				// Before NC28
				this.#fileList = OCA.Files.App.fileList;
				this.#currentFile = this.#fileList.findFile(playlistFile.name);
			} else {
				// NC28 or later
				this.#currentFile = playlistFile;
			}
			this.#openPlaylistFile(() => this.#jumpToPlaylistFile(this.#playlist.jumpToIndex(itemIdx)));
		}
	}

	playlistFileState() {
		if (this.#playingListFile) {
			return {
				fileId: this.#currentFile.id,
				index: this.#playlist.currentIndex()
			};
		} else {
			return null;
		}
	}

	#urlForFile(file) {
		let url = this.#fileList
			? this.#fileList.getDownloadUrl(file.name, file.path)
			: OC.filePath('music', '', 'index.php') + '/api/file/' + file.id + '/download';

		// Append request token unless this is a public share. This is actually unnecessary for most files
		// but needed when the file in question is played using our Aurora.js fallback player.
		if (!this.#shareToken) {
			let delimiter = _.includes(url, '?') ? '&' : '?';
			url += delimiter + 'requesttoken=' + encodeURIComponent(OC.requestToken);
		}
		return url;
	}

	#onClose() {
		this.#currentFile = null;
		this.#playingListFile = false;
		this.#playlist?.reset();
		OCA.Music.playlistTabView?.setCurrentTrack(null, null);
	}

	#onNext() {
		if (this.#playlist) {
			this.#jumpToPlaylistFile(this.#playlist.next());
		}
	}

	#onPrev() {
		if (this.#playlist) {
			this.#jumpToPlaylistFile(this.#playlist.prev());
		}
	}

	#viewingCurrentFileFolder() {
		// Note: this is always false on NC28+
		return this.#currentFile && this.#fileList && this.#currentFile.path == this.#fileList.breadcrumb.dir;
	}

	#onMenuOpen($menu) {
		// disable/enable the "Show list" item
		let $showItem = $menu.find('#playlist-menu-show');
		// the new sidebar API introduced in NC18 enabled viewing details also for files outside the current folder
		if (OCA.Files.Sidebar || this.#viewingCurrentFileFolder()) {
			$showItem.removeClass('disabled');
			$showItem.removeAttr('title');
		} else {
			$showItem.addClass('disabled');
			$showItem.attr('title', t('music', 'The option is available only while the parent folder of the playlist file is shown'));
		}

		// disable/enable the "Import list to Music" item
		let inLibraryFilesCount = _(this.#playlist.files()).filter('in_library').size();
		let extStreamsCount = _(this.#playlist.files()).filter('external').size();
		let outLibraryFilesCount = this.#playlist.length() - inLibraryFilesCount;

		let $importListItem = $menu.find('#playlist-menu-import');
		let $importRadioItem = $menu.find('#playlist-menu-import-radio');

		if (inLibraryFilesCount === 0) {
			$importListItem.addClass('disabled');
			$importListItem.attr('title', t('music', 'None of the playlist files are within your music library'));
		} else {
			$importListItem.removeClass('disabled');
			if (outLibraryFilesCount > 0) {
				$importListItem.attr('title',
						t('music', '{inCount} out of {totalCount} files are within your music library and can be imported',
						{inCount: inLibraryFilesCount, totalCount: this.#playlist.length()}));
			} else {
				$importListItem.removeAttr('title');
			}
		}

		// hide the "Import radio to Music" if there are no external streams on the list
		if (extStreamsCount === 0) {
			$importRadioItem.addClass('hidden');
		} else {
			$importRadioItem.removeClass('hidden');
		}

		// hide the "Import list to Music" if there are only external streams on the list
		if (extStreamsCount === this.#playlist.length()) {
			$importListItem.addClass('hidden');
		} else {
			$importListItem.removeClass('hidden');
		}
	}

	#onShowList() {
		if (OCA.Files.Sidebar) {
			// This API is available starting from NC18 and after NC28, it's the only one available.
			// This is better than the older API because this can be used also for files which are not
			// present in the currently viewed folder.
			OCA.Files.Sidebar.open(this.#currentFile.path + '/' + this.#currentFile.name);
			OCA.Files.Sidebar.setActiveTab(OCA.Music.playlistTabView.id);
		} else {
			this.#fileList.scrollTo(this.#currentFile.name);
			this.#fileList.showDetailsView(this.#currentFile.name, OCA.Music.playlistTabView.id);
		}
	}

	#onImportList() {
		this.#doImportFromFile(OCA.Music.PlaylistFileService.importPlaylist);
	}

	#onImportRadio() {
		this.#doImportFromFile(OCA.Music.PlaylistFileService.importRadio);
	}

	#doImportFromFile(serviceImportFunc) {
		this.#player.showBusy(true);

		serviceImportFunc(this.#currentFile, (_result) => {
			this.#player.showBusy(false);
		});
	}

	#jumpToPlaylistFile(file) {
		if (!file) {
			this.#player.close();
		} else {
			if (!this.#playingListFile) {
				this.#currentFile = file;
			}
			if (file.external) {
				this.#player.playExtUrl(file.url, file.caption, this.#shareToken);
			} else {
				this.#player.playFile(
					this.#urlForFile(file),
					file.mimetype,
					file.id,
					file.name,
					this.#shareToken
				);
			}
			this.#player.setPlaylistIndex(this.#playlist.currentIndex(), this.#playlist.length());
			if (OCA.Music.playlistTabView) {
				// this will also take care of clearing any existing focus if the this.#currentFile is not a playlist
				OCA.Music.playlistTabView.setCurrentTrack(this.#currentFile.id, this.#playlist.currentIndex());
			}
		}
	}

	#createFileClickCallback(fileOpenCallback) {
		return (file) => {
			// Check if playing file changes
			if (this.#currentFile?.id != file.id) {
				this.#currentFile = file;
				fileOpenCallback();
			}
			else {
				this.#player.togglePlayback();
			}
		};
	}

	#registerToNcFiles(ncFiles, mimes, onActionCallback, actionId) {
		ncFiles.registerFileAction(new ncFiles.FileAction({
			id: actionId,
			displayName: () => t('music', 'Play'),
			iconSvgInline: () => playIconSvgData,
			default: ncFiles.DefaultType.DEFAULT,
			order: -1, // prioritize over the built-in Viewer app

			enabled: (nodes, _view) => {
				if (nodes.length !== 1) {
					return false;
				}	
				return mimes.includes(nodes[0].mime);
			},

			/**
			 * Function executed on single file action
			 * @return true if the action was executed successfully,
			 * false otherwise and null if the action is silent/undefined.
			 * @throws Error if the action failed
			 */
			exec: (file, view, dir) => {
				const adaptFile = (f) => {
					return {id: f.fileid, name: f.basename, mimetype: f.mime, path: dir};
				};
				onActionCallback(adaptFile(file));

				if (!this.#playingListFile) {
					// get the directory contents and use them as the play queue
					view.getContents(dir).then(contents => {
						const dirFiles = _.map(contents.contents, adaptFile);
						// By default, the files are sorted simply by the character codes, putting upper case names before lower case
						// and not respecting any locale settings. This doesn't match the order on the UI, regardless of the column
						// used for sorting. Sort on our own treating numbers "naturally" and using the locale of the browser since
						// this is how NC28 seems to do this (older NC versions, on the other hand, used the user-selected UI-language
						// as the locale for sorting although user-selected locale would have made even more sense).
						// This still leaves such a mismatch that the special characters may be sorted differently by localeCompare than
						// what NC28 Files does (it uses the 3rd party library natural-orderby for this).
						dirFiles.sort((a, b) => a.name.localeCompare(b.name, undefined, {numeric: true, sensitivity: 'base'}));

						this.#playlist = new OCA.Music.Playlist(dirFiles, this.#audioMimes, this.#currentFile.id);
						this.#player.setNextAndPrevEnabled(this.#playlist.length() > 1);
					});
				}

				return true;
			},
		}));
	}

	#registerToFileActions(fileActions, mimes, onActionCallback, actionId) {
		// Handle 'play' action on file row
		const onPlay = (fileName, context) => {
			this.#fileList = context.fileList;
			let file = this.#fileList.findFile(fileName);

			// Recent versions of Nextcloud (at least 23-27, possibly some others too) fire this handler when
			// the user navigates to an audio file with a direct link. In that case, the callback happens before
			// the context.filList is populated and we can't operate normally. Just ignore these cases.
			if (file !== null) {
				onActionCallback(file);
			}
		};

		const registerPlayerForMime = (mime) => {
			fileActions.register(
					mime,
					actionId,
					OC.PERMISSION_READ,
					playIconPath,
					onPlay,
					t('music', 'Play')
			);
			fileActions.setDefault(mime, actionId);
		};
		_.forEach(mimes, registerPlayerForMime);
	}

	#openAudioFile() {
		this.#playingListFile = false;

		this.#player.show();
		this.#player.showBusy(false);
		this.#playlist = new OCA.Music.Playlist(this.#fileList?.files ?? [this.#currentFile], this.#audioMimes, this.#currentFile.id);
		this.#player.setNextAndPrevEnabled(this.#playlist.length() > 1);
		this.#jumpToPlaylistFile(this.#playlist.currentFile());
	}

	#openPlaylistFile(onReadyCallback = null) {
		this.#playingListFile = true;

		// clear the previous playback
		this.#player.stop();
		this.#playlist = null;

		this.#player.show(this.#currentFile.name);
		this.#player.showBusy(true);

		const listFileId = this.#currentFile.id;
		const onPlaylistLoaded = (data) => {
			// ignore the callback if the player is already closed or file changed by the time we get it
			if (this.#currentFile?.id == listFileId) {
				this.#player.showBusy(false);
				if (data.files.length > 0) {
					this.#playlist = new OCA.Music.Playlist(data.files, this.#audioMimes, data.files[0].id);
					this.#player.setNextAndPrevEnabled(this.#playlist.length() > 1);
					this.#jumpToPlaylistFile(this.#playlist.currentFile());
				}
				else {
					this.#currentFile = null;
					this.#player.close();
					OC.Notification.showTemporary(t('music', 'No files from the playlist could be found'));
				}
				if (data.invalid_paths.length > 0) {
					let note = t('music', 'The playlist contained {count} invalid path(s).',
							{count: data.invalid_paths.length});
					if (!this.#shareToken) {
						// Guide the user to look for details, unless this is a public share where the
						// details pane is not available.
						note += ' ' +  t('music', 'See the playlist file details.');
					}
					OC.Notification.showTemporary(note);
				}

				if (onReadyCallback) {
					onReadyCallback();
				}
			}
		};
		const onError = () => {
			// ignore the callback if the player is already closed or file changed by the time we get it
			if (this.#currentFile?.id == listFileId) {
				this.#player.close();
				this.#player.showBusy(false);
				this.#currentFile = null;
				OC.Notification.showTemporary(t('music', 'Error reading playlist file'));
			}
		};
		OCA.Music.PlaylistFileService.readFile(listFileId, onPlaylistLoaded, onError, this.#shareToken);
	}

};
