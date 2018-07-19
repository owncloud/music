/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright 2017, 2018 Pauli Järvinen
 */

angular.module('Music').directive('navigationItem', function() {
	return {
		scope: {
			text: '=',
			destination: '=',
			playlist: '='
		},
		templateUrl: 'navigationitem.html',
		replace: true
	};
});
