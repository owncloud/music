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

angular.module('Music').directive('alphabetNavigation', ['$rootScope', '$timeout',
function($rootScope, $timeout) {
	return {
		restrict: 'E',
		scope: {
			targets: '<',
			scrollToTarget: '<'
		},
		templateUrl: 'alphabetnavigation.html',
		replace: true,
		link: function(scope, element, attrs, ctrl) {

			scope.letters = [
				'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J',
				'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T',
				'U', 'V', 'W', 'X', 'Y', 'Z'
			];

			function onResize(event, appView) {
				// top and button padding of 5px each
				var height = appView.height() - 10;

				element.css('height', height);

				// Hide or replace every second letter on short screens
				if (height < 300) {
					element.find("a").removeClass("dotted").addClass("stripped");
				} else if (height < 500) {
					element.find("a").removeClass("stripped").addClass("dotted");
				} else {
					element.find("a").removeClass("dotted stripped");
				}

				if (height < 300) {
					element.css('line-height', Math.floor(height/13) + 'px');
				} else {
					element.css('line-height', Math.floor(height/26) + 'px');
				}

				// anchor the alphabet navigation to the right edge of the app view
				var appViewRight = document.body.clientWidth - appView.offset().left - appView.innerWidth();
				element.css('right', appViewRight);
			}

			function onPlayerBarShownOrHidden() {
				// React asynchronously so that angularjs bindings have had chance
				// to update the properties of the #app-view element.
				$timeout(function() {
					onResize(null, $('#app-view'));
				});
			}

			// trigger resize on #app-view resize and player status changes
			var unsubscribeFuncs = [
				$rootScope.$on('resize', onResize),
				$rootScope.$watch('started', onPlayerBarShownOrHidden)
			];

			// unsubscribe listeners when the scope is destroyed
			scope.$on('$destroy', function () {
				_.each(unsubscribeFuncs, function(func) { func(); });
			});
		}
	};
}]);
