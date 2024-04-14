/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2017 - 2024
 */

import playOverlayPath from '../../img/play-overlay.svg';

OCA.Music = OCA.Music || {};

/**
 * "File share player" is used on individually shared files
 */
OCA.Music.FileShareView = class {

	#mPlayer;
	#mShareToken;

	constructor(supportedMimes) {
		// Add click handler to the file preview if this is a supported file.
		// The feature is disabled on old IE versions where there's no MutationObserver and
		// $.initialize would not work.
		// Disable the feature also on Nextcloud 13+ where there's already a built-in player
		// on the file share page, and our player would be redundant.
		if (typeof MutationObserver !== 'undefined'
			&& $('audio').length === 0
			&& supportedMimes.includes($('#mimetype').val()))
		{
			this.#mPlayer = new OCA.Music.EmbeddedPlayer();
			this.#mShareToken = $('#sharingToken').val();
			this.#initView();
		}
	}

	#initView() {
		// The .publicpreview is added dynamically by another script.
		// Augment it with the click handler once it gets added.
		$.initialize('img.publicpreview', () => {
			const previewImg = $('img.publicpreview');
			// All of the following are needed only for IE. There, the overlay doesn't work without setting
			// the image position to relative. And still, the overlay is sometimes much smaller than the image,
			// creating a need to have also the image itself as clickable area.
			previewImg.css('position', 'relative').css('cursor', 'pointer').click(this.#onClick);

			// Add "play overlay" shown on hover
			const overlay = $('<img class="play-overlay">')
				.attr('src', playOverlayPath)
				.click(() => this.#onClick())
				.insertAfter(previewImg);

			const adjustOverlay = () => {
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
			previewImg.on('error', () => {
				previewImg.attr('src', OC.imagePath('core', 'filetypes/audio')).width(128).height(128);
				adjustOverlay();
			});
		});
	}

	#onClick() {
		if (!this.#mPlayer.isVisible()) {
			this.#mPlayer.show();
			this.#mPlayer.playFile(
					$('#downloadURL').val(),
					$('#mimetype').val(),
					0,
					$('#filename').val(),
					this.#mShareToken
			);
		}
		else {
			this.#mPlayer.togglePlayback();
		}
	}
};
