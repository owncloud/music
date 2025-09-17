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

import { VolumeControl } from 'shared/volumecontrol';

angular.module('Music').directive('volumeControl', [
function () {
	return {
		restrict: 'E',
		scope: {
			player: '<'
		},
		link: function(scope, element, _attrs, _ctrl) {
			const widget = new VolumeControl(scope.player, {
				tooltipSuffix: '\n[NUMPAD +/-]',
				muteTooltipSuffix: ' [M]'
			});
			element.replaceWith(widget.getElement());

			scope.$on('toggleMute', () => widget.toggleMute());
			scope.$on('offsetVolume', (_event, offset) => widget.offsetVolume(offset));
		}
	};
}]);
