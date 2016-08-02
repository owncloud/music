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

angular.module('Music').directive('albumart', ['$http', '$queueFactory', function($http, $queueFactory) {
	// Calling $http.get immediately for all album cover images would be bad idea because it would
	// block the playback until all the covers are loaded. Hence, we use queue which allows 5 simultaneous
	// HTTP GET queries. This is faster than running them only one at a time, but still enables starting
	// the playback with rather short delay.
	var httpQueue = $queueFactory(5);

	function setCoverImage(element, imageUrl) {
		// remove placeholder stuff
		element.html('');
		element.css('background-color', '');
		// add background image
		element.css('filter', "progid:DXImageTransform.Microsoft.AlphaImageLoader(src='" + imageUrl + "', sizingMethod='scale')");
		element.css('-ms-filter', "progid:DXImageTransform.Microsoft.AlphaImageLoader(src='" + imageUrl + "', sizingMethod='scale')");
		element.css('background-image', 'url(' + imageUrl + ')');
	}

	function setPlaceholder(element, text) {
		if(text) {
			// remove background image
			element.css('-ms-filter', '');
			element.css('background-image', '');
			// add placeholder stuff
			element.imageplaceholder(text);
			// remove style of the placeholder to allow mobile styling
			element.css('line-height', '');
			element.css('font-size', '');
		}
	}

	return function(scope, element, attrs, ctrl) {
		var coverLoadFailed = false;

		var onCoverChanged = function() {
			if(attrs.cover) {
				httpQueue.enqueue(function() {
					return $http.get(attrs.cover).then(
						function(response) {
							setCoverImage(element, attrs.cover);
							coverLoadFailed = false;
						},
						function(reject) {
							setPlaceholder(element, attrs.albumart);
							coverLoadFailed = true;
						}
					);
				});
			} else {
				setPlaceholder(element, attrs.albumart);
			}
		};

		var onAlbumartChanged = function() {
			if(!attrs.cover || coverLoadFailed) {
				setPlaceholder(element, attrs.albumart);
			}
		};

		attrs.$observe('albumart', onAlbumartChanged);
		attrs.$observe('cover', onCoverChanged);
	};
}]);

