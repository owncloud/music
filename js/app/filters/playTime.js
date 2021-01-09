/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <morris.jobke@gmail.com>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright 2013 Morris Jobke
 * @copyright 2020, 2021 Pauli Järvinen
 *
 */

angular.module('Music').filter('playTime', function() {
	return OCA.Music.Utils.formatPlayTime;
});