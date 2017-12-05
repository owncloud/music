/**
 * ownCloud - Music app
 *
 * @author Moritz Meißelbach
 * @copyright 2017 Moritz Meißelbach <moritz@meisselba.ch>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

angular.module('Music').directive('vsResize', ['$window', '$rootScope', function ($window, $rootScope) {
	return {
		link: {
			post: function (scope, element, attrs, ctrl) {

				var breakpoints = scope.$eval(attrs.vsResize);

				if (!breakpoints) {
					return;
				}

				var resetGrid = _.debounce(function () {
					var width = $window.innerWidth;
					var currentBreakpoint = element[0].getAttribute('data-size');
					for (var breakpoint in breakpoints) {
						if (width > breakpoints[breakpoint].width) {
							continue;
						}
						if (currentBreakpoint !== breakpoint) {
							element[0].setAttribute('data-size', breakpoint);
							scope.currentLayout = breakpoint;
							attrs.$set('vsSize', 'dimensions.' + breakpoint);
							scope.$apply();
							console.log('triggering');
							scope.$emit('vsRepeatTrigger');
						}
						break;
					}
				}, 60);

				resetGrid();

				// trigger resize on window resize and player status changes
				var unsubscribeFuncs = [
					$rootScope.$on('windowResized', resetGrid),
					$rootScope.$watch('started', resetGrid)
				];

				// unsubscribe listeners when the scope is destroyed
				scope.$on('$destroy', function () {
					_.each(unsubscribeFuncs, function (func) {
						func();
					});
				});
			}
		}
	};
}]);
