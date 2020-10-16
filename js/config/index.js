/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2020
 */

/**
 * `require` all modules in the given webpack context
 */
function requireAll(context) {
	context.keys().forEach(context);
}

/* Vendor libraries */
window.angular = require('angular');
require('node_modules/angular-gettext/dist/angular-gettext.js');
require('node_modules/angular-route/angular-route.js');
require('node_modules/angular-sanitize/angular-sanitize.js');
require('node_modules/angular-scroll/angular-scroll.js');
window.AV = require('vendor/aurora/aurora.js');
require('vendor/aurora/flac.js');
require('vendor/aurora/mp3.js');
require('vendor/dragdrop/draganddrop.js');
require('node_modules/javascript-detect-element-resize/jquery.resize.js');
window.Cookies = require('node_modules/js-cookie/src/js.cookie.js');
require('node_modules/restangular');

/* Music app files */
require('./app.js');
requireAll(require.context('../app', /*use subdirectories:*/ true));
requireAll(require.context('../l10n', /*use subdirectories:*/ false));
requireAll(require.context('../shared', /*use subdirectories:*/ false));
requireAll(require.context('../../img', /*use subdirectories:*/ true));
requireAll(require.context('../../css', /*use subdirectories:*/ false));
