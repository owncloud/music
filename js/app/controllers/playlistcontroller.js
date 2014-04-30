
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


angular.module('Music').controller('PlaylistController',
	['$scope', '$routeParams', 'PlaylistFactory', 'playlistService', 'gettextCatalog', 'Restangular',
	function ($scope, $routeParams, PlaylistFactory, playlistService, gettextCatalog, Restangular) {

	$scope.currentPlaylist = $routeParams.playlistId;
	$scope.playlistSongs = [];
	$scope.playlists = [];
	$scope.list = function(playlistId) {
		$scope.playlists = PlaylistFactory.getPlaylists();
	};

	$scope.getListSongs = function() {


	};

	$scope.playlistIndex = function() {
	  for(var i=0; i < $scope.playlists.length; i++) {
	    if($scope.playlists[i].id == $scope.currentPlaylist) {
	      return i;

	    }
	  }
	    return 0;
	};

	$scope.getRawSongs = function() {
		Restangular.all('fulllist').getList().then(function(fulllist){
		    var song;
		    for(var i=0; i < fulllist.length; i++) {
			song = fulllist[i];
			for(var j=0; j < $scope.playlists[$scope.playlistIndex()].songs.length; j++) {
			  if($scope.playlists[$scope.playlistIndex()].songs[j]==song.id)
					$scope.playlistSongs.push(song);
			}
		    }
		});
		return $scope.playlistSongs;
	};

	$scope.list();
	$scope.getRawSongs();

}]);
