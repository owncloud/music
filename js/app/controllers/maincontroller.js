
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
	['$scope', '$routeParams', 'artists', 'playerService', function ($scope, $routeParams, artists, playerService) {

	$scope.artists = artists;

	$scope.playTrack = function(track) {
		var artist = _.find($scope.artists,
			function(artist){
				return artist.id === track.artist.id;
			}),
			album = _.find(artist.albums,
			function(album){
				return album.id === track.album.id;
			});
		playerService.publish('play', {track: track, artist: artist, album: album});
	};

	$scope.playAlbum = function(album) {
		var track = album.tracks[0],
			artist = _.find($scope.artists,
			function(artist){
				return artist.id === track.artist.id;
			});
		playerService.publish('play', {track: track, artist: artist, album: album});
	};

	$scope.playArtist = function(artist) {
		var album = artist.albums[0],
			track = album.tracks[0];
		playerService.publish('play', {track: track, artist: artist, album: album});
	};
}]);