/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright 2025 Pauli Järvinen
 *
 */

import { ProgressInfo } from 'shared/progressinfo';

angular.module('Music').directive('progressInfo', [
function () {
	return {
		restrict: 'E',
		scope: {
			player: '<'
		},
		link: function(scope, element, _attrs, _ctrl) {
			const widget = new ProgressInfo(scope.player);
			element.replaceWith(widget.getElement());
		}
	};
}]);
