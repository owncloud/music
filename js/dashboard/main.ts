/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2024
 */

document.addEventListener('DOMContentLoaded', () => {
	OCA.Dashboard.register('music', (el : HTMLElement) => {
		el.innerHTML = 'This is content for the Music widget';
	});
});