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
window._ = require('../vendor/lodash');
window.$ = window.jQuery = require('../vendor/jquery');
window.angular = require('../vendor/angular');
require('../vendor/angular-gettext/dist/angular-gettext.js');
require('../vendor/angular-route/angular-route.js');
require('../vendor/angular-sanitize/angular-sanitize.js');
require('../vendor/angular-scroll/angular-scroll.js');
window.AV = require('../vendor/aurora/aurora.js');
require('../vendor/aurora/flac.js');
require('../vendor/aurora/mp3.js');
require('../vendor/dragdrop/draganddrop.js');
require('../vendor/javascript-detect-element-resize/jquery.resize.js');
window.Cookies = require('../vendor/js-cookie/src/js.cookie.js');
require('../vendor/restangular');

// Put back the functions removed by Lodash 4.x
_.mixin({
	pluck: _.map,
	findWhere: _.find
});

/* Music app files */
require('./app.js');
requireAll(require.context('../app', /*use subdirectories:*/ true));
requireAll(require.context('../l10n', /*use subdirectories:*/ false));
requireAll(require.context('../shared', /*use subdirectories:*/ false));
requireAll(require.context('../../img', /*use subdirectories:*/ true));
requireAll(require.context('../../css', /*use subdirectories:*/ false));
