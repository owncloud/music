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


angular.module('Music').controller('MainController',
	['$scope', '$routeParams', 'Artists', 'playlistService', function ($scope, $routeParams, Artists, playlistService) {

	$scope.artists = Artists;

	$scope.playTrack = function(track) {
		playlistService.setPlaylist([track]);
		playlistService.publish('play');
	};

	$scope.playAlbum = function(album) {
		playlistService.setPlaylist(album.tracks);
		playlistService.publish('play');
	};

	$scope.playArtist = function(artist) {
		var playlist = _.union.apply(null,
				_.map(
					artist.albums,
					function(album){
						return album.tracks;
					}
				)
			);
		playlistService.setPlaylist(playlist);
		playlistService.publish('play');
	};
}]);