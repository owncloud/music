/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @copyright Morris Jobke  2014
 */

angular.module('Music').controller('OverviewController',
	['$scope', '$rootScope', 'playlistService', 'Restangular', '$route', '$window',
	function ($scope, $rootScope, playlistService, Restangular, $route, $window) {

		// Prevent controller reload when the URL is updated with window.location.hash.
		// See http://stackoverflow.com/a/12429133/2104976
		var lastRoute = $route.current;
		$scope.$on('$locationChangeSuccess', function(event) {
			$route.current = lastRoute;
		});

		$scope.playTrack = function(track) {
			// update URL hash
			window.location.hash = '#/track/' + track.id;

			var artist = _.find($scope.$parent.artists,
				function(artist) {
					return artist.id === track.albumArtistId;
				}),
				album = _.find(artist.albums,
				function(album) {
					return album.id === track.albumId;
				}),
				tracks = _.sortBy(album.tracks,
					function(track) {
						return track.number;
					}
				);
			// determine index of clicked track
			var index = -1;
			for (var i = 0; i < tracks.length; i++) {
				if(tracks[i].id == track.id) {
					index = i;
					break;
				}
			}
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
			// update URL hash
			window.location.hash = '#/album/' + album.id;

			var tracks = _.sortBy(album.tracks,
					function(track) {
						return track.number;
					}
				);
			playlistService.setPlaylist(tracks);
			playlistService.publish('play');
		};

		$scope.playArtist = function(artist) {
			// update URL hash
			window.location.hash = '#/artist/' + artist.id;

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

		$scope.playFile = function (fileid) {
			if (fileid) {
				Restangular.one('file', fileid).get()
					.then(function(result){
						playlistService.setPlaylist([result]);
						playlistService.publish('play');
						$scope.scrollToItem('album-' + result.albumId);
					});
			}
		};

		// emited on end of playlist by playerController
		playlistService.subscribe('playlistEnded', function(){
			// update URL hash
			window.location.hash = '#/';
		});

		$scope.scrollToItem = function(itemId) {
			var container = angular.element(document.getElementById('app-content'));
			var element = angular.element(document.getElementById(itemId));
			var controls = document.getElementById('controls');
			if(container && controls && element) {
				container.scrollToElement(element, controls.offsetHeight, 500);
			}
		};

		$rootScope.$on('requestScrollToAlbum', function(event, albumId) {
			$scope.scrollToItem('album-' + albumId);
		});

		$rootScope.$on('artistsLoaded', function () {
			$scope.initializePlayerStateFromURL();
		});

		$scope.initializePlayerStateFromURL = function() {
			var hashParts = window.location.hash.substr(1).split('/');
			if (!hashParts[0] && hashParts[1] && hashParts[2]) {
				type = hashParts[1];
				var id = hashParts[2];

				if (type == 'file') {
					// trigger play
					$scope.playFile(id);
				} else if (type == 'artist') {
					// search for the artist by id
					object = _.find($scope.$parent.artists, function(artist) {
						return artist.id == id;
					});
					// trigger play
					$scope.playArtist(object);
					$scope.scrollToItem('artist-' + object.id);
				} else {
					var albums = _.flatten(_.pluck($scope.$parent.artists, 'albums'));
					if (type == 'album') {
						// search for the album by id
						object = _.find(albums, function(album) {
							return album.id == id;
						});
						// trigger play
						$scope.playAlbum(object);
						$scope.scrollToItem('album-' + object.id);
					} else if (type == 'track') {
						var tracks = _.flatten(_.pluck(albums, 'tracks'));
						// search for the track by id
						object = _.find(tracks, function(track) {
							return track.id == id;
						});
						// trigger play
						$scope.playTrack(object);
						$scope.scrollToItem('album-' + object.albumId);
					}
				}
			}
		};
}]);
