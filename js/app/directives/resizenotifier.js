/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright 2018 - 2021 Pauli Järvinen
 *
 */

angular.module('Music').directive('resizeNotifier', ['$rootScope', '$timeout', function($rootScope, $timeout) {
	return function(_scope, element, _attrs, _ctrl) {
		element.resize(function() {
			$timeout(() => $rootScope.$emit('resize', element));
		});
	};
}]);
