/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Matthew Wells
 * @author Pauli Järvinen
 * @copyright Matthew Wells 2025
 * @copyright Pauli Järvinen 2025
 */

window.addEventListener('DOMContentLoaded', () => {
	const appData = document.querySelector('#app-content')?.dataset;
	if (appData) {
		const bc = new BroadcastChannel(appData.identifier + '-scrobble-session-result');
		bc.postMessage(Boolean(appData.result));
	}
});