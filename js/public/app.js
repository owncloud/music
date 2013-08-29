
// fix SVGs in IE8
if(!SVGSupport()) {
	var replaceSVGs = function() {
		replaceSVG();
		// call them periodically to keep track of possible changes in the artist view
		setTimeout(replaceSVG, 10000);
	};
	replaceSVG();
	setTimeout(replaceSVG, 1000);
	setTimeout(replaceSVGs, 5000);
}

angular.module('Music', ['restangular']).
	config(
		['$routeProvider', '$interpolateProvider', 'RestangularProvider',
		function ($routeProvider, $interpolateProvider, RestangularProvider) {

	$routeProvider.when('/', {
		templateUrl: 'main.html',
		controller: 'MainController'
	}).otherwise({
		redirectTo: '/'
	});

	// because twig already uses {{}}
	$interpolateProvider.startSymbol('[[');
	$interpolateProvider.endSymbol(']]');

	// configure RESTAngular path
	RestangularProvider.setBaseUrl('api');

}]);
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
		var playlist = _.union(
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
angular.module('Music').controller('PlayerController',
	['$scope', '$routeParams', 'playlistService', 'Audio', 'Artists',
	function ($scope, $routeParams, playlistService, Audio, Artists) {

	$scope.artists = Artists;

	$scope.playing = false;
	$scope.player = Audio;
	$scope.position = 0.0;
	$scope.duration = 0.0;
	$scope.currentTrack = null;
	$scope.currentArtist = null;
	$scope.currentAlbum = null;

	$scope.repeat = false;
	$scope.shuffle = false;

	$scope.$watch('currentTrack', function(newValue, oldValue) {
		$scope.player.stopAll();
		$scope.player.destroySound('ownCloudSound');
		if(newValue !== null) {
			// find artist
			$scope.currentArtist = _.find($scope.artists.$$v, // TODO Why do I have to use $$v?
										function(artist){
											return artist.id === newValue.artist.id;
										});
			// find album
			$scope.currentAlbum = _.find($scope.currentArtist.albums,
										function(album){
											return album.id === newValue.album.id;
										});
			$scope.player.createSound({
				id: 'ownCloudSound',
				url: $scope.currentTrack.files['audio/mpeg'],
				autoLoad: true,
				whileplaying: function() {
					$scope.setTime(this.position/1000, this.duration/1000);
				},
				onstop: function() {
					$scope.setPlay(false);
				},
				onfinish: function() {
					$scope.setPlay(false);
					// determine if already inside of an $apply or $digest
					// see http://stackoverflow.com/a/12859093
					if($scope.$$phase) {
						$scope.next();
					} else {
						$scope.$apply(function(){
							$scope.next();
						});
					}
				},
				onresume: function() {
					$scope.setPlay(true);
				},
				onplay: function() {
					$scope.setPlay(true);
				},
				onpause: function() {
					$scope.setPlay(false);
				},
				volume: 50
			});
			$scope.player.play('ownCloudSound');
		} else {
			$scope.currentArtist = null;
			$scope.currentAlbum = null;
			// switch initial state
			$scope.$parent.started = false;
		}
	});

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
	$scope.setTime = function(position, duration) {
		// determine if already inside of an $apply or $digest
		// see http://stackoverflow.com/a/12859093
		if($scope.$$phase) {
			$scope.duration = duration;
			$scope.position = position;
		} else {
			$scope.$apply(function(){
				$scope.duration = duration;
				$scope.position = position;
			});
		}
	};

	$scope.toggle = function(forcePlay) {
		forcePlay = forcePlay || false;
		if($scope.currentTrack === null) {
			// nothing to do
			return null;
		}
		if(forcePlay) {
			$scope.player.play('ownCloudSound');
		} else {
			$scope.player.togglePause('ownCloudSound');
		}
	};

	$scope.next = function() {
		var track = playlistService.getNextTrack();
		while(track !== null &&
			!('audio/mpeg' in track.files)) {
			track = playlistService.getNextTrack();
		}

		$scope.currentTrack = track;
	};

	playlistService.subscribe('play', function(){
		// switch initial state
		$scope.$parent.started = true;

		// fetch track and start playing
		$scope.next();
	});
}]);
angular.module('Music').controller('PlaylistController',
	['$scope', '$routeParams', 'playlists', function ($scope, $routeParams, playlists) {

	$scope.playlists = playlists;

}]);
angular.module('Music').directive('albumart', function() {
	return function(scope, element, attrs, ctrl) {
		attrs.$observe('albumart',function(){
			element.placeholder(attrs.albumart);
		});
	};
});
angular.module('Music').factory('Artists', ['Restangular', function (Restangular) {
	return Restangular.all('artists').getList({fulltree: true});
}]);

angular.module('Music').factory('Audio', function () {
	soundManager.setup({
		url: OC.linkTo('music', '3rdparty/soundmanager'),
		flashVersion: 9,
		preferFlash: false
	});

	return soundManager;
});

angular.module('Music').factory('playlists', function(){
	return [
		{name: 'test playlist 1', id: 1},
		{name: 'test playlist 2', id: 2},
		{name: 'test playlist 3', id: 3},
		{name: 'test playlist 4', id: 4},
	];
});
angular.module('Music').filter('minify', function() {
	return function(input) {
		if(input !== null && input.length) {
			return input[0];
		}
		return '';
	};
});
angular.module('Music').filter('playTime', function() {
	return function(input) {
		minutes = Math.floor(input/60);
		seconds = Math.floor(input - (minutes * 60));
		return minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
	};
});
angular.module('Music').service('playlistService', ['$rootScope', function($rootScope) {
	var playlist = null;
	var currentTrackId = null;
	var played = [];
	return {
		getCurrentTrack: function() {
			if(currentTrackId !== null && playlist !== null) {
				return playlist[currentTrackId];
			}
			return null;
		},
		getNextTrack: function(repeat) {
			if(playlist === null) {
				return null;
			}
			if(currentTrackId === null ||
				currentTrackId === (playlist.length - 1) && repeat === true) {
				currentTrackId = 0;
			} else {
				currentTrackId++;
			}
			// repeat is disabled and the end of the playlist is reached
			// -> abort
			if(currentTrackId >= playlist.length) {
				currentTrackId = null;
				return null;
			}
			return playlist[currentTrackId];
		},
		setPlaylist: function(pl) {
			playlist = pl;
		},
        publish: function(name, parameters) {
            $rootScope.$emit(name, parameters);
        },
        subscribe: function(name, listener) {
            $rootScope.$on(name, listener);
        }
	};
}]);
