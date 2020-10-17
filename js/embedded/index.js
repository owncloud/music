/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2020
 */

/* Vendor libraries */
window.AV = require('vendor/aurora/aurora.js');
require('vendor/aurora/flac.js');
require('vendor/aurora/mp3.js');
require('node_modules/javascript-detect-element-resize/jquery.resize.js');
require('vendor/jquery-initialize');
window.Cookies = require('node_modules/js-cookie');

/* Embedded player files */
require('../shared/playerwrapper.js');
require('../shared/utils.js');
require('./embeddedplayer.js');
require('./main.js');
require('./playlist.js');
require('./playlistfileservice.js');
require('./playlisttabview.js');
require('./searchrenderer.js');
