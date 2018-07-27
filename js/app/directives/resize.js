/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright 2013 Morris Jobke
 * @copyright 2018 Pauli Järvinen
 *
 */

angular.module('Music').directive('resize', ['$window', '$rootScope', '$timeout',
function($window, $rootScope, $timeout) {
	return function(scope, element, attrs, ctrl) {
		function resizeNavigation() {
			var appView = $('#app-view');

			// top and button padding of 5px each
			var height = appView.height() - 10;

			element.css('height', height);

			// Hide or replace every second letter on short screens
			if(height < 300) {
				element.find("a").removeClass("dotted").addClass("stripped");
			} else if(height < 500) {
				element.find("a").removeClass("stripped").addClass("dotted");
			} else {
				element.find("a").removeClass("dotted stripped");
			}

			if(height < 300) {
				element.css('line-height', Math.floor(height/13) + 'px');
			} else {
				element.css('line-height', Math.floor(height/26) + 'px');
			}

			// anchor the alphabet navigation to the right edge of the app view
			var appViewRight = $window.innerWidth - appView.offset().left - appView.innerWidth();
			element.css('right', appViewRight);
		}

		resizeNavigation();

		// trigger resize on window resize and player status changes
		var unsubscribeFuncs = [
			$rootScope.$on('resize', resizeNavigation),
			$rootScope.$watch('started', function() {
				$timeout(resizeNavigation);
			})
		];

		// unsubscribe listeners when the scope is destroyed
		scope.$on('$destroy', function () {
			_.each(unsubscribeFuncs, function(func) { func(); });
		});
	};
}]);
