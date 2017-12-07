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

angular.module('Music').directive('vsScrollTo', ['$window', '$rootScope', function ($window, $rootScope) {
	return {
		link: {
			post: function (scope, element, attrs, ctrl) {

				var vsScrollInstance = attrs.vsScrollTarget;

				var container = document.getElementById(vsScrollInstance);
				var targetElementScope = angular.element(container).scope();
				element.on('click', function () {
					var target = attrs.vsScrollTo;
					targetElementScope.$apply(function () {
						var scrollParent = targetElementScope.$scrollParent;
						var sizes = targetElementScope.sizesCumulative;

						if (target < sizes.length) {
							scrollParent.animate({scrollTop: sizes[target]}, "slow");

						}
					});
				});

			}
		}
	};
}]);
