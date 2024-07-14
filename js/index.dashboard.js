/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2024
 */

/**
 * `require` all modules in the given webpack context
 */
function requireAll(context) {
	context.keys().forEach(context);
}

/* Vendor libraries */
require('vendor/aurora/alac.js');
require('vendor/aurora/flac.js');
require('vendor/aurora/mp3.js');
require('vendor/aurora/aac.js'); // this has to come after mp3.js, otherwise MP3 playback breaks

/* Dashboard widget files */
requireAll(require.context('./shared', /*use subdirectories:*/ false));
requireAll(require.context('./dashboard', /*use subdirectories:*/ false));
requireAll(require.context('../css/shared', /*use subdirectories:*/ false));
requireAll(require.context('../css/dashboard', /*use subdirectories:*/ false));
