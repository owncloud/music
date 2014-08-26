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


angular.module('Music').controller('PlayerController',
	['$scope', '$rootScope', 'playlistService', 'Audio', 'Restangular', 'gettext', 'gettextCatalog', '$filter', '$timeout',
	function ($scope, $rootScope, playlistService, Audio, Restangular, gettext, gettextCatalog, $filter, $timeout) {

	$scope.playing = false;
	$scope.buffering = false;
	$scope.player = Audio;
	$scope.position = 0.0;
	$scope.duration = 0.0;
	$scope.currentTrack = null;
	$scope.currentArtist = null;
	$scope.currentAlbum = null;

	$scope.bufferPercent = 0;
	$scope.volume = 80;  // volume can be 0~100

	$scope.repeat = false;
	$scope.shuffle = false;

	$scope.$playPosition = $('.play-position');
	$scope.$bufferBar = $('.buffer-bar');
	$scope.$playBar = $('.play-bar');

	// display a play icon in the title if a song is playing
	$scope.$watch('playing', function(newValue) {
		var title = $('title').html().trim();
		if(newValue) {
			$('title').html('▶ ' + title);
		} else {
			if(title.substr(0, 1) === '▶') {
				$('title').html(title.substr(1).trim());
			}
		}
	});

	$scope.getPlayableFileURL = function (track) {
		for(var mimeType in track.files) {
			if(mimeType=='audio/flac' || mimeType=='audio/mpeg') {
				return track.files[mimeType];
			}
		}

		return null;
	};

	$scope.$watch('currentTrack', function(newValue, oldValue) {
		playlistService.publish('playing', newValue);
		if($scope.player.asset != undefined) {
			// check if player's constructor has been called,
			// if so, stop() will be available
			$scope.player.stop();
		}
		$scope.setPlay(false);
		// Reset position
		$scope.position=0.0;
		if(newValue !== null) {
			// switch initial state
			$rootScope.started = true;
			// find artist
			$scope.currentArtist = _.find($scope.artists,
										function(artist){
											return artist.id === newValue.artistId;
										});
			// find album
			$scope.currentAlbum = _.find($scope.currentArtist.albums,
										function(album){
											return album.id === newValue.albumId;
										});

			$scope.player=AV.Player.fromURL($scope.getPlayableFileURL($scope.currentTrack));
			$scope.player.asset.source.chunkSize=1048576;
			$scope.setBuffering(true);

			$scope.player.play();

			$scope.setPlay(true);
			$scope.player.on("buffer", function (percent) {
				// update percent
				if($scope.$$phase) {
					$scope.bufferPercent = percent;
				} else {
					$scope.$apply(function(){
						$scope.bufferPercent = percent;
					});
				}
				if (percent == 100) {
					$scope.setBuffering(false);
				}
			})
			$scope.player.on("progress", function (currentTime) {
				var position = currentTime/1000;
				if($scope.$$phase) {
					$scope.position = position;
				} else {
					$scope.$apply(function(){
						$scope.position = position;
					});
				}
			});
			$scope.player.on('end', function() {
				$scope.setPlay(false);
				if($scope.$$phase) {
					$scope.next();
				} else {
					$scope.$apply(function(){
						$scope.next();
					});
				}
			});
		} else {
			$scope.currentArtist = null;
			$scope.currentAlbum = null;
			// switch initial state
			$rootScope.started = false;
			playlistService.publish('playlistEnded');
		}
	}, true);

	$scope.$watch(function () {
		return $scope.player.duration;
	}, function (newValue, oldValue) {
		var duration = newValue/1000;
		if($scope.$$phase) {
			$scope.duration = duration;
		} else {
			$scope.$apply(function(){
				$scope.duration = duration;
			});
		}
	})

	// only call from external script
	$scope.setPlay = function(playing) {
		// determine if already inside of an $apply or $digest
		// see http://stackoverflow.com/a/12859093
		if($scope.$$phase) {
			$scope.playing = playing;
		} else {
			$scope.$apply(function(){
				$scope.playing = playing;
			});
		}
	};

	// only call from external script
	$scope.setBuffering = function(buffering) {
		// determine if already inside of an $apply or $digest
		// see http://stackoverflow.com/a/12859093
		if($scope.$$phase) {
			$scope.buffering = buffering;
		} else {
			$scope.$apply(function(){
				$scope.buffering = buffering;
			});
		}
	};

	$scope.$watch("volume", function (newValue, oldValue) {
		var volume = parseInt(newValue);
		$scope.player.volume = volume;
	});

	// only call from external script
	$scope.setTime = function(position, duration) {
		$scope.$playPosition.text($filter('playTime')(position) + ' / ' + $filter('playTime')(duration));
		$scope.$playBar.css('width', (position / duration * 100) + '%');
	};

	$scope.setBuffer = function(position, duration) {
		$scope.$bufferBar.css('width', (position / duration * 100) + '%');
	};

	$scope.toggle = function(forcePlay) {
		forcePlay = forcePlay || false;
		if($scope.currentTrack === null) {
			// nothing to do
			return null;
		}
		if(forcePlay) {
			$scope.player.play();
			$scope.setPlay(true);
		} else {
			$scope.player.togglePlayback();
			if($scope.playing) {
				$scope.playing=false;
			} else {
				$scope.playing=true;
			}
		}
	};

	$scope.next = function() {
		var track = playlistService.getNextTrack($scope.repeat, $scope.shuffle),
			tracksSkipped = false;

		// get the next track as long as the current one contains no playable
		// audio mimetype
		while(track !== null && !$scope.getPlayableFileURL(track)) {
			tracksSkipped = true;
			track = playlistService.getNextTrack($scope.repeat, $scope.shuffle);
		}
		if(tracksSkipped === true) {
			OC.Notification.show(gettextCatalog.getString(gettext("Some not playable tracks were skipped.")));
			$timeout(OC.Notification.hide, 10000);
		}
		$scope.currentTrack = track;
	};

	$scope.prev = function() {
		var track = playlistService.getPrevTrack();
		if(track !== null) {
			$scope.currentTrack = track;
		}
	};

	$scope.seek = function($event) {
		var sound = $scope.player.sounds.ownCloudSound,
			offsetX = $event.offsetX || $event.originalEvent.layerX;
		sound.setPosition(offsetX * sound.durationEstimate / $event.currentTarget.clientWidth);
	};

	playlistService.subscribe('play', function(){
		// fetch track and start playing
		$scope.next();
	});
}]);
