/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2024
 */

import { PlayerWrapper } from "./playerwrapper";

const soundOffIconPath = require('../../img/sound-off.svg') as string;
const soundIconPath = require('../../img/sound.svg') as string;

export class VolumeControl {

    #player : PlayerWrapper;
    #elem : JQuery<HTMLElement>;
    #volume : number;
    #lastVolume : number;

    constructor(player : PlayerWrapper) {
        this.#player = player;

        this.#volume = parseInt(OCA.Music.Storage.get('volume')) || 50;  // volume can be 0~100
        player.setVolume(this.#volume);

        this.#createHtml();
    }

	addToContainer(container : JQuery<HTMLElement>) {
		container.append(this.#elem);
	}

    #createHtml() {
		this.#elem = $(document.createElement('div'))
			.attr('class', 'music-volume-control');

		let volumeIcon = $(document.createElement('img'))
			.attr('class', 'volume-icon control small svg')
			.attr('src', soundIconPath)
			.on('click', () => {
				const setVolume = (value : number) => {
					volumeSlider.val(value);
					volumeSlider.trigger('input');
				};

				if (this.#lastVolume) {
					setVolume(this.#lastVolume);
					this.#lastVolume = null;
				}
				else {
					this.#lastVolume = this.#volume;
					setVolume(0);
				}
			});

		const self = this;
		let volumeSlider = $(document.createElement('input'))
			.attr('class', 'volume-slider')
			.attr('min', '0')
			.attr('max', '100')
			.attr('type', 'range')
			.attr('value', this.#volume)
			.on('input change', function() {
				const value = $(this).val() as number;

				// Reset last known volume, if a new value is selected via the slider
				if (value && self.#lastVolume && self.#lastVolume !== self.#volume) {
					self.#lastVolume = null;
				}

				self.#volume = value;
				self.#player.setVolume(value);
				OCA.Music.Storage.set('volume', value);

				// Show correct icon if muted 
				volumeIcon.attr('src', value == 0 ? soundOffIconPath : soundIconPath);
			});

        this.#elem.append(volumeIcon);
        this.#elem.append(volumeSlider);
	}

}