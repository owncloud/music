
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
	['$rootScope', '$scope', '$routeParams', 'Artists', 'playlistService',
	function ($rootScope, $scope, $routeParams, Artists, playlistService) {

	// will be invoked by the artist factory
	$rootScope.$on('artistsLoaded', function() {
		$scope.loading = false;
	});

	$scope.letterAvailable = {
		'A': false,
		'B': false,
		'C': false,
		'D': false,
		'E': false,
		'F': false,
		'G': false,
		'H': false,
		'I': false,
		'J': false,
		'K': false,
		'L': false,
		'M': false,
		'N': false,
		'O': false,
		'P': false,
		'Q': false,
		'R': false,
		'S': false,
		'T': false,
		'U': false,
		'V': false,
		'W': false,
		'X': false,
		'Y': false,
		'Z': false
	};

	$scope.anchorArtists = [];

	$scope.loading = true;
	$scope.artists = Artists;

	$scope.$watch('artists', function(artists) {
		if(artists) {
			_.each(artists, function(artist) {
				var letter = artist.name.substr(0,1).toUpperCase();
				if($scope.letterAvailable.hasOwnProperty(letter) === true) {
					if($scope.letterAvailable[letter] === false) {
						$scope.anchorArtists.push(artist.name);
					}
					$scope.letterAvailable[letter] = true;
				}
			});
		}
	});

	$scope.playTrack = function(track) {
		var artist = _.find($scope.artists.$$v, // TODO Why do I have to use $$v?
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
}]);
angular.module('Music').controller('PlayerController',
	['$scope', '$routeParams', 'playlistService', 'Audio', 'Artists', 'Restangular',
	function ($scope, $routeParams, playlistService, Audio, Artists, Restangular) {

	$scope.artists = Artists;

	$scope.playing = false;
	$scope.loading = false;
	$scope.player = Audio;
	$scope.position = 0.0;
	$scope.duration = 0.0;
	$scope.currentTrack = null;
	$scope.currentArtist = null;
	$scope.currentAlbum = null;

	$scope.repeat = false;
	$scope.shuffle = false;

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
			if(Audio.canPlayMIME(mimeType)) {
				return track.files[mimeType];
			}
		}

		return null;
	};

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
				url: $scope.getPlayableFileURL($scope.currentTrack),
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
				onload: function(success) {
					if(!success) {
						$scope.setPlay(false);
						Restangular.all('log').post({message: JSON.stringify($scope.currentTrack)});
						// determine if already inside of an $apply or $digest
						// see http://stackoverflow.com/a/12859093
						if($scope.$$phase) {
							$scope.next();
						} else {
							$scope.$apply(function(){
								$scope.next();
							});
						}
					}
				},
				onbufferchange: function() {
					$scope.setLoading(this.isBuffering);
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
	$scope.setLoading = function(loading) {
		// determine if already inside of an $apply or $digest
		// see http://stackoverflow.com/a/12859093
		if($scope.$$phase) {
			$scope.loading = loading;
		} else {
			$scope.$apply(function(){
				$scope.loading = loading;
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
		var track = playlistService.getNextTrack($scope.repeat, $scope.shuffle);

		// get the next track as long as the current one contains no playable
		// audio mimetype
		while(track !== null && !$scope.getPlayableFileURL(track)) {
			track = playlistService.getNextTrack($scope.repeat, $scope.shuffle);
		}
		$scope.currentTrack = track;
	};

	$scope.prev = function() {
		var track = playlistService.getPrevTrack();
		if(track !== null) {
			$scope.currentTrack = track;
		}
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
angular.module('Music').directive('alphabet-navigation', function($window) {
	return function(scope, element, attrs, ctrl) {
		$($window).resize(function() {
			console.warn('muh');
		});

		element.resize(function(){
		});
	};
});
angular.module('Music').directive('scrollTo', ['$window', function($window) {
	return function(scope, element, attrs, ctrl) {
		var scrollToElement = function(id) {
			if(!id) {
				// scroll to top if nothing is provided
				$window.scrollTo(0, 0);
			}

			var el = $window.document.getElementById(id);

			if(el) {
				el.scrollIntoView({behavior: "smooth"});
			}
		};

		element.bind('click', function() {
			scrollToElement(attrs.scrollTo);
		});
	};
}]);
angular.module('Music').factory('Artists', ['Restangular', '$rootScope', function (Restangular, $rootScope) {
	return Restangular.all('artists').getList({fulltree: true}).then(
		function(result){
			$rootScope.$emit('artistsLoaded');
			return result;
		});
}]);

angular.module('Music').factory('Audio', function () {
	soundManager.setup({
		url: OC.linkTo('music', '3rdparty/soundmanager'),
		flashVersion: 8,
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
		getPrevTrack: function() {
			if(played.length > 0) {
				currentTrackId = played.pop();
				return playlist[currentTrackId];
			}
			return null;
		},
		getNextTrack: function(repeat, shuffle) {
			if(playlist === null) {
				return null;
			}
			if(currentTrackId !== null) {
				// add previous track id to the played list
				played.push(currentTrackId);
			}
			if(shuffle === true) {
				if(playlist.length === played.length) {
					if(repeat === true) {
						played = [];
					} else {
						currentTrackId = null;
						return null;
					}
				}
				// generate a list with all integers between 0 and playlist.length
				var all = [];
				for(var i = 0; i < playlist.length; i++) {
					all.push(i);
				}
				// remove the already played track ids
				all = _.difference(all, played);
				// determine a random integer out of this set
				currentTrackId = all[Math.round(Math.random() * (all.length - 1))];
			} else {
				if(currentTrackId === null ||
					currentTrackId === (playlist.length - 1) && repeat === true) {
					currentTrackId = 0;
				} else {
					currentTrackId++;
				}
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
			currentTrackId = null;
			player = [];
		},
        publish: function(name, parameters) {
            $rootScope.$emit(name, parameters);
        },
        subscribe: function(name, listener) {
            $rootScope.$on(name, listener);
        }
	};
}]);
