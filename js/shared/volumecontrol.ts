/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2024, 2025
 */

import { PlayerWrapper } from "./playerwrapper";

const soundOffIconPath = require('../../img/sound-off.svg') as string;
const soundIconPath = require('../../img/sound.svg') as string;

declare function t(module : string, text : string) : string;

interface VolumeControlOptions {
	tooltipSuffix? : string;
	muteTooltipSuffix? : string;
}

export class VolumeControl {

	#player : PlayerWrapper;
	#elem : JQuery<HTMLElement>;
	#volume : number;
	#lastVolume : number;

	constructor(player : PlayerWrapper, options : VolumeControlOptions = {}) {
		this.#player = player;
		this.#createHtml(options);
		this.setVolume(parseInt(OCA.Music.Storage.get('volume')) || 50); // volume can be 0~100
	}

	addToContainer(container : JQuery<HTMLElement>) {
		container.append(this.#elem);
	}

	getElement() : JQuery<HTMLElement> {
		return this.#elem;
	}

	setVolume(value : number) {
		value = Math.max(0, Math.min(100, value)); // Clamp to 0-100
		const volumeSlider = this.#elem.find('.volume-slider');
		volumeSlider.val(value);
		volumeSlider.trigger('input');
	}

	offsetVolume(offset : number) : void {
		this.setVolume(this.#volume + offset);
	}

	toggleMute() : void {
		if (this.#lastVolume) {
			this.setVolume(this.#lastVolume);
			this.#lastVolume = null;
		} else {
			this.#lastVolume = this.#volume;
			this.setVolume(0);
		}
	}

	#createHtml(options : VolumeControlOptions = {}) {
		this.#elem = $(document.createElement('div'))
			.attr('class', 'music-volume-control');

		const self = this;
		let volumeIcon = $(document.createElement('img'))
			.attr('class', 'volume-icon control small svg')
			.attr('src', soundIconPath)
			.on('click', () => self.toggleMute());

		let volumeSlider = $(document.createElement('input'))
			.attr('class', 'volume-slider')
			.attr('min', '0')
			.attr('max', '100')
			.attr('type', 'range')
			.attr('title', t('music', 'Volume') + (options.tooltipSuffix || ''))
			.on('input change', function() {
				const value = parseInt($(this).val() as string);

				// Reset last known volume, if a new value is selected via the slider
				if (value && self.#lastVolume && self.#lastVolume !== self.#volume) {
					self.#lastVolume = null;
				}

				self.#volume = value;
				self.#player.setVolume(value);
				OCA.Music.Storage.set('volume', value);

				// Show correct icon if muted 
				if (value == 0) {
					volumeIcon.attr('src', soundOffIconPath)
						.attr('title', t('music', 'Unmute') + (options.muteTooltipSuffix || ''));
				} else {
					volumeIcon.attr('src', soundIconPath)
						.attr('title', t('music', 'Mute') + (options.muteTooltipSuffix || ''));
				}
				
			});

		this.#elem.on('wheel', ($event) => {
			const event = $event.originalEvent as WheelEvent;
			if (!event.ctrlKey) {
				$event.preventDefault();
				let step = -Math.sign(event.deltaY);
				if (event.shiftKey) {
					step *= 5;
				}

				self.offsetVolume(step);
			}
		});

		this.#elem.append(volumeIcon);
		this.#elem.append(volumeSlider);
	}

}