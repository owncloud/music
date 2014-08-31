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
	['$scope', '$rootScope', 'playlistService', 'Audio', 'Restangular', 'gettext', 'gettextCatalog', '$timeout',
	function ($scope, $rootScope, playlistService, Audio, Restangular, gettext, gettextCatalog, $timeout) {

	$scope.playing = false;
	$scope.loading = false;
	$scope.player = Audio;
	$scope.currentTrack = null;
	$scope.currentArtist = null;
	$scope.currentAlbum = null;

	$scope.repeat = false;
	$scope.shuffle = false;
	$scope.position = {
		buffer: 0,
		current: 0,
		total: 0
	};

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
		if($scope.player.asset !== undefined) {
			// check if player's constructor has been called,
			// if so, stop() will be available
			$scope.player.stop();
		}
		$scope.setPlay(false);
		$scope.setLoading(true);
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

			$scope.player=Audio.fromURL($scope.getPlayableFileURL($scope.currentTrack));
			$scope.player.asset.source.chunkSize=524288;
			$scope.setLoading(true);

			$scope.player.play();

			$scope.setPlay(true);

			$scope.player.on('buffer', function (percent) {
				$scope.setBufferPercentage(parseInt(percent));
				$scope.$digest();
			});
			$scope.player.on('ready', function () {
				$scope.setLoading(false);
				$scope.$digest();
			});
			$scope.player.on('progress', function (currentTime) {
				$scope.setTime(currentTime/1000, $scope.player.duration/1000);
				$scope.$digest();
			});
			$scope.player.on('end', function() {
				$scope.setPlay(false);
				$scope.$digest();
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

	$scope.setPlay = function(playing) {
		$scope.playing = playing;
	};

	$scope.setLoading = function(loading) {
		$scope.loading = loading;
	};

	$scope.setTime = function(position, duration) {
		$scope.position.current = position;
		$scope.position.total = duration;
	};

	$scope.setBufferPercentage = function(percent) {
		$scope.position.buffer = percent;
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
			$scope.playing = !$scope.playing;
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
			OC.Notification.show(gettextCatalog.getString(gettext('Some not playable tracks were skipped.')));
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
		var offsetX = $event.offsetX || $event.originalEvent.layerX,
			percentage = offsetX / $event.currentTarget.clientWidth;
		if($scope.player.format.formatID !== 'flac') {
			// just in flac seeking is tested
			return;
		}
		$scope.player.seek(percentage * $scope.player.duration);
	};

	playlistService.subscribe('play', function(){
		// fetch track and start playing
		$scope.next();
	});
}]);
