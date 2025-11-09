/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright 2018 - 2025 Pauli Järvinen
 *
 */

import ResizeObserver from 'node_modules/resize-observer-polyfill';

angular.module('Music').directive('resizeNotifier', ['$rootScope', '$timeout', function($rootScope, $timeout) {
	return function(_scope, element, _attrs, _ctrl) {
		const ro = new ResizeObserver(_entries => {
			$timeout(() => $rootScope.$emit('resize', element));
		});

		ro.observe(element[0]);
	};
}]);
