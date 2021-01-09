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
	return function(input) {
		var hours = Math.floor(input / 3600);
		var minutes = Math.floor((input - hours*3600) / 60);
		var seconds = Math.floor(input % 60);

		if (hours > 0) {
			return hours + ':' + fmtTwoDigits(minutes) + ':' + fmtTwoDigits(seconds);
		} else {
			return minutes + ':' + fmtTwoDigits(seconds);
		}
	};

	// Format the given integer with two digits, prepending with a leading zero if necessary
	function fmtTwoDigits(integer) {
		return (integer < 10 ? '0' : '') + integer;
	}
});