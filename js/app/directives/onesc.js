/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright 2023 Pauli Järvinen
 *
 */

angular.module('Music').directive('onEsc', ['$timeout', function ($timeout) {
	return function (scope, element, attrs) {
		element.on('keydown keypress', (event) => {
			if (event.which === 27) {
				$timeout(() => scope.$eval(attrs.onEsc, {$event: event}));
				event.preventDefault();
			}
		});
	};
}]);
