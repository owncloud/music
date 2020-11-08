/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2017 - 2020
 */

import playIcon from '../../img/play-big.svg';


window.addEventListener('DOMContentLoaded', function() {
	// Nextcloud 13+ have a built-in Music player in its "individual shared music file" page.
	// Initialize our player only if such player is not found.
	if ($('audio').length === 0) {
		initEmbeddedPlayer();
	}
});

function initEmbeddedPlayer() {

	var mCurrentFile = null; // may be an audio file or a playlist file
	var mPlayingListFile = false;
	var mFileList = null; // FileList from Files or Sharing app
	var mShareToken = $('#sharingToken').val(); // undefined when not on share page

	var mPlayer = new OCA.Music.EmbeddedPlayer(onClose, onNext, onPrev, onMenuOpen, onShowList, onImportList);
	var mPlaylist = new OCA.Music.Playlist();

	var mAudioMimes = _.filter([
		'audio/flac',
		'audio/mp4',
		'audio/m4b',
		'audio/mpeg',
		'audio/ogg',
		'audio/wav'
	], mPlayer.canPlayMIME, mPlayer);

	var mPlaylistMimes = [
		'audio/mpegurl',
		'audio/x-scpls'
	];

	register();

	function urlForFile(file) {
		var url = mFileList.getDownloadUrl(file.name, file.path);

		// append request token unless this is a public share
		if (!mShareToken) {
			var delimiter = _.includes(url, '?') ? '&' : '?';
			url += delimiter + 'requesttoken=' + encodeURIComponent(OC.requestToken);
		}
		return url;
	}

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
		var $showItem = $menu.find('#playlist-menu-show');
		if (viewingCurrentFileFolder()) {
			$showItem.removeClass('disabled');
			$showItem.removeAttr('title');
		} else {
			$showItem.addClass('disabled');
			$showItem.attr('title', t('music', 'The option is available only while the parent folder of the playlist file is shown'));
		}

		// disable/enable the "Import to Music" item
		var inLibraryFilesCount = _.filter(mPlaylist.files(), {in_library: true}).length;
		var outLibraryFilesCount = mPlaylist.length() - inLibraryFilesCount;
		var $importItem = $menu.find('#playlist-menu-import');
		if (inLibraryFilesCount === 0) {
			$importItem.addClass('disabled');
			$importItem.attr('title', t('music', 'None of the playlist files are within your music library'));
		} else {
			$importItem.removeClass('disabled');
			if (outLibraryFilesCount > 0) {
				$importItem.attr('title',
						t('music', '{inCount} out of {totalCount} files are within your music library and can be imported',
						{inCount: inLibraryFilesCount, totalCount: mPlaylist.length()}));
			} else {
				$importItem.removeAttr('title');
			}
		}
	}

	function onShowList() {
		mFileList.scrollTo(mCurrentFile.name);
		mFileList.showDetailsView(mCurrentFile.name, OCA.Music.playlistTabView.id);
	}

	function onImportList() {
		// The busy animation is shown on the file item if we are still viewing the folder
		// where the file resides. The importing itself is possible regardless. 
		if (viewingCurrentFileFolder()) {
			var $file = mFileList.findFileEl(mCurrentFile.name);
			mFileList.showFileBusyState($file, true);
		}

		OCA.Music.playlistFileService.importFile(mCurrentFile, function(_result) {
			if ($file) {
				mFileList.showFileBusyState($file, false);
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
			mPlayer.playFile(
				urlForFile(file),
				file.mimetype,
				file.id,
				file.name,
				mShareToken
			);
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
			registerFolderPlayer(mAudioMimes, openAudioFile);
			registerFolderPlayer(mPlaylistMimes, openPlaylistFile);
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
	function registerFolderPlayer(mimes, openFileCallback) {
		// Handle 'play' action on file row
		var onPlay = function(fileName, context) {
			mFileList = context.fileList;
			var file = mFileList.findFile(fileName);

			// Check if playing file changes
			if (mCurrentFile === null || mCurrentFile.id != file.id) {
				mCurrentFile = file;
				openFileCallback();
			}
			else {
				mPlayer.togglePlayback();
			}
		};

		var registerPlayerForMime = function(mime) {
			OCA.Files.fileActions.register(
					mime,
					'music-play',
					OC.PERMISSION_READ,
					OC.filePath('music', 'dist', playIcon),
					onPlay,
					t('music', 'Play')
			);
			OCA.Files.fileActions.setDefault(mime, 'music-play');
		};
		_.forEach(mimes, registerPlayerForMime);
	}

	function openAudioFile() {
		mPlayingListFile = false;

		mPlayer.show();
		mPlaylist.init(mFileList.files, mAudioMimes, mCurrentFile.id);
		mPlayer.setNextAndPrevEnabled(mPlaylist.length() > 1);
		jumpToPlaylistFile(mPlaylist.currentFile());
	}

	function openPlaylistFile(onReadyCallback /*optional*/) {
		mPlayingListFile = true;
		var $file = mFileList.findFileEl(mCurrentFile.name);

		mFileList.showFileBusyState($file, true);
		var onPlaylistLoaded = function(data) {
			if (data.files.length > 0) {
				mPlayer.show(mCurrentFile.name);
				mPlaylist.init(data.files, mAudioMimes, data.files[0].id);
				mPlayer.setNextAndPrevEnabled(mPlaylist.length() > 1);
				jumpToPlaylistFile(mPlaylist.currentFile());
			}
			else {
				mCurrentFile = null;
				OC.Notification.showTemporary(t('music', 'No files from the playlist could be found'));
			}
			if (data.invalid_paths.length > 0) {
				var note = t('music', 'The playlist contained {count} invalid path(s).',
						{count: data.invalid_paths.length});
				if (!mShareToken) {
					// Guide the user to look for details, unless this is a public share where the
					// details pane is not available.
					note += ' ' +  t('music', 'See the playlist file details.');
				}
				OC.Notification.showTemporary(note);
			}

			mFileList.showFileBusyState($file, false);

			if (onReadyCallback) {
				onReadyCallback();
			}
		};
		var onError = function() {
			mCurrentFile = null;
			OC.Notification.showTemporary(t('music', 'Error reading playlist file'));
			mFileList.showFileBusyState($file, false);
		};
		OCA.Music.playlistFileService.readFile(mCurrentFile.id, onPlaylistLoaded, onError, mShareToken);
	}

	/**
	 * "File share player" is used on individually shared files
	 */
	function registerFileSharePlayer(supportedMimes) {
		var onClick = function() {
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
				var previewImg = $(this);
				previewImg.css('cursor', 'pointer');
				previewImg.click(onClick);

				// At least in ownCloud 10 and Nextcloud 11-13, there is such an oversight
				// that if MP3 file has no embedded cover, then the placeholder is not shown
				// either. Fix that on our own.
				previewImg.on('error', function() {
					previewImg.attr('src', OC.imagePath('core', 'filetypes/audio'));
					previewImg.css('width', '128px');
					previewImg.css('height', '128px');
				});
			});
		}
	}

}
