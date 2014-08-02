
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
	['$scope', '$routeParams', 'PlaylistFactory', 'playlistService', 'gettextCatalog', 'Restangular', '$location',
	function ($scope, $routeParams, PlaylistFactory, playlistService, gettextCatalog, Restangular, $location) {

		$scope.playlistSongs = [];
		$scope.playlists = [];

		$scope.newPlaylistForm = {
			name: null,
			trackIds: []
		};
		$scope.createPlaylist = function(playlist) {
			var playlists = Restangular.all('playlists');
			playlists.post(playlist).then(function(){
				$scope.playlists = [];
				console.log("new plist:" +arguments);

// 				$scope.newPlaylistForm.trackIds = "";
// 				$scope.newPlaylistForm.$setPristine();
				$scope.getPlaylists();
			});

		};
		$scope.getPlaylists = function() {
			Restangular.all('playlists').getList().then(function(getPlaylists){
				var plist;
				console.log("getplists: "+getPlaylists);
				for(var i=0; i < getPlaylists.length; i++) {
					plist = getPlaylists[i];
					$scope.playlists.push(plist);
				}
				return $scope.playlists;

				}, function error(reason) {
					console.log("cannot get playlists");
			});
		};
		$scope.removePlaylist = function(id) {
			var playlist = Restangular.one('playlists', id);
			playlist.remove().then(function(){
				$scope.playlists = [];
				$scope.getPlaylists();
			});
		};
		$scope.getPlaylist = function(id) {
			var playlist = Restangular.one('playlists', id).get().then(function(playlist){

// 				$scope.getCurrentPlist();
				console.log("==========HERE GOES THE LIST "+id+"=============");
				console.log("name: "+playlist.name);
				$scope.currentPlaylistName = playlist.name;
				console.log("trackIds: "+playlist.trackIds);
				$scope.currentPlaylistSongs = playlist.trackIds;
				console.log("=======================");

			});
		};

		$scope.currentPlaylist = $routeParams.playlistId;
		console.log("Current Playlist: "+ $scope.currentPlaylist);

		$scope.getListSongs = function() {

		};

		$scope.getCurrentPlist = function() {
			for(var i=0; i < $scope.playlists.length; i++) {
				if($scope.playlists[i].id == $scope.currentPlaylist) {
					$scope.cPlistN = $scope.playlists[i].name;
					$scope.cPlistId = $scope.playlists[i].id;
					$scope.cPlistSongs = $scope.playlists[i].songs;
					$scope.getRawSongs(i);
					break;
				}
			}
			console.log("-------------------$scope.cPlist.name: " + $scope.cPlistN);
			console.log("-------------------$scope.cPlist.id: " + $scope.cPlistId);
			console.log("-------------------$scope.cPlist.songs: " + $scope.cPlistSongs);
		};

		$scope.addTracks = function(playlistId, songs) {
			console.log("adding to: "+playlistId+" songs: "+songs);
			var message = Restangular.one('playlists', playlistId).all("add");
			message.post({trackIds: songs}).then(function(newMsg) {
					console.log("tracks added "+ songs);
					$scope.playlists = [];
					$scope.getPlaylists();
			}, function error(reason) {
				console.log("error :(");
			});
		};

		$scope.$on('droppedSong', function(event, songId, playlistId) {
			$scope.addTracks(playlistId, songId);
			console.log("I am activated by main" + songId + " " + playlistId);
		});
		$scope.getPlaylists();
		console.log("$scope.currentPlaylist = " + $scope.currentPlaylist);
		console.log("$scope.playlists[0] = " + $scope.playlists[0]);
		console.log("$scope.playlists = " + $scope.playlists);
	//	$scope.getRawSongs();
	//	$scope.getCurrentPlist();

}]);
