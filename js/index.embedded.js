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
require('node_modules/core-js/features/string/ends-with');
require('node_modules/core-js/features/weak-map');
require('node_modules/core-js/features/weak-set');

/* Vendor libraries */
require('vendor/aurora/alac.js');
require('vendor/aurora/flac.js');
require('vendor/aurora/mp3.js');
require('vendor/aurora/aac.js'); // this has to come after mp3.js, otherwise MP3 playback breaks
require('node_modules/javascript-detect-element-resize/jquery.resize.js');
// jquery.initialize can't be initialized on a browser lacking the MutationObserver like IE10
if (typeof MutationObserver !== 'undefined') {
	require('vendor/jquery-initialize');
}

/* Embedded player files */
requireAll(require.context('./shared', /*use subdirectories:*/ false));
requireAll(require.context('./embedded', /*use subdirectories:*/ false));
requireAll(require.context('../css/shared', /*use subdirectories:*/ false));
requireAll(require.context('../css/embedded', /*use subdirectories:*/ false));
