
// fix SVGs in IE because the scaling is a real PITA
// https://github.com/owncloud/music/issues/126
if($('html').hasClass('ie')) {
	var replaceSVGs = function() {
		replaceSVG();
		// call them periodically to keep track of possible changes in the artist view
		setTimeout(replaceSVG, 10000);
	};
	replaceSVG();
	setTimeout(replaceSVG, 1000);
	setTimeout(replaceSVGs, 5000);
}

angular.module('Music', ['restangular', 'gettext']).
	config(
		['$routeProvider', '$interpolateProvider', 'RestangularProvider',
		function ($routeProvider, $interpolateProvider, RestangularProvider) {

	$routeProvider.when('/:type/:id', {
		templateUrl: 'main.html'
	}).when('/', {
		templateUrl: 'main.html'
	}).otherwise({
		redirectTo: '/'
	});

	// configure RESTAngular path
	RestangularProvider.setBaseUrl('api');
}]);
angular.module('Music').controller('MainController',
	['$rootScope', '$scope', '$routeParams', '$location', 'Artists', 'playlistService', 'gettextCatalog',
	function ($rootScope, $scope, $routeParams, $location, Artists, playlistService, gettextCatalog) {

	// retrieve language from backend - is set in ng-app HTML element
	gettextCatalog.currentLanguage = $rootScope.lang;

	$scope.loading = true;

	// will be invoked by the artist factory
	$rootScope.$on('artistsLoaded', function() {
		$scope.loading = false;
	});

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

	Artists.then(function(artists){
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

		$scope.handlePlayRequest();
	});
	
	$rootScope.$on('$routeChangeSuccess', function() {
		$scope.handlePlayRequest();
	});
	
	$scope.play = function (type, object) {
		$scope.playRequest = {
			type: type,
			object: object
		};
		$location.path('/' + type + '/' + object.id);
	};
	
	$scope.handlePlayRequest = function() {
		if (!$scope.artists) return;
		
		var type, object;
		
		if ($scope.playRequest) {
			type = $scope.playRequest.type;
			object = $scope.playRequest.object;
			$scope.playRequest = null;
		} else if ($routeParams.type) {
			type = $routeParams.type;
			if (type == 'artist') {
				object = _.find($scope.artists, function(artist) {
					return artist.id == $routeParams.id;
				});
			} else {
				var albums = _.flatten(_.pluck($scope.artists, 'albums'));
				if (type == 'album') {
					object = _.find(albums, function(album) {
						return album.id == $routeParams.id;
					});
				} else if (type == 'track') {
					var tracks = _.flatten(_.pluck(albums, 'tracks'));
					object = _.find(tracks, function(track) {
						return track.id == $routeParams.id;
					});
				}
			}
		}
		
		if (type && object) {
			if (type == 'artist') $scope.playArtist(object);
			else if (type == 'album') $scope.playAlbum(object);
			else if (type == 'track') $scope.playTrack(object);
		}
	};

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
}]);
angular.module('Music').controller('PlayerController',
	['$scope', '$routeParams', '$rootScope', 'playlistService', 'Audio', 'Artists', 'Restangular', 'gettext',
	function ($scope, $routeParams, $rootScope, playlistService, Audio, Artists, Restangular, gettext) {

	$scope.playing = false;
	$scope.buffering = false;
	$scope.player = Audio;
	$scope.position = 0.0;
	$scope.duration = 0.0;
	$scope.currentTrack = null;
	$scope.currentArtist = null;
	$scope.currentAlbum = null;

	$scope.repeat = false;
	$scope.shuffle = false;

	// will be invoked by the audio factory
	$rootScope.$on('SoundManagerReady', function() {
		if ($routeParams.type == 'file') $scope.playFile($routeParams.id);
		if($scope.$parent.started) {
			// invoke play after the flash gets unblocked
			$scope.$apply(function(){
				$scope.next();
			});
		}
	});

	$rootScope.$on('$routeChangeSuccess', function() {
		if ($routeParams.type == 'file') $scope.playFile($routeParams.id);
	});

	$scope.playFile = function (fileid) {
		if (fileid) {
			Restangular.one('file', fileid).get()
				.then(function(result){
					playlistService.setPlaylist([result]);
					playlistService.publish('play');
				});
		}
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
		var isChrome = (navigator && navigator.userAgent &&
			navigator.userAgent.indexOf('Chrome') !== -1) ?
				true : false;
		for(var mimeType in track.files) {
			if(mimeType === 'audio/ogg' && isChrome) {
				// TODO inject this
				OC.Notification.showHtml(gettext(
					'Chrome is only able to playback MP3 files - see <a href="https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files">wiki</a>'
				));
			}
			if(Audio.canPlayMIME(mimeType)) {
				return track.files[mimeType];
			}
		}

		return null;
	};

	$scope.$watch('currentTrack', function(newValue, oldValue) {
		playlistService.publish('playing', newValue);
		$scope.player.stopAll();
		$scope.player.destroySound('ownCloudSound');
		if(newValue !== null) {
			// switch initial state
			$scope.$parent.started = true;
			// find artist
			$scope.currentArtist = _.find($scope.artists,
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
					$scope.setBuffering(this.isBuffering);
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
		var setAlbumart = function() {
			if(attrs.cover) {
				// remove placeholder stuff
				element.html('');
				element.css('background-color', '');
				// add background image
				element.css('filter', "progid:DXImageTransform.Microsoft.AlphaImageLoader(src='" + attrs.cover + "', sizingMethod='scale')");
				element.css('-ms-filter', "progid:DXImageTransform.Microsoft.AlphaImageLoader(src='" + attrs.cover + "', sizingMethod='scale')");
				element.css('background-image', 'url(' + attrs.cover + ')');
			} else {
				if(attrs.albumart) {
					// remove background image
					element.css('-ms-filter', '');
					element.css('background-image', '');
					// add placeholder stuff
					element.imageplaceholder(attrs.albumart);
				}
			}
		};

		attrs.$observe('albumart', setAlbumart);
		attrs.$observe('cover', setAlbumart);
	};
});

angular.module('Music').directive('resize', ['$window', '$rootScope', function($window, $rootScope) {
	return function(scope, element, attrs, ctrl) {
		var resizeNavigation = function() {
			var height = $window.innerHeight;
			// top and button padding of 5px each
			height = height - 10;
			// remove playerbar height if started
			if(scope.started) {
				height = height - 65;
			}
			// remove header height
			height = height - 45;
			element.css('height', height);
			element.css('line-height', height/26 + 'px');
		};

		// trigger resize on window resize
		$($window).resize(function() {
			resizeNavigation();
		});

		// trigger resize on player status changes
		$rootScope.$watch('started', function() {
			resizeNavigation();
		});

		resizeNavigation();
	};
}]);
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

angular.module('Music').factory('Audio', ['$rootScope', function ($rootScope) {
	var isChrome = (navigator && navigator.userAgent &&
		navigator.userAgent.indexOf('Chrome') !== -1) ?
			true : false;

	soundManager.setup({
		url: OC.linkTo('music', '3rdparty/soundmanager'),
		flashVersion: 8,
		// this fixes a bug with HTML5 playback in Chrome - Chrome has to use flash
		// Chrome stalls sometimes for several seconds after changing a track
		// drawback: OGG files can't played in Chrome
		// https://code.google.com/p/chromium/issues/detail?id=111281
		useHTML5Audio: isChrome ? false : true,
		preferFlash: isChrome ? true : false,
		useFlashBlock: true,
		flashPollingInterval: 200,
		html5PollingInterval: 200,
		onready: function() {
			$rootScope.$emit('SoundManagerReady');
		}
	});

	return soundManager;
}]);

angular.module('Music').factory('playlists', function(){
	return [
		{name: 'test playlist 1', id: 1},
		{name: 'test playlist 2', id: 2},
		{name: 'test playlist 3', id: 3},
		{name: 'test playlist 4', id: 4}
	];
});

angular.module('Music').filter('playTime', function() {
	return function(input) {
		var minutes = Math.floor(input/60),
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
angular.module("Music").run(['gettextCatalog', function (gettextCatalog) {
/* jshint -W100 */
    gettextCatalog.setStrings('ach', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"","Loading ...":"","Music":"","Next":"","Nothing in here. Upload your music!":"","Pause":"","Play":"","Previous":"","Repeat":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('ady', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"","Loading ...":"","Music":"","Next":"","Nothing in here. Upload your music!":"","Pause":"","Play":"","Previous":"","Repeat":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('af', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"","Loading ...":"","Music":"","Next":"","Nothing in here. Upload your music!":"","Pause":"","Play":"","Previous":"","Repeat":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('af_ZA', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"","Loading ...":"","Music":"","Next":"","Nothing in here. Upload your music!":"","Pause":"","Play":"","Previous":"","Repeat":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('ak', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"","Loading ...":"","Music":"","Next":"","Nothing in here. Upload your music!":"","Pause":"","Play":"","Previous":"","Repeat":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('ar', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"إلغاء","Loading ...":"تحميل ...","Music":"الموسيقى","Next":"التالي","Nothing in here. Upload your music!":"لا يوجد شيء هنا. إرفع بعض الموسيقى!","Pause":"تجميد","Play":"إلعب","Previous":"السابق","Repeat":"إعادة","Show all {{ trackcount }} songs ...":["","","","","",""],"Show less ...":"اعرض اقل ...","Shuffle":"عشوائي","Unknown album":"البوم غير معروف","Unknown artist":"فنان غير معروف"});
    gettextCatalog.setStrings('az', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"","Loading ...":"","Music":"","Next":"","Nothing in here. Upload your music!":"","Pause":"","Play":"","Previous":"","Repeat":"","Show all {{ trackcount }} songs ...":"","Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('be', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"","Loading ...":"","Music":"","Next":"","Nothing in here. Upload your music!":"","Pause":"","Play":"","Previous":"","Repeat":"","Show all {{ trackcount }} songs ...":["","","",""],"Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('bg_BG', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Изтриване","Loading ...":"Зареждане ...","Music":"Музика","Next":"Следваща","Nothing in here. Upload your music!":"Няма нищо тук. Качете си музиката!","Pause":"Пауза","Play":"Пусни","Previous":"Предишна","Repeat":"Повтори","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"Покажи по-малко ...","Shuffle":"Разбъркване","Unknown album":"Непознат албум","Unknown artist":"Непознат изпълнител"});
    gettextCatalog.setStrings('bn_BD', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"মুছে","Loading ...":"","Music":"গানবাজনা","Next":"পরবর্তী","Nothing in here. Upload your music!":"","Pause":"বিরতি","Play":"বাজাও","Previous":"পূর্ববর্তী","Repeat":"পূনঃসংঘটন","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('bs', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"","Loading ...":"","Music":"","Next":"Sljedeći","Nothing in here. Upload your music!":"","Pause":"","Play":"","Previous":"","Repeat":"","Show all {{ trackcount }} songs ...":["","",""],"Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('ca', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Esborra","Loading ...":"Carregant...","Music":"Música","Next":"Següent","Nothing in here. Upload your music!":"Res per aquí. Pugeu la vostra música!","Pause":"Pausa","Play":"Reprodueix","Previous":"Anterior","Repeat":"Repeteix","Show all {{ trackcount }} songs ...":["Mostra totes les {{ trackcount }} peces...","Mostra totes les {{ trackcount }} peces..."],"Show less ...":"Mostra'n menys...","Shuffle":"Aleatori","Unknown album":"Àlbum desconegut","Unknown artist":"Artista desconegut"});
    gettextCatalog.setStrings('cs_CZ', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Smazat","Loading ...":"Načítám ...","Music":"Hudba","Next":"Následující","Nothing in here. Upload your music!":"Zde nic není. Nahrajte vaši hudbu!","Pause":"Pozastavit","Play":"Přehrát","Previous":"Předchozí","Repeat":"Opakovat","Show all {{ trackcount }} songs ...":["Zobrazit {{ trackcount }} písničku ...","Zobrazit {{ trackcount }} písničky ...","Zobrazit {{ trackcount }} písniček ..."],"Show less ...":"Zobrazit méně ...","Shuffle":"Promíchat","Unknown album":"Neznámé album","Unknown artist":"Neznámý umělec"});
    gettextCatalog.setStrings('cy_GB', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Dileu","Loading ...":"","Music":"Cerddoriaeth","Next":"Nesaf","Nothing in here. Upload your music!":"","Pause":"Seibio","Play":"Chwarae","Previous":"Blaenorol","Repeat":"Ailadrodd","Show all {{ trackcount }} songs ...":["","","",""],"Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('da', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Slet","Loading ...":"Indlæser...","Music":"Musik","Next":"Næste","Nothing in here. Upload your music!":"Her er tomt. Upload din musik!","Pause":"Pause","Play":"Afspil","Previous":"Forrige","Repeat":"Gentag","Show all {{ trackcount }} songs ...":["Vis alle {{ trackcount }} sange ...","Vis alle {{ trackcount }} sange ..."],"Show less ...":"Vis færre ...","Shuffle":"Bland","Unknown album":"Ukendt album","Unknown artist":"Ukendt artist"});
    gettextCatalog.setStrings('de', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Löschen","Loading ...":"Lade ...","Music":"Musik","Next":"Weiter","Nothing in here. Upload your music!":"Alles leer. Lade Deine Musik hoch!","Pause":"Anhalten","Play":"Abspielen","Previous":"Zurück","Repeat":"Wiederholen","Show all {{ trackcount }} songs ...":["{{ trackcount }} Lied anzeigen  ...","Alle {{ trackcount }} Lieder anzeigen  ..."],"Show less ...":"Weniger anzeigen ...","Shuffle":"Zufallswiedergabe","Unknown album":"Unbekanntes Album","Unknown artist":"Unbekannter Künstler"});
    gettextCatalog.setStrings('de_AT', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Löschen","Loading ...":"Lade ...","Music":"Musik","Next":"Nächstes","Nothing in here. Upload your music!":"Alles leer. Lade deine Musik hoch!","Pause":"Pause","Play":"Abspielen","Previous":"Vorheriges","Repeat":"Wiederholen","Show all {{ trackcount }} songs ...":["{{ trackcount }} Lied anzeigen ...","Alle {{ trackcount }} Lieder anzeigen ..."],"Show less ...":"Weniger anzeigen ...","Shuffle":"Zufallswiedergabe","Unknown album":"Unbekanntes Album","Unknown artist":"Unbekannter Künstler"});
    gettextCatalog.setStrings('de_CH', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Löschen","Loading ...":"","Music":"Musik","Next":"Weiter","Nothing in here. Upload your music!":"","Pause":"Anhalten","Play":"Abspielen","Previous":"Vorheriges","Repeat":"Wiederholen","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('de_DE', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Löschen","Loading ...":"Lade …","Music":"Musik","Next":"Weiter","Nothing in here. Upload your music!":"Alles leer. Laden Sie Ihre Musik hoch!","Pause":"Anhalten","Play":"Abspielen","Previous":"Zurück","Repeat":"Wiederholen","Show all {{ trackcount }} songs ...":["{{ trackcount }} Lied anzeigen  ...","Alle {{ trackcount }} Lieder anzeigen  ..."],"Show less ...":"Weniger anzeigen ...","Shuffle":"Zufallswiedergabe","Unknown album":"Unbekanntes Album","Unknown artist":"Unbekannter Künstler"});
    gettextCatalog.setStrings('el', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Διαγραφή","Loading ...":"Φόρτωση ...","Music":"Μουσική","Next":"Επόμενο","Nothing in here. Upload your music!":"Δεν υπάρχει τίποτα εδώ. Μεταφορτώστε την μουσική σας!","Pause":"Παύση","Play":"Αναπαραγωγή","Previous":"Προηγούμενο","Repeat":"Επανάληψη","Show all {{ trackcount }} songs ...":["Εμφάνιση του τραγουδιού","Εμφάνιση όλων των {{ trackcount }} τραγουδιών ..."],"Show less ...":"Προβολή λιγότερων...","Shuffle":"Τυχαία αναπαραγωγή","Unknown album":"Άγνωστο άλμπουμ","Unknown artist":"Άγνωστος καλλιτέχνης"});
    gettextCatalog.setStrings('en@pirate', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"","Loading ...":"","Music":"Music","Next":"","Nothing in here. Upload your music!":"","Pause":"Pause","Play":"","Previous":"","Repeat":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('en_GB', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Delete","Loading ...":"Loading...","Music":"Music","Next":"Next","Nothing in here. Upload your music!":"Nothing in here. Upload your music!","Pause":"Pause","Play":"Play","Previous":"Previous","Repeat":"Repeat","Show all {{ trackcount }} songs ...":["Show all {{ trackcount }} songs...","Show all {{ trackcount }} songs..."],"Show less ...":"Show less...","Shuffle":"Shuffle","Unknown album":"Unknown album","Unknown artist":"Unknown artist"});
    gettextCatalog.setStrings('eo', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Forigi","Loading ...":"Ŝargante...","Music":"Muziko","Next":"Jena","Nothing in here. Upload your music!":"Nenio estas ĉi tie. Alŝutu vian muzikon!","Pause":"Paŭzi...","Play":"Ludi","Previous":"Maljena","Repeat":"Ripeti","Show all {{ trackcount }} songs ...":["Montri ĉiujn {{ trackcount }} kantaĵojn...","Montri ĉiujn {{ trackcount }} kantaĵojn..."],"Show less ...":"Montri malpli...","Shuffle":"Miksi","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('es', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Eliminar","Loading ...":"Cargando...","Music":"Música","Next":"Siguiente","Nothing in here. Upload your music!":"Aquí no hay nada. ¡Sube tu música!","Pause":"Pausa","Play":"Reproducir","Previous":"Anterior","Repeat":"Repetir","Show all {{ trackcount }} songs ...":["Mostrar todas las {{ trackcount}} canciones...","Mostrar todas las {{ trackcount }} canciones..."],"Show less ...":"Mostrar menos...","Shuffle":"Mezclar","Unknown album":"Álbum desconocido","Unknown artist":"Artista desconocido"});
    gettextCatalog.setStrings('es_AR', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Borrar","Loading ...":"Cargando...","Music":"Música","Next":"Siguiente","Nothing in here. Upload your music!":"No hay nada aquí. ¡Suba su música!","Pause":"Pausar","Play":"Reproducir","Previous":"Previo","Repeat":"Repetir","Show all {{ trackcount }} songs ...":["Mostrar la única canción...","Mostrar las {{ trackcount }} canciones..."],"Show less ...":"Mostrar menos...","Shuffle":"Aleatorio","Unknown album":"Album desconocido","Unknown artist":"Artista desconocido"});
    gettextCatalog.setStrings('es_CL', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"","Loading ...":"","Music":"","Next":"","Nothing in here. Upload your music!":"","Pause":"","Play":"","Previous":"","Repeat":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('es_MX', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Eliminar","Loading ...":"Cargando ...","Music":"Música","Next":"Siguiente","Nothing in here. Upload your music!":"Aquí no hay nada. ¡Sube tu música!","Pause":"Pausa","Play":"Reproducir","Previous":"Anterior","Repeat":"Repetir","Show all {{ trackcount }} songs ...":["Mostrar todas las {{ trackcount}} canciones ...","Mostrar todas las {{ trackcount }} canciones ..."],"Show less ...":"Mostrar menos ...","Shuffle":"Mezclar","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('et_EE', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Kustuta","Loading ...":"Laadimine ...","Music":"Muusika","Next":"Järgmine","Nothing in here. Upload your music!":"Siin pole midagi. Laadi oma muusikat üles!","Pause":"Paus","Play":"Esita","Previous":"Eelmine","Repeat":"Korda","Show all {{ trackcount }} songs ...":["Näita {{ trackcount }} lugu ...","Näita kõiki {{ trackcount }} lugu ..."],"Show less ...":"Näita vähem ...","Shuffle":"Juhuslik esitus","Unknown album":"Tundmatu album","Unknown artist":"Tundmatu esitaja"});
    gettextCatalog.setStrings('eu', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Ezabatu","Loading ...":"Kargatzen...","Music":"Musika","Next":"Hurrengoa","Nothing in here. Upload your music!":"Ez dago ezer. Igo zure musika!","Pause":"Pausarazi","Play":"Erreproduzitu","Previous":"Aurrekoa","Repeat":"Errepikatu","Show all {{ trackcount }} songs ...":["Bistaratu {{ trackcount}} abesti guztiak ...","Bistaratu {{ trackcount}} abesti guztiak ..."],"Show less ...":"Bistaratu gutxiago...","Shuffle":"Nahastu","Unknown album":"Diska ezezaguna","Unknown artist":"Artista ezezaguna"});
    gettextCatalog.setStrings('eu_ES', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Ezabatu","Loading ...":"","Music":"Musika","Next":"Aurrera","Nothing in here. Upload your music!":"","Pause":"geldi","Play":"jolastu","Previous":"Atzera","Repeat":"Errepikatu","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('fa', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"حذف","Loading ...":"","Music":"موزیک","Next":"بعدی","Nothing in here. Upload your music!":"","Pause":"توقف کردن","Play":"پخش کردن","Previous":"قبلی","Repeat":"تکرار","Show all {{ trackcount }} songs ...":"","Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('fi_FI', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Poista","Loading ...":"Ladataan...","Music":"Musiikki","Next":"Seuraava","Nothing in here. Upload your music!":"Täällä ei ole mitään. Lähetä musiikkia tänne!","Pause":"Keskeytä","Play":"Toista","Previous":"Edellinen","Repeat":"Kertaa","Show all {{ trackcount }} songs ...":["Näytä {{ trackcount }} kappale...","Näytä kaikki {{ trackcount }} kappaletta..."],"Show less ...":"Näytä vähemmän...","Shuffle":"Sekoita","Unknown album":"Tuntematon levy","Unknown artist":"Tuntematon esittäjä"});
    gettextCatalog.setStrings('fr', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Supprimer","Loading ...":"Chargement…","Music":"Musique","Next":"Suivant","Nothing in here. Upload your music!":"Il n'y a rien ici ! Envoyez donc votre musique :)","Pause":"Pause","Play":"Lire","Previous":"Précédent","Repeat":"Répéter","Show all {{ trackcount }} songs ...":["Afficher le morceau {{ trackcount }}...","Afficher les {{ trackcount }} morceaux..."],"Show less ...":"Afficher moins…","Shuffle":"Lecture aléatoire","Unknown album":"Album inconnu","Unknown artist":"Artiste inconnu"});
    gettextCatalog.setStrings('fr_CA', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"","Loading ...":"","Music":"","Next":"","Nothing in here. Upload your music!":"","Pause":"","Play":"","Previous":"","Repeat":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('gl', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Eliminar","Loading ...":"Cargando ...","Music":"Música","Next":"Seguinte","Nothing in here. Upload your music!":"Aquí non hai nada. Envíe a súa música!","Pause":"Pausa","Play":"Reproducir","Previous":"Anterior","Repeat":"Repetir","Show all {{ trackcount }} songs ...":["Amosar todas as {{ trackcount }} cancións ...","Amosar todas as {{ trackcount }} cancións ..."],"Show less ...":"Amosar menos ...","Shuffle":"Ao chou","Unknown album":"Album descoñecido","Unknown artist":"Interprete descoñecido"});
    gettextCatalog.setStrings('he', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"מחיקה","Loading ...":"טוען...","Music":"מוזיקה","Next":"הבא","Nothing in here. Upload your music!":"אין כאן שום דבר. אולי ברצונך להעלות משהו?","Pause":"השהה","Play":"נגן","Previous":"קודם","Repeat":"חזרה","Show all {{ trackcount }} songs ...":["הצג את כל {{trackcount}} שירים","הצג את כל {{trackcount}} שירים..."],"Show less ...":"הצג פחות...","Shuffle":"ערבב","Unknown album":"אלבום לא ידוע","Unknown artist":"אמן לא ידוע"});
    gettextCatalog.setStrings('hi', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"","Loading ...":"","Music":"","Next":"","Nothing in here. Upload your music!":"","Pause":"","Play":"","Previous":"","Repeat":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('hr', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Obriši","Loading ...":"","Music":"Muzika","Next":"Sljedeća","Nothing in here. Upload your music!":"","Pause":"Pauza","Play":"Reprodukcija","Previous":"Prethodna","Repeat":"Ponavljanje","Show all {{ trackcount }} songs ...":["","",""],"Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('hu_HU', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Törlés","Loading ...":"Betöltés ...","Music":"Zene","Next":"Következő","Nothing in here. Upload your music!":"Nincs itt semmi. Töltsd fel a zenédet!","Pause":"Szünet","Play":"Lejátszás","Previous":"Előző","Repeat":"Ismétlés","Show all {{ trackcount }} songs ...":["Mutasd mind a {{ trackcount }} zenét ...","Mutasd mind a {{ trackcount }} zenét ..."],"Show less ...":"Mutass kevesebbet...","Shuffle":"Keverés","Unknown album":"Ismeretlen album","Unknown artist":"Ismeretlen előadó"});
    gettextCatalog.setStrings('hy', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Ջնջել","Loading ...":"","Music":"","Next":"","Nothing in here. Upload your music!":"","Pause":"","Play":"","Previous":"","Repeat":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('ia', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Deler","Loading ...":"","Music":"Musica","Next":"Proxime","Nothing in here. Upload your music!":"","Pause":"Pausa","Play":"Reproducer","Previous":"Previe","Repeat":"Repeter","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('id', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Hapus","Loading ...":"Memuat ...","Music":"Musik","Next":"Selanjutnya","Nothing in here. Upload your music!":"Tidak ada apapun disini. Unggah musik anda!","Pause":"Jeda","Play":"Putar","Previous":"Sebelumnya","Repeat":"Ulangi","Show all {{ trackcount }} songs ...":"Menampilkan semua {{ trackcount }} lagu ...","Show less ...":"Menampilkan ringkas ...","Shuffle":"Acak","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('is', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Eyða","Loading ...":"","Music":"Tónlist","Next":"Næst","Nothing in here. Upload your music!":"","Pause":"Pása","Play":"Spila","Previous":"Fyrra","Repeat":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('it', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Elimina","Loading ...":"Caricamento in corso...","Music":"Musica","Next":"Successivo","Nothing in here. Upload your music!":"Non c'è niente qui. Carica la tua musica!","Pause":"Pausa","Play":"Riproduci","Previous":"Precedente","Repeat":"Ripeti","Show all {{ trackcount }} songs ...":["Mostra {{ trackcount }} brano...","Mostra tutti i {{ trackcount }} brani..."],"Show less ...":"Mostra meno...","Shuffle":"Mescola","Unknown album":"Album sconosciuto","Unknown artist":"Artista sconosciuto"});
    gettextCatalog.setStrings('ja_JP', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"削除","Loading ...":"読込中 ...","Music":"ミュージック","Next":"次","Nothing in here. Upload your music!":"ここには何もありません。ミュージックをアップロードしてください！","Pause":"一時停止","Play":"再生","Previous":"前","Repeat":"繰り返し","Show all {{ trackcount }} songs ...":"すべての {{ trackcount }} 曲を表示 ...","Show less ...":"簡略表示 ...","Shuffle":"シャッフル","Unknown album":"不明なアルバム","Unknown artist":"不明なアーティスト"});
    gettextCatalog.setStrings('ka', {"Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"","Loading ...":"","Music":"","Next":"","Nothing in here. Upload your music!":"","Pause":"","Play":"","Previous":"","Repeat":"","Show all {{ trackcount }} songs ...":"","Show less ...":"","Shuffle":""});
    gettextCatalog.setStrings('ka_GE', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"წაშლა","Loading ...":"","Music":"მუსიკა","Next":"შემდეგი","Nothing in here. Upload your music!":"","Pause":"პაუზა","Play":"დაკვრა","Previous":"წინა","Repeat":"გამეორება","Show all {{ trackcount }} songs ...":"","Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('km', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"លុប","Loading ...":"កំពុងផ្ទុក ...","Music":"តន្ត្រី","Next":"បន្ទាប់","Nothing in here. Upload your music!":"គ្មាន​អ្វីទេ​នៅទីនេះ។ ដាក់តន្ត្រី​របស់​អ្នកឡើង!","Pause":"ផ្អាក","Play":"លេង","Previous":"មុន","Repeat":"ធ្វើម្ដងទៀត","Show all {{ trackcount }} songs ...":"បង្ហាញ​ទាំង {{ trackcount }} ចម្រៀង ...","Show less ...":"បង្ហាញ​តិច ...","Shuffle":"បង្អូស","Unknown album":"អាល់ប៊ុមអត់​ឈ្មោះ","Unknown artist":"សិល្បករអត់​ឈ្មោះ"});
    gettextCatalog.setStrings('kn', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"","Loading ...":"","Music":"","Next":"","Nothing in here. Upload your music!":"","Pause":"","Play":"","Previous":"","Repeat":"","Show all {{ trackcount }} songs ...":"","Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('ko', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"삭제","Loading ...":"불러오는 중...","Music":"음악","Next":"다음","Nothing in here. Upload your music!":"아무 것도 없습니다. 음악을 업로드하십시오!","Pause":"일시 정지","Play":"재생","Previous":"이전","Repeat":"반복","Show all {{ trackcount }} songs ...":"모든 {{ trackcount }} 곡 보기...","Show less ...":"덜 보기...","Shuffle":"임의 재생","Unknown album":"알려지지 않은 앨범","Unknown artist":"알려지지 않은 아티스트"});
    gettextCatalog.setStrings('ku_IQ', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"","Loading ...":"","Music":"مۆسیقا","Next":"دوواتر","Nothing in here. Upload your music!":"","Pause":"وه‌ستان","Play":"لێدان","Previous":"پێشووتر","Repeat":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('lb', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Läschen","Loading ...":"","Music":"Musek","Next":"Weider","Nothing in here. Upload your music!":"","Pause":"Paus","Play":"Ofspillen","Previous":"Zeréck","Repeat":"Widderhuelen","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('lt_LT', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Ištrinti","Loading ...":"Įkeliama ...","Music":"Muzika","Next":"Kitas","Nothing in here. Upload your music!":"Nieko nėra. Įkelkite sava muziką!","Pause":"Pristabdyti","Play":"Groti","Previous":"Ankstesnis","Repeat":"Kartoti","Show all {{ trackcount }} songs ...":["Rodyti visas {{ trackcount }} dainas ...","Rodyti visas {{ trackcount }} dainas ...","Rodyti visas {{ trackcount }} dainas ..."],"Show less ...":"Rodyti mažiau ...","Shuffle":"Maišyti","Unknown album":"Nežinomas albumas","Unknown artist":"Nežinomas atlikėjas"});
    gettextCatalog.setStrings('lv', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Dzēst","Loading ...":"","Music":"Mūzika","Next":"Nākamā","Nothing in here. Upload your music!":"","Pause":"Pauzēt","Play":"Atskaņot","Previous":"Iepriekšējā","Repeat":"Atkārtot","Show all {{ trackcount }} songs ...":["","",""],"Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('mk', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Избриши","Loading ...":"Вчитувам ...","Music":"Музика","Next":"Следно","Nothing in here. Upload your music!":"Тука нема ништо. Ставете своја музика!","Pause":"Пауза","Play":"Пушти","Previous":"Претходно","Repeat":"Повтори","Show all {{ trackcount }} songs ...":["Прикажи ги сите {{ trackcount }} песни ...","Прикажи ги сите {{ trackcount }} песни ..."],"Show less ...":"Прикажи помалку ...","Shuffle":"Помешај","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('ml', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"","Loading ...":"","Music":"","Next":"","Nothing in here. Upload your music!":"","Pause":"","Play":"","Previous":"","Repeat":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('ml_IN', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"","Loading ...":"","Music":"സംഗീതം","Next":"","Nothing in here. Upload your music!":"","Pause":"","Play":"","Previous":"","Repeat":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('mn', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"","Loading ...":"","Music":"","Next":"","Nothing in here. Upload your music!":"","Pause":"","Play":"","Previous":"","Repeat":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('ms_MY', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Padam","Loading ...":"Memuatkan ...","Music":"Muzik","Next":"Seterus","Nothing in here. Upload your music!":"Tiada apa-apa di sini. Muat naik muzik anda!","Pause":"Jeda","Play":"Main","Previous":"Sebelum","Repeat":"Ulang","Show all {{ trackcount }} songs ...":"Paparkan semua {{ trackcount }} lagu ...","Show less ...":"Kurangkan paparan ...","Shuffle":"Kocok","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('my_MM', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"","Loading ...":"","Music":"","Next":"","Nothing in here. Upload your music!":"","Pause":"","Play":"","Previous":"","Repeat":"","Show all {{ trackcount }} songs ...":"","Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('nb_NO', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Slett","Loading ...":"Laster ...","Music":"Musikk","Next":"Neste","Nothing in here. Upload your music!":"Ingenting her. Last opp musikken din!","Pause":"Pause","Play":"Spill","Previous":"Forrige","Repeat":"Gjenta","Show all {{ trackcount }} songs ...":["Vis alle {{ trackcount }} sanger ...","Vis alle {{ trackcount }} sanger ..."],"Show less ...":"Vis mindre ...","Shuffle":"Tilfeldig","Unknown album":"Ukjent album","Unknown artist":"Ukjent artist"});
    gettextCatalog.setStrings('nds', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"","Loading ...":"","Music":"","Next":"","Nothing in here. Upload your music!":"","Pause":"","Play":"","Previous":"","Repeat":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('ne', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"","Loading ...":"","Music":"","Next":"","Nothing in here. Upload your music!":"","Pause":"","Play":"","Previous":"","Repeat":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('nl', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Verwijder","Loading ...":"Laden ...","Music":"Muziek","Next":"Volgende","Nothing in here. Upload your music!":"Nog niets aanwezig. Upload uw muziek!","Pause":"Pause","Play":"Afspelen","Previous":"Vorige","Repeat":"Herhaling","Show all {{ trackcount }} songs ...":["Toon elk {{ trackcount }} nummer ...","Toon alle {{ trackcount }} nummers ..."],"Show less ...":"Toon minder ...","Shuffle":"Shuffle","Unknown album":"Onbekend album","Unknown artist":"Onbekende artiest"});
    gettextCatalog.setStrings('nn_NO', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Slett","Loading ...":"","Music":"Musikk","Next":"Neste","Nothing in here. Upload your music!":"","Pause":"Pause","Play":"Spel","Previous":"Førre","Repeat":"Gjenta","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('nqo', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"","Loading ...":"","Music":"","Next":"","Nothing in here. Upload your music!":"","Pause":"","Play":"","Previous":"","Repeat":"","Show all {{ trackcount }} songs ...":"","Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('oc', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Escafa","Loading ...":"","Music":"Musica","Next":"Venent","Nothing in here. Upload your music!":"","Pause":"Pausa","Play":"Fai tirar","Previous":"Darrièr","Repeat":"Torna far","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('pa', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"ਹਟਾਓ","Loading ...":"","Music":"ਸੰਗੀਤ","Next":"","Nothing in here. Upload your music!":"","Pause":"","Play":"","Previous":"","Repeat":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('pl', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Usuń","Loading ...":"Wczytuję ...","Music":"Muzyka","Next":"Następny","Nothing in here. Upload your music!":"Brak zawartości. Proszę wysłać muzykę!","Pause":"Wstrzymaj","Play":"Odtwarzaj","Previous":"Poprzedni","Repeat":"Powtarzanie","Show all {{ trackcount }} songs ...":["Pokaż wszystkie {{ trackcount }} piosenek ...","Pokaż wszystkie {{ trackcount }} piosenek ...","Pokaż wszystkie {{ trackcount }} piosenek ..."],"Show less ...":"Pokaz mniej ...","Shuffle":"Losowo","Unknown album":"Nieznany album","Unknown artist":"Nieznany artysta"});
    gettextCatalog.setStrings('pt_BR', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Eliminar","Loading ...":"Carregando...","Music":"Música","Next":"Próxima","Nothing in here. Upload your music!":"Nada aqui. Enviar sua música!","Pause":"Paisa","Play":"Reproduzir","Previous":"Anterior","Repeat":"Repatir","Show all {{ trackcount }} songs ...":["Mostrar todas as {{ trackcount }} músicas ...","Mostrar todas as {{ trackcount }} músicas ..."],"Show less ...":"Exibição mais simples...","Shuffle":"Embaralhar","Unknown album":"Album desconhecido","Unknown artist":"Artista desconhecido"});
    gettextCatalog.setStrings('pt_PT', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Eliminar","Loading ...":"A carregar...","Music":"Musica","Next":"Próxima","Nothing in here. Upload your music!":"Não existe nada aqui. Carregue a sua musica!","Pause":"Pausa","Play":"Reproduzir","Previous":"Anterior","Repeat":"Repetir","Show all {{ trackcount }} songs ...":["Mostrar todas as {{ trackcount }} músicas ...","Mostrar todas as {{ trackcount }} músicas ..."],"Show less ...":"Mostrar menos...","Shuffle":"Baralhar","Unknown album":"Álbum desconhecido","Unknown artist":"Artista desconhecido"});
    gettextCatalog.setStrings('ro', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Șterge","Loading ...":"","Music":"Muzică","Next":"Următor","Nothing in here. Upload your music!":"","Pause":"Pauză","Play":"Redă","Previous":"Anterior","Repeat":"Repetă","Show all {{ trackcount }} songs ...":["","",""],"Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('ru', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Удалить","Loading ...":"Загружается...","Music":"Музыка","Next":"Следующий","Nothing in here. Upload your music!":"Здесь ничего нет. Загрузите вашу музыку!","Pause":"Пауза","Play":"Проиграть","Previous":"Предыдущий","Repeat":"Повтор","Show all {{ trackcount }} songs ...":["Показать {{ trackcount }} песню...","Показать все {{ trackcount }} песни...","Показать все {{ trackcount }} песен..."],"Show less ...":"Показать меньше...","Shuffle":"Перемешать","Unknown album":"Неизвестный альбом","Unknown artist":"Неизвестный исполнитель"});
    gettextCatalog.setStrings('ru_RU', {"Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Удалить","Loading ...":"","Music":"Музыка","Next":"","Nothing in here. Upload your music!":"","Pause":"","Play":"","Previous":"","Repeat":"","Show all {{ trackcount }} songs ...":["","",""],"Show less ...":"","Shuffle":""});
    gettextCatalog.setStrings('si_LK', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"මකා දමන්න","Loading ...":"","Music":"සංගීතය","Next":"ඊලඟ","Nothing in here. Upload your music!":"","Pause":"විරාමය","Play":"ධාවනය","Previous":"පෙර","Repeat":"පුනරාවර්ථන","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('sk', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Odstrániť","Loading ...":"","Music":"","Next":"","Nothing in here. Upload your music!":"","Pause":"","Play":"","Previous":"","Repeat":"Opakovať","Show all {{ trackcount }} songs ...":["","",""],"Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('sk_SK', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Zmazať","Loading ...":"Nahrávam...","Music":"Hudba","Next":"Ďalšia","Nothing in here. Upload your music!":"Nič tu nie je. Nahrajte si vašu hudbu!","Pause":"Pauza","Play":"Prehrať","Previous":"Predošlá","Repeat":"Opakovať","Show all {{ trackcount }} songs ...":["Zobraziť {{ trackcount }} skladieb ...","Zobraziť {{ trackcount }} skladieb ...","Zobraziť všetky {{ trackcount }} skladieb ..."],"Show less ...":"Zobraziť menej ...","Shuffle":"Zamiešať","Unknown album":"Neznámy album","Unknown artist":"Neznámy umelec"});
    gettextCatalog.setStrings('sl', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Izbriši","Loading ...":"Nalaganje ...","Music":"Glasba","Next":"Naslednja","Nothing in here. Upload your music!":"V mapi ni glasbenih datotek. Dodajte skladbe!","Pause":"Premor","Play":"Predvajaj","Previous":"Predhodna","Repeat":"Ponovi","Show all {{ trackcount }} songs ...":["Pokaži {{ trackcount }} posnetek ...","Pokaži {{ trackcount }} posnetka ...","Pokaži {{ trackcount }} posnetke ...","Pokaži {{ trackcount }} posnetkov ..."],"Show less ...":"Pokaži manj ...","Shuffle":"Premešaj","Unknown album":"Neznan album","Unknown artist":"Neznan izvajalec"});
    gettextCatalog.setStrings('sq', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Elimino","Loading ...":"Ngarkim...","Music":"Muzikë","Next":"Mëpasshëm","Nothing in here. Upload your music!":"Këtu nuk ka asgjë. Ngarkoni muziken tuaj!","Pause":"Pauzë","Play":"Luaj","Previous":"Mëparshëm","Repeat":"Përsëritet","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"Shfaq m'pak...","Shuffle":"Përziej","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('sr', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Обриши","Loading ...":"","Music":"Музика","Next":"Следећа","Nothing in here. Upload your music!":"","Pause":"Паузирај","Play":"Пусти","Previous":"Претходна","Repeat":"Понављај","Show all {{ trackcount }} songs ...":["","",""],"Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('sr@latin', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Obriši","Loading ...":"","Music":"Muzika","Next":"Sledeća","Nothing in here. Upload your music!":"","Pause":"Pauziraj","Play":"Pusti","Previous":"Prethodna","Repeat":"Ponavljaj","Show all {{ trackcount }} songs ...":["","",""],"Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('su', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"","Loading ...":"","Music":"","Next":"","Nothing in here. Upload your music!":"","Pause":"","Play":"","Previous":"","Repeat":"","Show all {{ trackcount }} songs ...":"","Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('sv', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Radera","Loading ...":"Laddar  ...","Music":"Musik","Next":"Nästa","Nothing in here. Upload your music!":"Det finns inget här. Ladda upp din musik!","Pause":"Paus","Play":"Spela","Previous":"Föregående","Repeat":"Upprepa","Show all {{ trackcount }} songs ...":["Visa alla {{ trackcount }} låtar ...","Visa alla {{ trackcount }} låtar ..."],"Show less ...":"Visa mindre ...","Shuffle":"Blanda","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('sw_KE', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"","Loading ...":"","Music":"","Next":"","Nothing in here. Upload your music!":"","Pause":"","Play":"","Previous":"","Repeat":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('ta_LK', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"நீக்குக","Loading ...":"","Music":"இசை","Next":"அடுத்த","Nothing in here. Upload your music!":"","Pause":"இடைநிறுத்துக","Play":"Play","Previous":"முன்தைய","Repeat":"மீண்டும்","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('te', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"తొలగించు","Loading ...":"","Music":"సంగీతం","Next":"తదుపరి","Nothing in here. Upload your music!":"","Pause":"","Play":"","Previous":"గత","Repeat":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('th_TH', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"ลบ","Loading ...":"","Music":"เพลง","Next":"ถัดไป","Nothing in here. Upload your music!":"","Pause":"หยุดชั่วคราว","Play":"เล่น","Previous":"ก่อนหน้า","Repeat":"ทำซ้ำ","Show all {{ trackcount }} songs ...":"","Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('tr', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Sil","Loading ...":"Yükleniyor...","Music":"Müzik","Next":"Sonraki","Nothing in here. Upload your music!":"Burada hiçbir şey yok. Müziğinizi yükleyin!","Pause":"Beklet","Play":"Oynat","Previous":"Önceki","Repeat":"Tekrar","Show all {{ trackcount }} songs ...":["Tüm {{ trackcount }} şarkıyı göster ...","Tüm {{ trackcount }} şarkıyı göster ..."],"Show less ...":"Daha az göster ...","Shuffle":"Karıştır","Unknown album":"Bilinmeyen albüm","Unknown artist":"Bilinmeyen sanatçı"});
    gettextCatalog.setStrings('tzm', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"","Loading ...":"","Music":"","Next":"","Nothing in here. Upload your music!":"","Pause":"","Play":"","Previous":"","Repeat":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('ug', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"ئۆچۈر","Loading ...":"","Music":"نەغمە","Next":"كېيىنكى","Nothing in here. Upload your music!":"","Pause":"ۋاقىتلىق توختا","Play":"چال","Previous":"ئالدىنقى","Repeat":"قايتىلا","Show all {{ trackcount }} songs ...":"","Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('uk', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Видалити","Loading ...":"Завантаження ...","Music":"Музика","Next":"Наступний","Nothing in here. Upload your music!":"Тут зараз нічого немає. Завантажте свою музику!","Pause":"Пауза","Play":"Грати","Previous":"Попередній","Repeat":"Повторювати","Show all {{ trackcount }} songs ...":["Показати {{ trackcount }} пісню ...","Показати всі {{ trackcount }} пісні ...","Показати всі {{ trackcount }} пісні ..."],"Show less ...":"Показати меньше ...","Shuffle":"Перемішати","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('ur', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"","Loading ...":"","Music":"","Next":"","Nothing in here. Upload your music!":"","Pause":"","Play":"","Previous":"","Repeat":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('ur_PK', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"","Loading ...":"","Music":"","Next":"","Nothing in here. Upload your music!":"","Pause":"","Play":"","Previous":"","Repeat":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('uz', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"","Loading ...":"","Music":"","Next":"","Nothing in here. Upload your music!":"","Pause":"","Play":"","Previous":"","Repeat":"","Show all {{ trackcount }} songs ...":"","Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('vi', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"Xóa","Loading ...":"Đang tải ...","Music":"Âm nhạc","Next":"Kế tiếp","Nothing in here. Upload your music!":"Không có gì ở đây. Hãy tải nhạc của bạn lên!","Pause":"Tạm dừng","Play":"Play","Previous":"Lùi lại","Repeat":"Lặp lại","Show all {{ trackcount }} songs ...":"Hiển thị tất cả {{ trackcount }} bài hát ...","Show less ...":"Hiển thị ít hơn ...","Shuffle":"Ngẫu nhiên","Unknown album":"Không tìm thấy album","Unknown artist":"Không tìm thấy nghệ sĩ"});
    gettextCatalog.setStrings('zh_CN', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"删除","Loading ...":"加载中...","Music":"音乐","Next":"下一个","Nothing in here. Upload your music!":"这里还什么都没有。上传你的音乐吧！","Pause":"暂停","Play":"播放","Previous":"前一首","Repeat":"重复","Show all {{ trackcount }} songs ...":"显示所有 {{ trackcount }} 首歌曲 ...","Show less ...":"显示概要","Shuffle":"随机","Unknown album":"未知专辑","Unknown artist":"未知艺术家"});
    gettextCatalog.setStrings('zh_HK', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"刪除","Loading ...":"","Music":"音樂","Next":"下一首","Nothing in here. Upload your music!":"","Pause":"暫停","Play":"播放","Previous":"上一首","Repeat":"","Show all {{ trackcount }} songs ...":"","Show less ...":"","Shuffle":"","Unknown album":"","Unknown artist":""});
    gettextCatalog.setStrings('zh_TW', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"","Delete":"刪除","Loading ...":"載入中…","Music":"音樂","Next":"下一個","Nothing in here. Upload your music!":"這裡沒有東西，上傳你的音樂！","Pause":"暫停","Play":"播放","Previous":"上一個","Repeat":"重覆","Show all {{ trackcount }} songs ...":"顯示全部 {{ trackcount }} 首歌曲","Show less ...":"顯示更少","Shuffle":"隨機播放","Unknown album":"","Unknown artist":""});

/* jshint +W100 */
}]);
