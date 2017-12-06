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


angular.module('Music').service('albumGridService', ['$rootScope', function ($rootScope) {

	var breakpoints = {
		phone: {columns: 1, width: 768},
		tablet: {columns: 2, width: 1300},
		desktop: {columns: 3, width: 2000},
		ultrawide: {columns: 4, width: 9999}
	};

	var albumHeight = 282;
	var artistHeight = 36;

	return {
		getBreakpoints: function () {
			return breakpoints;
		},

		getColumns: function () {
			return columns;
		},

		getDimensionsForArtist: function (artist) {
			var dimensions = {};

			for (var breakpoint in breakpoints) {
				var numAlbums = artist.albums.length;
				var columns = breakpoints[breakpoint].columns;
				var rows = Math.floor(numAlbums / columns);
				if (numAlbums % columns) { // Add a row for remaining albums
					rows++;
				}
				dimensions[breakpoint] = artistHeight + (rows * albumHeight); // We now have the total height of an artist's album list for this breakpoint
			}

			return dimensions;
		},

		getAlbumHeight: function () {
			return albumHeight;
		},

		getArtistHeight: function () {
			return artistHeight;
		}
	};

}
]);
