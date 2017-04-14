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
	$scope.seekCursorType = 'default';
	$scope.volume = Cookies.get('oc_music_volume') || 75;  // volume can be 0~100

	$scope.repeat = false;
	$scope.shuffle = false;
	$scope.position = {
		bufferPercent: '0%',
		currentPercent: '0%',
		current: 0,
		total: 0
	};

	// Player events may fire synchronously or asynchronously. Utilize $timeout
	// to always handle them asynchronously to run the handler within digest loop
	// but with no nested digests loop (which causes an exception).
	function onPlayerEvent(event, handler) {
		$scope.player.on(event, function(arg) {
			$timeout(function() {
				handler(arg);
			});
		});
	}

	onPlayerEvent('buffer', function (percent) {
		$scope.setBufferPercentage(percent);
	});
	onPlayerEvent('ready', function () {
		$scope.setLoading(false);
	});
	onPlayerEvent('progress', function (currentTime) {
		$scope.setTime(currentTime/1000, $scope.player.duration/1000);
	});
	onPlayerEvent('end', function() {
		$scope.setPlay(false);
		$scope.next();
	});
	onPlayerEvent('duration', function(msecs) {
		$scope.setTime($scope.position.current, $scope.player.duration/1000);
	});

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
			if($scope.player.canPlayMIME(mimeType)) {
				return {
					'type': mimeType,
					'url': track.files[mimeType] + '?requesttoken=' + encodeURIComponent(OC.requestToken)
				};
			}
		}

		return null;
	};

	function setCurrentTrack(playlistEntry) {
		var track = playlistEntry ? playlistEntry.track : null;
		$scope.currentTrack = track;
		$scope.player.stop();
		$scope.setPlay(false);
		if(track !== null) {
			// switch initial state
			$rootScope.started = true;
			// find artist
			$scope.currentArtist = _.find($scope.artists,
										function(artist){
											return artist.id === track.albumArtistId;
										});
			// find album
			$scope.currentAlbum = _.find($scope.currentArtist.albums,
										function(album){
											return album.id === track.albumId;
										});

			$scope.player.fromURL($scope.getPlayableFileURL(track));
			$scope.setLoading(true);
			$scope.seekCursorType = $scope.player.seekingSupported() ? 'pointer' : 'default';

			$scope.player.play();

			$scope.setPlay(true);

		} else {
			$scope.currentArtist = null;
			$scope.currentAlbum = null;
			// switch initial state
			$rootScope.started = false;
		}
	}

	$scope.setPlay = function(playing) {
		$scope.playing = playing;
	};

	$scope.setLoading = function(loading) {
		$scope.loading = loading;
	};

	$scope.$watch('volume', function(newValue, oldValue) {
		$scope.player.setVolume(newValue);
		Cookies.set('oc_music_volume', newValue, { expires: 3650 });
	});

	$scope.setTime = function(position, duration) {
		$scope.position.current = position;
		$scope.position.total = duration;
		$scope.position.currentPercent = Math.round(position/duration*100) + '%';
	};

	$scope.setBufferPercentage = function(percent) {
		$scope.position.bufferPercent = Math.round(percent) + '%';
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
		var entry = playlistService.jumpToNextTrack($scope.repeat, $scope.shuffle),
			tracksSkipped = false;

		// get the next track as long as the current one contains no playable
		// audio mimetype
		while(entry !== null && !$scope.getPlayableFileURL(entry.track)) {
			tracksSkipped = true;
			entry = playlistService.jumpToNextTrack($scope.repeat, $scope.shuffle);
		}
		if(tracksSkipped) {
			OC.Notification.showTemporary(gettextCatalog.getString(gettext('Some not playable tracks were skipped.')));
		}
		setCurrentTrack(entry);
	};

	$scope.prev = function() {
		var track = playlistService.jumpToPrevTrack();
		if(track !== null) {
			setCurrentTrack(track);
		}
	};

	$scope.seek = function($event) {
		var offsetX = $event.offsetX || $event.originalEvent.layerX,
			percentage = offsetX / $event.currentTarget.clientWidth;
		$scope.player.seek(percentage);
	};

	playlistService.subscribe('play', function(){
		// fetch track and start playing
		$scope.next();
	});

	$scope.scrollToCurrentTrack = function() {
		if ($scope.currentTrack) {
			$rootScope.$emit('scrollToTrack', $scope.currentTrack.id);
		}
	};
}]);
