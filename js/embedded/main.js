/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2017 - 2023
 */

import playIconPath from '../../img/play-big.svg';
import playIconSvgData from '../../img/play-big.svg?raw';
import playOverlayPath from '../../img/play-overlay.svg';

import { FileAction, registerFileAction, DefaultType } from '@nextcloud/files';

window.addEventListener('DOMContentLoaded', function() {
	// Nextcloud 13+ have a built-in Music player in its "individual shared music file" page.
	// Initialize our player only if such player is not found.
	if ($('audio').length === 0) {
		initEmbeddedPlayer();
	}
});

function initEmbeddedPlayer() {

	let mCurrentFile = null; // may be an audio file or a playlist file
	let mPlayingListFile = false;
	let mFileList = null; // FileList from Files or Sharing app
	let mShareToken = $('#sharingToken').val(); // undefined when not on share page

	let mPlayer = new OCA.Music.EmbeddedPlayer(onClose, onNext, onPrev, onMenuOpen, onShowList, onImportList, onImportRadio);
	let mPlaylist = null;

	const mAudioMimes = _.filter([
		'audio/aac',
		'audio/aiff',
		'audio/basic',
		'audio/flac',
		'audio/mp4',
		'audio/m4b',
		'audio/mpeg',
		'audio/ogg',
		'audio/wav',
		'audio/x-aiff',
		'audio/x-caf',
	], (mime) => mPlayer.canPlayMime(mime));

	const mPlaylistMimes = [
		'audio/mpegurl',
		'audio/x-scpls'
	];

	register();

	function onClose() {
		mCurrentFile = null;
		mPlayingListFile = false;
		mPlaylist.reset();
		if (OCA.Music.playlistTabView) {
			OCA.Music.playlistTabView.setCurrentTrack(null, null);
		}
	}

	function onNext() {
		jumpToPlaylistFile(mPlaylist.next());
	}

	function onPrev() {
		jumpToPlaylistFile(mPlaylist.prev());
	}

	function viewingCurrentFileFolder() {
		return mCurrentFile && mFileList && mCurrentFile.path == mFileList.breadcrumb.dir;
	}

	function onMenuOpen($menu) {
		// disable/enable the "Show list" item
		let $showItem = $menu.find('#playlist-menu-show');
		if (viewingCurrentFileFolder()) {
			$showItem.removeClass('disabled');
			$showItem.removeAttr('title');
		} else {
			$showItem.addClass('disabled');
			$showItem.attr('title', t('music', 'The option is available only while the parent folder of the playlist file is shown'));
		}

		// disable/enable the "Import list to Music" item
		let inLibraryFilesCount = _(mPlaylist.files()).filter('in_library').size();
		let extStreamsCount = _(mPlaylist.files()).filter('url').size();
		let outLibraryFilesCount = mPlaylist.length() - inLibraryFilesCount;

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
						{inCount: inLibraryFilesCount, totalCount: mPlaylist.length()}));
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
		if (extStreamsCount === mPlaylist.length()) {
			$importListItem.addClass('hidden');
		} else {
			$importListItem.removeClass('hidden');
		}
	}

	function onShowList() {
		mFileList.scrollTo(mCurrentFile.name);
		mFileList.showDetailsView(mCurrentFile.name, OCA.Music.playlistTabView.id);
	}

	function onImportList() {
		doImportFromFile(OCA.Music.PlaylistFileService.importPlaylist);
	}

	function onImportRadio() {
		doImportFromFile(OCA.Music.PlaylistFileService.importRadio);
	}

	function doImportFromFile(serviceImportFunc) {
		// The busy animation is shown on the file item if we are still viewing the folder
		// where the file resides. The importing itself is possible regardless.
		let animationShown = viewingCurrentFileFolder();
		if (animationShown) {
			showCurrentFileAsBusy(true);
		}

		serviceImportFunc(mCurrentFile, (_result) => {
			if (animationShown) {
				showCurrentFileAsBusy(false);
			}
		});
	}

	function jumpToPlaylistFile(file) {
		if (!file) {
			mPlayer.close();
		} else {
			if (!mPlayingListFile) {
				mCurrentFile = file;
			}
			if (file.external) {
				mPlayer.playExtUrl(file.url, file.caption, mShareToken);
			} else {
				mPlayer.playFile(
					file.url ?? mFileList.getDownloadUrl(file.name, file.path),
					file.mimetype,
					file.id,
					file.name,
					mShareToken
				);
			}
			mPlayer.setPlaylistIndex(mPlaylist.currentIndex(), mPlaylist.length());
			if (OCA.Music.playlistTabView) {
				// this will also take care of clearing any existing focus if the mCurrentFile is not a playlist
				OCA.Music.playlistTabView.setCurrentTrack(mCurrentFile.id, mPlaylist.currentIndex());
			}
		}
	}

	function register() {
		// Add play action to file rows with supported mime type, either audio or playlist.
		// Protect against cases where this script gets (accidentally) loaded outside of the Files app.
		if (typeof OCA.Files !== 'undefined') {
			OCA.Music.initPlaylistTabView(mPlaylistMimes);
			connectPlaylistTabViewEvents();
			registerFolderPlayer(mAudioMimes, openAudioFile, 'music_play_audio_file');
			registerFolderPlayer(mPlaylistMimes, openPlaylistFile, 'music_play_playlist_file');
		}

		// Add player on single-file-share page if the MIME is a supported audio type
		if ($('#header').hasClass('share-file')) {
			registerFileSharePlayer(mAudioMimes);
		}
	}

	function connectPlaylistTabViewEvents() {
		if (OCA.Music.playlistTabView) {
			OCA.Music.playlistTabView.on('playlistItemClick', function(playlistId, playlistName, itemIdx) {
				if (mCurrentFile !== null && playlistId == mCurrentFile.id) {
					if (itemIdx == mPlaylist.currentIndex()) {
						mPlayer.togglePlayback();
					} else {
						jumpToPlaylistFile(mPlaylist.jumpToIndex(itemIdx));
					}
				}
				else {
					mFileList = OCA.Files.App.fileList;
					mCurrentFile = mFileList.findFile(playlistName);
					openPlaylistFile(function() {
						jumpToPlaylistFile(mPlaylist.jumpToIndex(itemIdx));
					});
				}
			});
			OCA.Music.playlistTabView.on('rendered', function() {
				if (mCurrentFile !== null) {
					OCA.Music.playlistTabView.setCurrentTrack(mCurrentFile.id, mPlaylist.currentIndex());
				}
			});
		}
	}

	/**
	 * "Folder player" is used in the Files app and on shared folders to play audio files and playlist files
	 */
	function registerFolderPlayer(mimes, openFileCallback, actionId) {
		const wrappedCallback = function(file) {
			// Check if playing file changes
			if (mCurrentFile === null || mCurrentFile.id != file.id) {
				mCurrentFile = file;
				openFileCallback();
			}
			else {
				mPlayer.togglePlayback();
			}
		};

		if (OCA.Files?.fileActions) {
			registerFolderPlayerBeforeNC28(mimes, wrappedCallback, actionId);
		} else {
			registerFolderPlayerAfterNC28(mimes, wrappedCallback, actionId);
		}
	}

	function registerFolderPlayerAfterNC28(mimes, onActionCallback, actionId) {
		registerFileAction(new FileAction({
			id: actionId,
			displayName: () => t('music', 'Play'),
			iconSvgInline: () => playIconSvgData,
			default: DefaultType.DEFAULT,
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
			exec: (file, _view, _dir) => {
				mFileList = null;
				const adaptedFile = {id: file.fileid, name: file.basename, mimetype: file.mime, url: file.source};
				onActionCallback(adaptedFile);
				return true;
			},
		}));
	}

	function registerFolderPlayerBeforeNC28(mimes, onActionCallback, actionId) {
		// Handle 'play' action on file row
		let onPlay = function(fileName, context) {
			mFileList = context.fileList;
			let file = mFileList.findFile(fileName);

			// Recent versions of Nextcloud (at least 23-27, possibly some others too) fire this handler when
			// the user navigates to an audio file with a direct link. In that case, the callback happens before
			// the context.filList is populated and we can't operate normally. Just ignore these cases.
			if (file !== null) {
				onActionCallback(file);
			}
		};

		let registerPlayerForMime = function(mime) {
			OCA.Files.fileActions.register(
					mime,
					actionId,
					OC.PERMISSION_READ,
					playIconPath,
					onPlay,
					t('music', 'Play')
			);
			OCA.Files.fileActions.setDefault(mime, actionId);
		};
		_.forEach(mimes, registerPlayerForMime);
	}

	function openAudioFile() {
		mPlayingListFile = false;

		mPlayer.show();
		mPlaylist = new OCA.Music.Playlist(mFileList?.files ?? [mCurrentFile], mAudioMimes, mCurrentFile.id);
		mPlayer.setNextAndPrevEnabled(mPlaylist.length() > 1);
		jumpToPlaylistFile(mPlaylist.currentFile());
	}

	function openPlaylistFile(onReadyCallback = null) {
		mPlayingListFile = true;

		showCurrentFileAsBusy(true);
		let onPlaylistLoaded = function(data) {
			showCurrentFileAsBusy(false);
			if (data.files.length > 0) {
				mPlayer.show(mCurrentFile.name);
				mPlaylist = new OCA.Music.Playlist(data.files, mAudioMimes, data.files[0].id);
				mPlayer.setNextAndPrevEnabled(mPlaylist.length() > 1);
				jumpToPlaylistFile(mPlaylist.currentFile());
			}
			else {
				mCurrentFile = null;
				OC.Notification.showTemporary(t('music', 'No files from the playlist could be found'));
			}
			if (data.invalid_paths.length > 0) {
				let note = t('music', 'The playlist contained {count} invalid path(s).',
						{count: data.invalid_paths.length});
				if (!mShareToken) {
					// Guide the user to look for details, unless this is a public share where the
					// details pane is not available.
					note += ' ' +  t('music', 'See the playlist file details.');
				}
				OC.Notification.showTemporary(note);
			}

			showCurrentFileAsBusy(false);

			if (onReadyCallback) {
				onReadyCallback();
			}
		};
		let onError = function() {
			showCurrentFileAsBusy(false);
			mCurrentFile = null;
			OC.Notification.showTemporary(t('music', 'Error reading playlist file'));
		};
		OCA.Music.PlaylistFileService.readFile(mCurrentFile.id, onPlaylistLoaded, onError, mShareToken);
	}

	function showCurrentFileAsBusy(isBusy) {
		if (mFileList) {
			let $file = mFileList.findFileEl(mCurrentFile.name);
			if ($file) {
				mFileList.showFileBusyState($file, isBusy);
			}
		} else {
			// TODO: How to do this on NC28?
		}
	}

	/**
	 * "File share player" is used on individually shared files
	 */
	function registerFileSharePlayer(supportedMimes) {
		let onClick = function() {
			if (!mPlayer.isVisible()) {
				mPlayer.show();
				mPlayer.playFile(
						$('#downloadURL').val(),
						$('#mimetype').val(),
						0,
						$('#filename').val(),
						mShareToken
				);
			}
			else {
				mPlayer.togglePlayback();
			}
		};

		// Add click handler to the file preview if this is a supported file.
		// The feature is disabled on old IE versions where there's no MutationObserver and
		// $.initialize would not work.
		if (typeof MutationObserver !== 'undefined'
				&& _.includes(supportedMimes, $('#mimetype').val()))
		{
			// The #publicpreview is added dynamically by another script.
			// Augment it with the click handler once it gets added.
			$.initialize('img.publicpreview', function() {
				const previewImg = $(this);
				// All of the following are needed only for IE. There, the overlay doesn't work without setting
				// the image position to relative. And still, the overlay is sometimes much smaller than the image,
				// creating a need to have also the image itself as clickable area.
				previewImg.css('position', 'relative').css('cursor', 'pointer').click(onClick);

				// Add "play overlay" shown on hover
				const overlay = $('<img class="play-overlay">')
					.attr('src', playOverlayPath)
					.click(onClick)
					.insertAfter(previewImg);

				const adjustOverlay = function() {
					const width = previewImg.width();
					const height = previewImg.height();
					overlay.width(width).height(height).css('margin-left', `-${width}px`);
				};
				adjustOverlay();

				// In case the image data is not actually loaded yet, the size information 
				// is not valid above. Recheck once loaded.
				previewImg.on('load', adjustOverlay);

				// At least in ownCloud 10 and Nextcloud 11-13, there is such an oversight
				// that if MP3 file has no embedded cover, then the placeholder is not shown
				// either. Fix that on our own.
				previewImg.on('error', function() {
					previewImg.attr('src', OC.imagePath('core', 'filetypes/audio')).width(128).height(128);
					adjustOverlay();
				});
			});
		}
	}

}
