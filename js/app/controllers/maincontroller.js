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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.	See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.	If not, see <http://www.gnu.org/licenses/>.
 *
 */


angular.module('Music').controller('MainController',
	['$rootScope', '$scope', '$location', 'Artist', 'Album', 'Track', 'playlistService', 'gettextCatalog', 'Prefix',
	function ($rootScope, $scope, $location, Artist, Album, Track, playlistService, gettextCatalog, Prefix) {

	// retrieve language from backend - is set in ng-app HTML element
	gettextCatalog.currentLanguage = $rootScope.lang;

	$scope.loading = true;

	$scope.currentTrack = null;
	playlistService.subscribe('playing', function(e, track){
		// determine if already inside of an $apply or $digest
		// see http://stackoverflow.com/a/12859093
		if($scope.$$phase) {
			$scope.currentTrack = track;
		} else {
			$scope.$apply(function(){
				$scope.currentTrack = track;
			});
		}
	});

	$scope.anchorArtists = [];

	$scope.letters = [
		'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J',
		'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T',
		'U', 'V', 'W', 'X', 'Y', 'Z'
	];

	$scope.letterAvailable = {};
	for(var i in $scope.letters){
		$scope.letterAvailable[$scope.letters[i]] = false;
	}

	Artist.query().then(function(artists){
		$scope.loading = false;
		$scope.artists = artists;
		for(var i=0; i < artists.length; i++) {
			var artist = artists[i],
				letter = artist.name.substr(0,1).toUpperCase();

			if($scope.letterAvailable.hasOwnProperty(letter) === true) {
				if($scope.letterAvailable[letter] === false) {
					$scope.anchorArtists.push(artist.name);
				}
				$scope.letterAvailable[letter] = true;
			}

		}
	});

	$scope.$watch('currentArtist', function(newArtist, oldArtist){
		if(newArtist !== oldArtist){
			Artist.get(newArtist.id).then(function(artist){
				$scope.artist = artist;
			});
		}	
	});

	$scope.playTrack = function(track) {
		var artist = _.find($scope.artists,
			function(artist) {
				return artist.id === track.artist.id;
			}),
			album = _.find(artist.albums,
			function(album) {
				return album.id === track.album.id;
			}),
			tracks = _.sortBy(album.tracks,
				function(track) {
					return track.number;
				}
			);
		// determine index of clicked track
		var index = tracks.indexOf(track);
		if(index > 0) {
			// slice array in two parts and interchange them
			var begin = tracks.slice(0, index);
			var end = tracks.slice(index);
			tracks = end.concat(begin);
		}
		playlistService.setPlaylist(tracks);
		playlistService.publish('play');
	};

	$scope.playAlbum = function(album) {
		var tracks = _.sortBy(album.tracks,
				function(track) {
					return track.number;
				}
			);
		playlistService.setPlaylist(tracks);
		playlistService.publish('play');
	};

	$scope.playArtist = function(artist) {
		var albums = _.sortBy(artist.albums,
			function(album) {
				return album.year;
			}),
			playlist = _.union.apply(null,
				_.map(
					albums,
					function(album){
						var tracks = _.sortBy(album.tracks,
							function(track) {
								return track.number;
							}
						);
						return tracks;
					}
				)
			);
		playlistService.setPlaylist(playlist);
		playlistService.publish('play');
	};

	$scope.switchAnimationType = function(type) {
		$rootScope.animationType = type;
	};

	// default filter value
	$scope.filter = 'artist';
	$scope.artistFilterClicked = function() {
		$scope.filter = 'artist';
	};
	$scope.albumFilterClicked = function() {
		$scope.filter = 'album';
		if ( !$scope.albums ) {
			Album.queryWithoutTree().then(function(albums) {
				$scope.albums = albums;
			});
		}
	};
	$scope.trackFilterClicked = function() {
		$scope.filter = 'track';
		if ( !$scope.tracks ) {
			Track.query().then(function(tracks) {
				$scope.tracks = tracks;
			});
		}
	};

	$scope.artistClicked = function(clickedArtist) {
		$scope.currentArtist = clickedArtist;
		$location.path(app_path + "artist/" + $scope.currentArtist.id);
	};

	$scope.albumClicked = function(album) {
		alert('clicked Album: '+ album.id);
		$location.path(Prefix + "album/" + album.id);
	};

	$scope.trackClicked = function(track, context) {
		//copy the context tracks in a tracks array
		tracks = context;
		var index = tracks.indexOf(track);
		if(index > 0) {
			// slice array in two parts and interchange them
			var begin = tracks.slice(0, index);
			var end = tracks.slice(index);
			tracks = end.concat(begin);
		}
		playlist = tracks;
		//calling setPlaylist method to play the defined tracks
		playlistService.setPlaylist(playlist);
		//playlistService.setCurrentTrack(track);
		playlistService.publish('play');
		//switch to the playing view
		$location.path(Prefix + "playing");
	};

	$scope.showArtists = function (){
		$location.path(Prefix);
	};

	$scope.showPlayer = function (){
		$location.path(Prefix + "playing");
	};

	$scope.showOwncloud = function (){
		$location.path("/");
	};


}]);