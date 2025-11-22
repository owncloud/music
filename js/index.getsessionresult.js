/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Matthew Wells
 * @copyright Matthew Wells 2025
 */

const bc = new BroadcastChannel('scrobble-session-result');
const appContent = document.querySelector('#app-content');
if (appContent) {
    bc.postMessage(Boolean(appContent.dataset.result));
}
