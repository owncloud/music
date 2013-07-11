/**
 * ownCloud - Music app
 *
 * @author Morris Jobke
 10:01:07 <mjob> Hab Zeit* @copyright 2013 Morris Jobke <morris.jobke@gmail.com>
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

angular.module('Music').directive('albumart', function() {
	return function(scope, element, attrs, ctrl) {
		attrs.$observe('albumart',function(){
			// TODO fix dependency on md5
			var hash = md5(attrs.albumart),
				maxRange = parseInt('ffffffffff', 16),
				red = parseInt(hash.substr(0,10), 16)/maxRange,
				green = parseInt(hash.substr(10,10), 16)/maxRange,
				blue = parseInt(hash.substr(20,10), 16)/maxRange;
			red *= 256;
			green *= 256;
			blue *= 256;
			rgb = [Math.floor(red), Math.floor(green), Math.floor(blue)];
			element.css('background-color', 'rgb(' + rgb.join(',') + ')');
		});
	};
});