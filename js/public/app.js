
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

angular.module('Music', ['restangular', 'gettext']).
	config(
		['$routeProvider', '$interpolateProvider', 'RestangularProvider',
		function ($routeProvider, $interpolateProvider, RestangularProvider) {

	$routeProvider.when('/', {
		templateUrl: 'main.html'
	}).otherwise({
		redirectTo: '/'
	});

	// configure RESTAngular path
	RestangularProvider.setBaseUrl('api');
}]);
angular.module('Music').controller('MainController',
	['$rootScope', '$scope', 'Artists', 'playlistService', 'gettextCatalog',
	function ($rootScope, $scope, Artists, playlistService, gettextCatalog) {

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
		if($scope.$parent.started) {
			// invoke play after the flash gets unblocked
			$scope.$apply(function(){
				$scope.next();
			});
		}
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
angular.module("Music").run(['gettextCatalog', function (gettextCatalog) {
    gettextCatalog.setStrings('ach', {"Music":"","Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('ady', {"Music":"","Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('af', {"Music":"","Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('af_ZA', {"Music":"","Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('ar', {"Music":"الموسيقى","Loading ...":"","Previous":"السابق","Play":"إلعب","Pause":"تجميد","Next":"التالي","Shuffle":"","Repeat":"إعادة","Delete":"إلغاء","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["","","","","",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('be', {"Music":"","Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["","","",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('bg_BG', {"Music":"Музика","Loading ...":"","Previous":"Предишна","Play":"Пусни","Pause":"Пауза","Next":"Следваща","Shuffle":"","Repeat":"Повтори","Delete":"Изтриване","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('bn_BD', {"Music":"গানবাজনা","Loading ...":"","Previous":"পূর্ববর্তী","Play":"বাজাও","Pause":"বিরতি","Next":"পরবর্তী","Shuffle":"","Repeat":"পূনঃসংঘটন","Delete":"মুছে","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('bs', {"Music":"","Loading ...":"","Previous":"","Play":"","Pause":"","Next":"Sljedeći","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["","",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('ca', {"Music":"Música","Loading ...":"Carregant...","Previous":"Anterior","Play":"Reprodueix","Pause":"Pausa","Next":"Següent","Shuffle":"Aleatori","Repeat":"Repeteix","Delete":"Esborra","Nothing in here. Upload your music!":"Res per aquí. Pugeu la vostra música!","Show all {{ trackcount }} songs ...":["Mostra totes les {{ trackcount }} peces...","Mostra totes les {{ trackcount }} peces..."],"Show less ...":"Mostra'n menys...","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Chrome només permet reproduir fitxers MP3 - veieu la <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>"});
    gettextCatalog.setStrings('cs_CZ', {"Music":"Hudba","Loading ...":"Načítám ...","Previous":"Předchozí","Play":"Přehrát","Pause":"Pozastavit","Next":"Následující","Shuffle":"Promíchat","Repeat":"Opakovat","Delete":"Smazat","Nothing in here. Upload your music!":"Zde nic není. Nahrajte vaši hudbu!","Show all {{ trackcount }} songs ...":["Zobrazit {{ trackcount }} písničku ...","Zobrazit {{ trackcount }} písničky ...","Zobrazit {{ trackcount }} písniček ..."],"Show less ...":"Zobrazit méně ...","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('cy_GB', {"Music":"Cerddoriaeth","Loading ...":"","Previous":"Blaenorol","Play":"Chwarae","Pause":"Seibio","Next":"Nesaf","Shuffle":"","Repeat":"Ailadrodd","Delete":"Dileu","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["","","",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('da', {"Music":"Musik","Loading ...":"Indlæser...","Previous":"Forrige","Play":"Afspil","Pause":"Pause","Next":"Næste","Shuffle":"Bland","Repeat":"Gentag","Delete":"Slet","Nothing in here. Upload your music!":"Her er tomt. Upload din musik!","Show all {{ trackcount }} songs ...":["Vis alle {{ trackcount }} sange ...","Vis alle {{ trackcount }} sange ..."],"Show less ...":"Vis færre ...","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Chrome kan kun afspille MP3 filer se <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki'en</a> for yderligere information."});
    gettextCatalog.setStrings('de', {"Music":"Musik","Loading ...":"Lade ...","Previous":"Zurück","Play":"Abspielen","Pause":"Anhalten","Next":"Weiter","Shuffle":"Zufallswiedergabe","Repeat":"Wiederholen","Delete":"Löschen","Nothing in here. Upload your music!":"Alles leer. Laden Sie Ihre Musik hoch!","Show all {{ trackcount }} songs ...":["{{ trackcount }} Lied anzeigen  ...","Alle {{ trackcount }} Lieder anzeigen  ..."],"Show less ...":"Weniger anzeigen ...","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Chrome ist nur in der Lage, MP3-Dateien wiederzugeben - siehe in das <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">Wiki</a>"});
    gettextCatalog.setStrings('de_AT', {"Music":"","Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('de_CH', {"Music":"Musik","Loading ...":"","Previous":"Vorheriges","Play":"Abspielen","Pause":"Anhalten","Next":"Weiter","Shuffle":"","Repeat":"Wiederholen","Delete":"Löschen","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('de_DE', {"Music":"Musik","Loading ...":"Lade …","Previous":"Zurück","Play":"Abspielen","Pause":"Anhalten","Next":"Weiter","Shuffle":"Zufallswiedergabe","Repeat":"Wiederholen","Delete":"Löschen","Nothing in here. Upload your music!":"Alles leer. Laden Sie Ihre Musik hoch!","Show all {{ trackcount }} songs ...":["{{ trackcount }} Lied anzeigen  ...","Alle {{ trackcount }} Lieder anzeigen  ..."],"Show less ...":"Weniger anzeigen ...","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Chrome ist nur in der Lage, MP3-Dateien wiederzugeben - siehe in das <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">Wiki</a>"});
    gettextCatalog.setStrings('el', {"Music":"Μουσική","Loading ...":"Φόρτωση ...","Previous":"Προηγούμενη","Play":"Αναπαραγωγή","Pause":"Παύση","Next":"Επόμενη","Shuffle":"Ανακάτεμα","Repeat":"Επαναλαμβανόμενο","Delete":"Διαγραφή","Nothing in here. Upload your music!":"Δεν υπάρχει τίποτα εδώ. Ανεβάστε την μουσική σας!","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"Προβολή λιγότερων...","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Το Chrome αναπαράγει μόνο αρχεία MP3 - δείτε στο <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>"});
    gettextCatalog.setStrings('en@pirate', {"Music":"Music","Loading ...":"","Previous":"","Play":"","Pause":"Pause","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('en_GB', {"Music":"Music","Loading ...":"Loading...","Previous":"Previous","Play":"Play","Pause":"Pause","Next":"Next","Shuffle":"Shuffle","Repeat":"Repeat","Delete":"Delete","Nothing in here. Upload your music!":"Nothing in here. Upload your music!","Show all {{ trackcount }} songs ...":["Show all {{ trackcount }} songs...","Show all {{ trackcount }} songs..."],"Show less ...":"Show less...","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>"});
    gettextCatalog.setStrings('eo', {"Music":"Muziko","Loading ...":"Ŝargante...","Previous":"Maljena","Play":"Ludi","Pause":"Paŭzi...","Next":"Jena","Shuffle":"Miksi","Repeat":"Ripeti","Delete":"Forigi","Nothing in here. Upload your music!":"Nenio estas ĉi tie. Alŝutu vian muzikon!","Show all {{ trackcount }} songs ...":["Montri ĉiujn {{ trackcount }} kantaĵojn...","Montri ĉiujn {{ trackcount }} kantaĵojn..."],"Show less ...":"Montri malpli...","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Chrome nur kapablas reprodukti MP3-dosierojn – vidu <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">la vikion</a>"});
    gettextCatalog.setStrings('es', {"Music":"Música","Loading ...":"Cargando...","Previous":"Anterior","Play":"Reproducir","Pause":"Pausa","Next":"Siguiente","Shuffle":"Mezclar","Repeat":"Repetir","Delete":"Eliminar","Nothing in here. Upload your music!":"Aquí no hay nada. ¡Sube tu música!","Show all {{ trackcount }} songs ...":["Mostrar todas las {{ trackcount}} canciones...","Mostrar todas las {{ trackcount }} canciones..."],"Show less ...":"Mostrar menos...","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Chrome únicamente puede reproducir archivos MP3 - ver <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>"});
    gettextCatalog.setStrings('es_AR', {"Music":"Música","Loading ...":"","Previous":"Previo","Play":"Reproducir","Pause":"Pausar","Next":"Siguiente","Shuffle":"","Repeat":"Repetir","Delete":"Borrar","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('es_MX', {"Music":"","Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('et_EE', {"Music":"Muusika","Loading ...":"Laadimine ...","Previous":"Eelmine","Play":"Esita","Pause":"Paus","Next":"Järgmine","Shuffle":"Juhuslik esitus","Repeat":"Korda","Delete":"Kustuta","Nothing in here. Upload your music!":"Siin pole midagi. Laadi oma muusikat üles!","Show all {{ trackcount }} songs ...":["Näita {{ trackcount }} lugu ...","Näita kõiki {{ trackcount }} lugu ..."],"Show less ...":"Näita vähem ...","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Chrome suudab esitada ainult MP3 faile - vaata lisa <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wikist</a>"});
    gettextCatalog.setStrings('eu', {"Music":"Musika","Loading ...":"Kargatzen...","Previous":"Aurrekoa","Play":"Erreproduzitu","Pause":"Pausarazi","Next":"Hurrengoa","Shuffle":"Nahastu","Repeat":"Errepikatu","Delete":"Ezabatu","Nothing in here. Upload your music!":"Ez dago ezer. Igo zure musika!","Show all {{ trackcount }} songs ...":["Bistaratu {{ trackcount}} abesti guztiak ...","Bistaratu {{ trackcount}} abesti guztiak ..."],"Show less ...":"Bistaratu gutxiago...","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('fa', {"Music":"موزیک","Loading ...":"","Previous":"قبلی","Play":"پخش کردن","Pause":"توقف کردن","Next":"بعدی","Shuffle":"","Repeat":"تکرار","Delete":"حذف","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":"","Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('fi_FI', {"Music":"Musiikki","Loading ...":"Ladataan...","Previous":"Edellinen","Play":"Toista","Pause":"Keskeytä","Next":"Seuraava","Shuffle":"Sekoita","Repeat":"Kertaa","Delete":"Poista","Nothing in here. Upload your music!":"Täällä ei ole mitään. Lähetä musiikkia tänne!","Show all {{ trackcount }} songs ...":["Näytä {{ trackcount }} kappale...","Näytä kaikki {{ trackcount }} kappaletta..."],"Show less ...":"Näytä vähemmän...","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Chrome voi toistaa vain MP3-tiedostoja - lisätietoja <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wikissä</a>"});
    gettextCatalog.setStrings('fr', {"Music":"Musique","Loading ...":"Chargement en cours…","Previous":"Précédent","Play":"Lire","Pause":"Pause","Next":"Suivant","Shuffle":"Lecture aléatoire","Repeat":"Répéter","Delete":"Supprimer","Nothing in here. Upload your music!":"Il n'y a rien ici ! Envoyez donc votre musique :)","Show all {{ trackcount }} songs ...":["Afficher le morceau {{ trackcount }}...","Afficher les {{ trackcount }} morceaux..."],"Show less ...":"Afficher moins…","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Chrome  n'est capable de jouer que les fichiers MP3 uniquement - voir le <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>"});
    gettextCatalog.setStrings('gl', {"Music":"Música","Loading ...":"Cargando ...","Previous":"Anterior","Play":"Reproducir","Pause":"Pausa","Next":"Seguinte","Shuffle":"Ao chou","Repeat":"Repetir","Delete":"Eliminar","Nothing in here. Upload your music!":"Aquí non hai nada. Envíe a súa música!","Show all {{ trackcount }} songs ...":["Amosar todas as {{ trackcount }} cancións ...","Amosar todas as {{ trackcount }} cancións ..."],"Show less ...":"Amosar menos ...","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Chrome só pode de reproducir ficheiros MP3 - vexa o <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>"});
    gettextCatalog.setStrings('he', {"Music":"מוזיקה","Loading ...":"","Previous":"קודם","Play":"נגן","Pause":"השהה","Next":"הבא","Shuffle":"","Repeat":"חזרה","Delete":"מחיקה","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('hi', {"Music":"","Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('hr', {"Music":"Muzika","Loading ...":"","Previous":"Prethodna","Play":"Reprodukcija","Pause":"Pauza","Next":"Sljedeća","Shuffle":"","Repeat":"Ponavljanje","Delete":"Obriši","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["","",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('hu_HU', {"Music":"Zene","Loading ...":"","Previous":"Előző","Play":"Lejátszás","Pause":"Szünet","Next":"Következő","Shuffle":"","Repeat":"Ismétlés","Delete":"Törlés","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('hy', {"Music":"","Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"Ջնջել","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('ia', {"Music":"Musica","Loading ...":"","Previous":"Previe","Play":"Reproducer","Pause":"Pausa","Next":"Proxime","Shuffle":"","Repeat":"Repeter","Delete":"Deler","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('id', {"Music":"Musik","Loading ...":"","Previous":"Sebelumnya","Play":"Putar","Pause":"Jeda","Next":"Selanjutnya","Shuffle":"","Repeat":"Ulangi","Delete":"Hapus","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":"","Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('is', {"Music":"Tónlist","Loading ...":"","Previous":"Fyrra","Play":"Spila","Pause":"Pása","Next":"Næst","Shuffle":"","Repeat":"","Delete":"Eyða","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('it', {"Music":"Musica","Loading ...":"Caricamento in corso...","Previous":"Precedente","Play":"Riproduci","Pause":"Pausa","Next":"Successivo","Shuffle":"Mescola","Repeat":"Ripeti","Delete":"Elimina","Nothing in here. Upload your music!":"Non c'è niente qui. Carica la tua musica!","Show all {{ trackcount }} songs ...":["Mostra {{ trackcount }} brano...","Mostra tutti i {{ trackcount }} brani..."],"Show less ...":"Mostra meno...","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Chrome è in grado di riprodurre solo file MP3 - vedi il <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>"});
    gettextCatalog.setStrings('ja_JP', {"Music":"ミュージック","Loading ...":"読込中 ...","Previous":"前","Play":"再生","Pause":"一時停止","Next":"次","Shuffle":"シャッフル","Repeat":"繰り返し","Delete":"削除","Nothing in here. Upload your music!":"ここには何もありません。ミュージックをアップロードしてください！","Show all {{ trackcount }} songs ...":"すべての {{ trackcount }} 曲を表示 ...","Show less ...":"簡略表示 ...","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Chrome は MP3 ファイルの再生のみ可能です - <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a> を参照してください。"});
    gettextCatalog.setStrings('ka', {"Music":"","Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":"","Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('ka_GE', {"Music":"მუსიკა","Loading ...":"","Previous":"წინა","Play":"დაკვრა","Pause":"პაუზა","Next":"შემდეგი","Shuffle":"","Repeat":"გამეორება","Delete":"წაშლა","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":"","Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('km', {"Music":"តន្ត្រី","Loading ...":"កំពុងផ្ទុក ...","Previous":"មុន","Play":"លេង","Pause":"ផ្អាក","Next":"បន្ទាប់","Shuffle":"បង្អូស","Repeat":"ធ្វើម្ដងទៀត","Delete":"លុប","Nothing in here. Upload your music!":"គ្មាន​អ្វីទេ​នៅទីនេះ។ ដាក់តន្ត្រី​របស់​អ្នកឡើង!","Show all {{ trackcount }} songs ...":"បង្ហាញ​ទាំង {{ trackcount }} ចម្រៀង ...","Show less ...":"បង្ហាញ​តិច ...","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Chrome គ្រាន់​តែអាច​ចាក់ឡើងវិញនូវឯកសារ MP3​ ប៉ុណ្ណោះ - សូម​អាននៅ <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">វីគី</a>"});
    gettextCatalog.setStrings('kundefined', {"Music":"","Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":"","Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('ko', {"Music":"음악","Loading ...":"불러오는 중...","Previous":"이전","Play":"재생","Pause":"일시 정지","Next":"다음","Shuffle":"임의 재생","Repeat":"반복","Delete":"삭제","Nothing in here. Upload your music!":"아무것도 들어있지 않아요! 업로드 해주세요!","Show all {{ trackcount }} songs ...":"전체 {{ trackcount }} 곡 보이기","Show less ...":"그외 곡들 보여주기...","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"구글 크롬만이 MP3 파일을 재생할수 잇습니다 - <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>를 보세요"});
    gettextCatalog.setStrings('ku_IQ', {"Music":"مۆسیقا","Loading ...":"","Previous":"پێشووتر","Play":"لێدان","Pause":"وه‌ستان","Next":"دوواتر","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('lb', {"Music":"Musek","Loading ...":"","Previous":"Zeréck","Play":"Ofspillen","Pause":"Paus","Next":"Weider","Shuffle":"","Repeat":"Widderhuelen","Delete":"Läschen","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('lt_LT', {"Music":"Muzika","Loading ...":"Įkeliama ...","Previous":"Ankstesnis","Play":"Groti","Pause":"Pristabdyti","Next":"Kitas","Shuffle":"Maišyti","Repeat":"Kartoti","Delete":"Ištrinti","Nothing in here. Upload your music!":"Nieko nėra. Įkelkite sava muziką!","Show all {{ trackcount }} songs ...":["Rodyti visas {{ trackcount }} dainas ...","Rodyti visas {{ trackcount }} dainas ...","Rodyti visas {{ trackcount }} dainas ..."],"Show less ...":"Rodyti mažiau ...","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Chrome gali groti tik MP3 formato muziką - sužinokite daugiau <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki puslapyje</a>"});
    gettextCatalog.setStrings('lv', {"Music":"Mūzika","Loading ...":"","Previous":"Iepriekšējā","Play":"Atskaņot","Pause":"Pauzēt","Next":"Nākamā","Shuffle":"","Repeat":"Atkārtot","Delete":"Dzēst","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["","",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('mk', {"Music":"Музика","Loading ...":"Вчитувам ...","Previous":"Претходно","Play":"Пушти","Pause":"Пауза","Next":"Следно","Shuffle":"Помешај","Repeat":"Повтори","Delete":"Избриши","Nothing in here. Upload your music!":"Тука нема ништо. Ставете своја музика!","Show all {{ trackcount }} songs ...":["Прикажи ги сите {{ trackcount }} песни ...","Прикажи ги сите {{ trackcount }} песни ..."],"Show less ...":"Прикажи помалку ...","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Само Chrome може да репродуцира MP3 датотеки - види <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>"});
    gettextCatalog.setStrings('ml_IN', {"Music":"സംഗീതം","Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('ms_MY', {"Music":"Muzik","Loading ...":"","Previous":"Sebelum","Play":"Main","Pause":"Jeda","Next":"Seterus","Shuffle":"","Repeat":"Ulang","Delete":"Padam","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":"","Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('my_MM', {"Music":"","Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":"","Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('nb_NO', {"Music":"Musikk","Loading ...":"","Previous":"Forrige","Play":"Spill","Pause":"Pause","Next":"Neste","Shuffle":"","Repeat":"Gjenta","Delete":"Slett","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('nds', {"Music":"","Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('ne', {"Music":"","Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('nl', {"Music":"Muziek","Loading ...":"Laden ...","Previous":"Vorige","Play":"Afspelen","Pause":"Pause","Next":"Volgende","Shuffle":"Shuffle","Repeat":"Herhaling","Delete":"Verwijder","Nothing in here. Upload your music!":"Nog niets aanwezig. Upload uw muziek!","Show all {{ trackcount }} songs ...":["Toon elk {{ trackcount }} nummer ...","Toon alle {{ trackcount }} nummers ..."],"Show less ...":"Toon minder ...","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Chrome kan alleen MP3 bestanden afspelen - zie <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>"});
    gettextCatalog.setStrings('nn_NO', {"Music":"Musikk","Loading ...":"","Previous":"Førre","Play":"Spel","Pause":"Pause","Next":"Neste","Shuffle":"","Repeat":"Gjenta","Delete":"Slett","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('nqo', {"Music":"","Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":"","Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('oc', {"Music":"Musica","Loading ...":"","Previous":"Darrièr","Play":"Fai tirar","Pause":"Pausa","Next":"Venent","Shuffle":"","Repeat":"Torna far","Delete":"Escafa","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('pa', {"Music":"ਸੰਗੀਤ","Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"ਹਟਾਓ","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('pl', {"Music":"Muzyka","Loading ...":"Wczytuję ...","Previous":"Poprzedni","Play":"Odtwarzaj","Pause":"Wstrzymaj","Next":"Następny","Shuffle":"Losowo","Repeat":"Powtarzanie","Delete":"Usuń","Nothing in here. Upload your music!":"Brak zawartości. Proszę wysłać muzykę!","Show all {{ trackcount }} songs ...":["Pokaż wszystkie {{ trackcount }} piosenek ...","Pokaż wszystkie {{ trackcount }} piosenek ...","Pokaż wszystkie {{ trackcount }} piosenek ..."],"Show less ...":"Pokaz mniej ...","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"W Chrome jest tylko możliwość odtwarzania muzyki MP3 - zobacz <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>"});
    gettextCatalog.setStrings('pt_BR', {"Music":"Música","Loading ...":"Carregando...","Previous":"Anterior","Play":"Reproduzir","Pause":"Paisa","Next":"Próxima","Shuffle":"Embaralhar","Repeat":"Repatir","Delete":"Eliminar","Nothing in here. Upload your music!":"Nada aqui. Enviar sua música!","Show all {{ trackcount }} songs ...":["Mostrar todas as {{ trackcount }} músicas ...","Mostrar todas as {{ trackcount }} músicas ..."],"Show less ...":"Exibição mais simples...","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Chrome só é capaz de reproduzir arquivos MP3 - veja <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>"});
    gettextCatalog.setStrings('pt_PT', {"Music":"Musica","Loading ...":"A carregar...","Previous":"Anterior","Play":"Reproduzir","Pause":"Pausa","Next":"Próxima","Shuffle":"Baralhar","Repeat":"Repetir","Delete":"Eliminar","Nothing in here. Upload your music!":"Não existe nada aqui. Carregue a sua musica!","Show all {{ trackcount }} songs ...":["Mostrar todas as {{ trackcount }} músicas ...","Mostrar todas as {{ trackcount }} músicas ..."],"Show less ...":"Mostrar menos...","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Chrome apenas é capaz de reproduzir ficheiros MP3 - Consulte a<a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>"});
    gettextCatalog.setStrings('ro', {"Music":"Muzică","Loading ...":"","Previous":"Anterior","Play":"Redă","Pause":"Pauză","Next":"Următor","Shuffle":"","Repeat":"Repetă","Delete":"Șterge","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["","",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('ru', {"Music":"Музыка","Loading ...":"Загружается...","Previous":"Предыдущий","Play":"Проиграть","Pause":"Пауза","Next":"Следующий","Shuffle":"Перемешать","Repeat":"Повтор","Delete":"Удалить","Nothing in here. Upload your music!":"Здесь ничего нет. Загрузите вашу музыку!","Show all {{ trackcount }} songs ...":["Показать {{ trackcount }} песню...","Показать все {{ trackcount }} песни...","Показать все {{ trackcount }} песен..."],"Show less ...":"Показать меньше...","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Chrome может воспроизводить только MP3 файлы - смотрите <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">вики</a>"});
    gettextCatalog.setStrings('ru_RU', {"Music":"Музыка","Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["","",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('si_LK', {"Music":"සංගීතය","Loading ...":"","Previous":"පෙර","Play":"ධාවනය","Pause":"විරාමය","Next":"ඊලඟ","Shuffle":"","Repeat":"පුනරාවර්ථන","Delete":"මකා දමන්න","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('sk', {"Music":"","Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["","",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('sk_SK', {"Music":"Hudba","Loading ...":"Nahrávam...","Previous":"Predošlá","Play":"Prehrať","Pause":"Pauza","Next":"Ďalšia","Shuffle":"Zamiešať","Repeat":"Opakovať","Delete":"Zmazať","Nothing in here. Upload your music!":"Nič tu nie je. Nahrajte si vašu hudbu!","Show all {{ trackcount }} songs ...":["Zobraziť {{ trackcount }} skladieb ...","Zobraziť {{ trackcount }} skladieb ...","Zobraziť {{ trackcount }} skladieb ..."],"Show less ...":"Zobraziť menej ...","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Chrome je schopný prehrávať len MP3 súbory - pozri <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>"});
    gettextCatalog.setStrings('sl', {"Music":"Glasba","Loading ...":"","Previous":"Predhodna","Play":"Predvajaj","Pause":"Premor","Next":"Naslednja","Shuffle":"","Repeat":"Ponovi","Delete":"Izbriši","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["","","",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('sq', {"Music":"Muzikë","Loading ...":"","Previous":"Mëparshëm","Play":"Luaj","Pause":"Pauzë","Next":"Mëpasshëm","Shuffle":"","Repeat":"Përsëritet","Delete":"Elimino","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('sr', {"Music":"Музика","Loading ...":"","Previous":"Претходна","Play":"Пусти","Pause":"Паузирај","Next":"Следећа","Shuffle":"","Repeat":"Понављај","Delete":"Обриши","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["","",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('sr@latiundefined', {"Music":"Muzika","Loading ...":"","Previous":"Prethodna","Play":"Pusti","Pause":"Pauziraj","Next":"Sledeća","Shuffle":"","Repeat":"Ponavljaj","Delete":"Obriši","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["","",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('sv', {"Music":"Musik","Loading ...":"Laddar  ...","Previous":"Föregående","Play":"Spela","Pause":"Paus","Next":"Nästa","Shuffle":"Blanda","Repeat":"Upprepa","Delete":"Radera","Nothing in here. Upload your music!":"Det finns inget här. Ladda upp din musik!","Show all {{ trackcount }} songs ...":["Visa alla {{ trackcount }} låtar ...","Visa alla {{ trackcount }} låtar ..."],"Show less ...":"Visa mindre ...","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Chrome har bara möjligheten att spela upp MP3 filer - se <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>"});
    gettextCatalog.setStrings('sw_KE', {"Music":"","Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('ta_LK', {"Music":"இசை","Loading ...":"","Previous":"முன்தைய","Play":"Play","Pause":"இடைநிறுத்துக","Next":"அடுத்த","Shuffle":"","Repeat":"மீண்டும்","Delete":"நீக்குக","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('te', {"Music":"సంగీతం","Loading ...":"","Previous":"గత","Play":"","Pause":"","Next":"తదుపరి","Shuffle":"","Repeat":"","Delete":"తొలగించు","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('th_TH', {"Music":"เพลง","Loading ...":"","Previous":"ก่อนหน้า","Play":"เล่น","Pause":"หยุดชั่วคราว","Next":"ถัดไป","Shuffle":"","Repeat":"ทำซ้ำ","Delete":"ลบ","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":"","Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('tr', {"Music":"Müzik","Loading ...":"Yükleniyor","Previous":"Önceki","Play":"Oynat","Pause":"Beklet","Next":"Sonraki","Shuffle":"Karıştır","Repeat":"Tekrar","Delete":"Sil","Nothing in here. Upload your music!":"Burada hiçbir şey yok. Müziğinizi yükleyin!","Show all {{ trackcount }} songs ...":["Tüm {{ trackcount }} şarkıyı göster ...","Tüm {{ trackcount }} şarkıyı göster ..."],"Show less ...":"Daha az göster ...","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Chrome, sadece MP3 dosyalarını oynatabilir - bkz. <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">viki</a>"});
    gettextCatalog.setStrings('tzm', {"Music":"","Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('ug', {"Music":"نەغمە","Loading ...":"","Previous":"ئالدىنقى","Play":"چال","Pause":"ۋاقىتلىق توختا","Next":"كېيىنكى","Shuffle":"","Repeat":"قايتىلا","Delete":"ئۆچۈر","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":"","Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('uk', {"Music":"Музика","Loading ...":"Завантаження ...","Previous":"Попередній","Play":"Грати","Pause":"Пауза","Next":"Наступний","Shuffle":"","Repeat":"Повторювати","Delete":"Видалити","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["","",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('ur_PK', {"Music":"","Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('vi', {"Music":"Âm nhạc","Loading ...":"","Previous":"Lùi lại","Play":"Play","Pause":"Tạm dừng","Next":"Kế tiếp","Shuffle":"","Repeat":"Lặp lại","Delete":"Xóa","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":"","Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('zh_CN', {"Music":"音乐","Loading ...":"","Previous":"前一首","Play":"播放","Pause":"暂停","Next":"下一个","Shuffle":"","Repeat":"重复","Delete":"删除","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":"","Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('zh_HK', {"Music":"音樂","Loading ...":"","Previous":"上一首","Play":"播放","Pause":"暫停","Next":"下一首","Shuffle":"","Repeat":"","Delete":"刪除","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":"","Show less ...":"","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('zh_TW', {"Music":"音樂","Loading ...":"載入中…","Previous":"上一個","Play":"播放","Pause":"暫停","Next":"下一個","Shuffle":"隨機播放","Repeat":"重覆","Delete":"刪除","Nothing in here. Upload your music!":"這裡沒有東西，上傳你的音樂！","Show all {{ trackcount }} songs ...":"顯示全部 {{ trackcount }} 首歌曲","Show less ...":"顯示更少","Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Chrome 只能播放 MP3 檔案，請見 <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>"});

}]);
/* jshint +W100 */
