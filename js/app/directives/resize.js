/**
 * ownCloud - Music app
 *
 * @author Morris Jobke
 * @copyright 2013 Morris Jobke <morris.jobke@gmail.com>
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

angular.module('Music').directive('resize', ['$window', '$rootScope', function($window, $rootScope) {
	return function(scope, element, attrs, ctrl) {
		var resizeNavigation = function() {
			var height = $window.innerHeight;

			// top and button padding of 5px each
			height = height - 10;
			// remove playerbar height if started
			if(scope.started) {
				height = height - 65;
			}
			// remove header height
			height = height - 45;

			element.css('height', height);

			// Hide or replace every second letter on short screens
			if(height < 300) {
				$(".alphabet-navigation a").removeClass("dotted").addClass("stripped");
			} else if(height < 500) {
				$(".alphabet-navigation a").removeClass("stripped").addClass("dotted");
			} else {
				$(".alphabet-navigation a").removeClass("dotted stripped");
			}

			if(height < 300) {
				element.css('line-height', Math.floor(height/13) + 'px');
			} else {
				element.css('line-height', Math.floor(height/26) + 'px');
			}
		};

		// trigger resize on window resize
		$($window).resize(function() {
			resizeNavigation();
		});

		// trigger resize on player status changes
		$rootScope.$watch('started', function() {
			resizeNavigation();
		});

		resizeNavigation();
	};
}]);
