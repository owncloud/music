/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2017, 2018
 */

$(document).ready(function() {
	// Nextcloud 13 has a built-in Music player in its "individual shared music file" page.
	// Initialize our player only if such player is not found.
	if ($('audio').length === 0) {
		initEmbeddedPlayer();
	}
});

function initEmbeddedPlayer() {

	var currentFile = null;
	var shareToken = $('#sharingToken').val(); // undefined when not on share page

	// function to get download URL for given file name, created later as it
	// has to bind some variables not available here
	var urlForFile = null;

	var actionRegisteredForSingleShare = false; // to check that we don't register more than one click handler

	// Register the play action for the supported mime types both synchronously
	// and asynchronously once the player init is done. This is necessary because
	// the types supported by SoundManager2 are known only in the callback but
	// the callback does not fire at all on browsers with no codecs (some versions
	// of Chromium) where we still can support mp3 and flac formats using aurora.js.
	var player = new EmbeddedPlayer(register, onClose, onNext, onPrev);
	register();

	var playlist = new Playlist();

	function onClose() {
		currentFile = null;
		playlist.reset();
	}

	function onNext() {
		jumpToPlaylistFile(playlist.next());
	}

	function onPrev() {
		jumpToPlaylistFile(playlist.prev());
	}

	function jumpToPlaylistFile(file) {
		if (!file) {
			player.close();
		} else {
			currentFile = file.id;
			player.playFile(
				urlForFile(file.name),
				file.mimetype,
				currentFile,
				file.name,
				shareToken
			);
		}
	}

	function register() {
		var audioMimes = [
			'audio/flac',
			'audio/mp4',
			'audio/m4b',
			'audio/mpeg',
			'audio/ogg',
			'audio/wav'
		];
		var supportedMimes = _.filter(audioMimes, player.canPlayMIME, player);

		// Add play action to file rows with supported mime type.
		// Protect against cases where this script gets (accidentally) loaded outside of the Files app.
		if (typeof OCA.Files !== 'undefined') {
			registerFolderPlayer(supportedMimes);
		}

		// Add player on single-fle-share page if the MIME is supported
		if ($('#header').hasClass('share-file')) {
			registerFileSharePlayer(supportedMimes);
		}
	}

	/**
	 * "Folder player" is used in the Files app and on shared folders
	 */
	function registerFolderPlayer(supportedMimes) {
		// Handle 'play' action on file row
		var onPlay = function(fileName, context) {
			player.show();

			// Check if playing file changes
			var filerow = context.$file;
			if (currentFile != filerow.attr('data-id')) {
				currentFile = filerow.attr('data-id');

				urlForFile = function(name) {
					var url = context.fileList.getDownloadUrl(name, context.dir);
					// append request token unless this is a public share
					if (!shareToken) {
						var delimiter = _.includes(url, '?') ? '&' : '?';
						url += delimiter + 'requesttoken=' + encodeURIComponent(OC.requestToken);
					}
					return url;
				};

				player.playFile(
					urlForFile(fileName),
					filerow.attr('data-mime'),
					currentFile,
					fileName,
					shareToken
				);

				playlist.init(context.fileList.files, supportedMimes, currentFile);
				player.setNextAndPrevEnabled(playlist.length() > 1);
			}
			else {
				player.togglePlayback();
			}
		};

		var registerPlayerForMime = function(mime) {
			OCA.Files.fileActions.register(
					mime,
					'music-play',
					OC.PERMISSION_READ,
					OC.imagePath('music', 'play-big'),
					onPlay,
					t('music', 'Play')
			);
			OCA.Files.fileActions.setDefault(mime, 'music-play');
		};
		_.forEach(supportedMimes, registerPlayerForMime);
	}

	/**
	 * "File share player" is used on individually shared files
	 */
	function registerFileSharePlayer(supportedMimes) {
		var onClick = function() {
			player.show();
			if (!currentFile) {
				currentFile = 1; // bogus id

				player.playFile(
						$('#downloadURL').val(),
						$('#mimetype').val(),
						0,
						$('#filename').val(),
						shareToken
				);
			}
			else {
				player.togglePlayback();
			}
		};

		// Add click handler to the file preview if this is a supported file.
		// The feature is disabled on old IE versions where there's no MutationObserver and
		// $.initialize would not work. Also, make sure to add the handler only once even if this method
		// gets called multiple times.
		if (typeof MutationObserver !== "undefined"
				&& !actionRegisteredForSingleShare
				&& _.contains(supportedMimes, $('#mimetype').val()))
		{
			actionRegisteredForSingleShare = true;

			// The #publicpreview is added dynamically by another script.
			// Augment it with the click handler once it gets added.
			$.initialize('img.publicpreview', function() {
				var previewImg = $(this);
				previewImg.css('cursor', 'pointer');
				previewImg.click(onClick);

				// At least in ownCloud 10 and Nextcloud 11-13, there is such an oversight
				// that if MP3 file has no embedded cover, then the placeholder is not shown
				// either. Fix that on our own.
				previewImg.error(function() {
					previewImg.attr('src', OC.imagePath('core', 'filetypes/audio'));
					previewImg.css('width', '128px');
					previewImg.css('height', '128px');
				});
			});
		}
	}

}
