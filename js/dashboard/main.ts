/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2024
 */

import { MusicWidget } from './musicwidget';
import { PlayerWrapper } from 'shared/playerwrapper';
import { PlayQueue } from 'shared/playqueue';

document.addEventListener('DOMContentLoaded', () => {
	OCA.Dashboard.register('music', (el : HTMLElement) => {
		const $container = $(el);
		$container.addClass('music-widget');
		const player = new PlayerWrapper();
		const queue = new PlayQueue();
		const widget = new MusicWidget($container, player, queue);
	});
});
