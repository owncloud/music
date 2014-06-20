
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


		$scope.createPlaylist = function(playlist) {
			var playlists = Restangular.all('playlists');
			playlists.post(playlist).then(function(){
				$scope.playlists = [];
				console.log(arguments);
				$scope.newPlaylistForm.name = "";
				$scope.newPlaylistForm.trackIds = "";
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
			var playlist = Restangular.one('playlists', id);
			playlist.get().then($scope.getCurrentPlist());
		};



	$scope.currentPlaylist = $routeParams.playlistId;
	console.log("Current PLaylist: "+ $scope.currentPlaylist);
	$scope.playlistSongs = [];
	$scope.playlists = [];
// 	$scope.list = function(playlistId) {
// 		$scope.playlists = PlaylistFactory.getPlaylists();
// 	};

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
	};

// 	$scope.newPlaylist = function() {
// 		var li = $(document.createElement('li'))
// 		  .load(OC.filePath('music', 'ajax', 'plist-new.php'));
// 		var bodyListener = function(e) {
// 			if($('#new-plist-dialog').find($(e.target)).length === 0) {
// 				$('#create').closest('li').after(li).show();
// 				$('#new-plist-dialog').remove();
// 				$('body').unbind('click', bodyListener);
// 			}
// 		};
// 		$('body').bind('click', bodyListener);
// 		$('#create').closest('li').after(li).hide();
// 	};

// 	$scope.addPlaylist = function(plistName) {
// 		console.log(plistName);
// 		var message = Restangular.one('addPlaylist');
// 		message.post(plistName).then(function(newMsg) {
// 			console.log("i sent it");
// 			$scope.playlists = [];
// 			$scope.getPlaylists();
// 			$location.url('/');
// 			$('#new-plist-dialog').remove();
// 		}, function error(reason) {
// 			console.log("error :(");
// 		});
// 	};
// 	$scope.getPlaylist = function(id) {
// 		console.log(id);
// 		var message = Restangular.one('getPlaylist');
// 		message.post(id).then(function(pl) {
// 			var li = $(document.createElement('li'))
// 			.load(OC.filePath('music', 'ajax', 'edit-plists.php'), {plist: pl});
//
// 			var bodyListener = function(e) {
// 				if($('#pledit_dialog').find($(e.target)).length === 0) {
// 					$('#playlist-edit'+pl[0].id).closest('li').before(li).show();
// 					$('#pledit_dialog').parent().remove();
// 					$('body').unbind('click', bodyListener);
// 				}
// 			};
// 			$('body').bind('click', bodyListener);
//
// 			$('#playlist-edit'+pl[0].id).closest('li').before(li).hide();
// 		}, function error(reason) {
// 			console.log("error :(");
// 		});
// 	};
// 	$scope.removePlaylist = function(id) {
// 		console.log(id);
// 		var message = Restangular.one('removePlaylist');
// 		message.post(id).then(function(newMsg) {
// 			console.log("i sent it");
// 			$scope.playlists = [];
// 			$scope.getPlaylists();
// 		}, function error(reason) {
// 			console.log("error :(");
// 		});
// 	};
	$scope.playlistIndex = function() {
		for(var i=0; i < $scope.playlists.length; i++) {
			if($scope.playlists[i].id == $scope.currentPlaylist) {
				console.log("i found the playlist: "+i);
				return i;
		  }
		}
		console.log("no i couldn't!!!!! "+i);
		return 0;
	};
	$scope.updatePlaylist = function(id, name, songs) {
		console.log("adding to: "+id+" name: "+name+" songs: "+songs);
		var message = Restangular.one('updatePlaylist/'+id+"/"+name);
			message.post(songs).then(function(newMsg) {
				console.log("updated");
				$scope.playlists = [];
				$scope.getPlaylists();
				$('#pledit_dialog').hide();
		}, function error(reason) {
			console.log("error :(");
		});
	};
	$scope.rootFolders = 'bob';
	$scope.array = [];
// 	$scope.getRawSongs = function(ind) {
// 		Restangular.all('fulllist').getList().then(function(fulllist){
// 			$scope.songArray = $scope.playlists[ind].songs.split(',');
// 			console.log("sending raw song list for: "+ind + " which plID is: "+$scope.playlists[ind].id+" and name is: "+$scope.playlists[ind].name + " and the songs of this list are: "+$scope.playlists[ind].songs);
// 			console.log("the first song for example: "+$scope.songArray[0]);
// 			var song;
// 			for(var i=0; i < fulllist.length; i++) {
// 				song = fulllist[i];
// 				for(var j=0; j < $scope.songArray.length; j++) {
// 			  		if($scope.songArray[j]==song.id) {
// 						console.log(ind+" index, " +$scope.playlists[ind].name + " songs: "+$scope.songArray+" matches with: " +song.id);
// 						$scope.playlistSongs.push(song);
// 					}
// 				}
// 		    	}
// 		});
// //		return $scope.playlistSongs;
// 	};

//	$scope.list();
	$scope.getPlaylists();
	console.log("$scope.currentPlaylist = " + $scope.currentPlaylist);
	console.log("$scope.playlists[0] = " + $scope.playlists[0]);
	console.log("$scope.playlists = " + $scope.playlists);
//	$scope.getRawSongs();
//	$scope.getCurrentPlist();

}]);
/*
$(document).on('click', '#addPlaylist', function () {
	angular.element('#new-plist-dialog').scope().createPlaylist(document.getElementById('name').value);
});
*/
$(document).on('click', '#updatePlaylist', function () {
	angular.element('#pledit_dialog').scope().updatePlaylist(document.getElementById('id').value, document.getElementById('name').value, document.getElementById('songs').value);
});
