/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2017 - 2024
 */

window.addEventListener('DOMContentLoaded', function() {
	let mPlayer = new OCA.Music.EmbeddedPlayer();

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

	function register() {
		// Add player on single-file-share page if the MIME is a supported audio type
		if ($('#header').hasClass('share-file')) {
			OCA.Music.fileShareView = new OCA.Music.FileShareView(mPlayer, mAudioMimes);
		}
		// Add play action to file rows with supported mime type, either audio or playlist.
		// Protect against cases where this script gets (accidentally) loaded outside of the Files app.
		else if (typeof OCA.Files !== 'undefined') {
			OCA.Music.folderView = new OCA.Music.FolderView(mPlayer, mAudioMimes, mPlaylistMimes);
			OCA.Music.initPlaylistTabView(mPlaylistMimes);
			connectPlaylistTabViewEvents(OCA.Music.folderView);
		}
	}

	function connectPlaylistTabViewEvents(folderView) {
		if (OCA.Music.playlistTabView) {
			OCA.Music.playlistTabView.on('playlistItemClick', (file, index) => folderView.onPlaylistItemClick(file, index));

			OCA.Music.playlistTabView.on('rendered', () => {
				const plState = folderView.playlistFileState();
				if (plState !== null) {
					OCA.Music.playlistTabView.setCurrentTrack(plState.fileId, plState.index);
				} else {
					OCA.Music.playlistTabView.setCurrentTrack(null);
				}
			});
		}
	}

	register();
});
