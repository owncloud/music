/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Volkan Gezer <volkangezer@gmail.com>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright 2014 Volkan Gezer
 * @copyright 2020 - 2023 Pauli Järvinen
 *
 */

angular.module('Music').directive('onEnter', function () {
	return function (scope, element, attrs) {
		element.bind('keydown keypress', function (event) {
			if (event.which === 13) {
				scope.$apply(function () {
					scope.$eval(attrs.onEnter, {$event: event});
				});
				event.preventDefault();
			}
		});
	};
});
