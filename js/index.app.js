/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2020 - 2022
 */

/**
 * `require` all modules in the given webpack context
 */
function requireAll(context) {
	context.keys().forEach(context);
}

/* Polyfills for IE compatibility */
require('node_modules/core-js/features/array/includes');
require('node_modules/core-js/features/string/replace-all');
require('node_modules/core-js/features/string/starts-with');
require('node_modules/core-js/features/string/ends-with');
require('vendor/polyfill/keyboard.js');

/* Vendor libraries */
window.angular = require('angular');
require('node_modules/angular-gettext');
require('node_modules/angular-route');
require('node_modules/angular-sanitize');
require('node_modules/angular-scroll');
require('node_modules/javascript-detect-element-resize/jquery.resize.js');
require('node_modules/long-press-event');
require('node_modules/restangular');
require('vendor/aurora/alac.js');
require('vendor/aurora/flac.js');
require('vendor/aurora/mp3.js');
require('vendor/aurora/aac.js'); // this has to come after mp3.js, otherwise MP3 playback breaks
require('vendor/dragdrop/draganddrop.js');
require('vendor/nextcloud/placeholder.js');
// jquery.initialize can't be initialized on a browser lacking the MutationObserver like IE10
if (typeof MutationObserver !== 'undefined') {
	require('vendor/jquery-initialize');
}

/* Music app files */
requireAll(require.context('./app', /*use subdirectories:*/ true));
requireAll(require.context('./shared', /*use subdirectories:*/ false));
requireAll(require.context('../img', /*use subdirectories:*/ true));
requireAll(require.context('../css', /*use subdirectories:*/ false));
