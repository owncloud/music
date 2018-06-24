/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright 2017 Pauli Järvinen
 */

angular.module('Music').directive('sidebarListItem', function() {
	return {
		scope: {
			text: '=',
			destination: '=',
			playlist: '='
		},
		templateUrl: 'sidebarlistitem.html',
		replace: true
	};
});
