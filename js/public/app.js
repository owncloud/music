
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

	$routeProvider.when('/', {
		templateUrl: 'main.html'
	}).when('/file/:id', {
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
	['$scope', '$routeParams', '$rootScope', 'playlistService', 'Audio', 'Artists', 'Restangular', 'gettext', 'gettextCatalog',
	function ($scope, $routeParams, $rootScope, playlistService, Audio, Artists, Restangular, gettext, gettextCatalog) {

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
		$scope.playFile($routeParams.id);
		if($scope.$parent.started) {
			// invoke play after the flash gets unblocked
			$scope.$apply(function(){
				$scope.next();
			});
		}
	});

	$rootScope.$on('$routeChangeSuccess', function() {
		$scope.playFile($routeParams.id);
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
				var str = gettext(
					'Chrome is only able to play MP3 files - see <a href="https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files">wiki</a>'
				);
				// TODO inject this
				OC.Notification.showHtml(gettextCatalog.getString(str));
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
    gettextCatalog.setStrings('ach', {});
    gettextCatalog.setStrings('ady', {});
    gettextCatalog.setStrings('af', {});
    gettextCatalog.setStrings('af_ZA', {});
    gettextCatalog.setStrings('ak', {});
    gettextCatalog.setStrings('ar', {"Delete":"إلغاء","Loading ...":"تحميل ...","Music":"الموسيقى","Next":"التالي","Nothing in here. Upload your music!":"لا يوجد شيء هنا. إرفع بعض الموسيقى!","Pause":"تجميد","Play":"إلعب","Previous":"السابق","Repeat":"إعادة","Show less ...":"اعرض اقل ...","Shuffle":"عشوائي","Unknown album":"البوم غير معروف","Unknown artist":"فنان غير معروف"});
    gettextCatalog.setStrings('az', {});
    gettextCatalog.setStrings('be', {});
    gettextCatalog.setStrings('bg_BG', {"Delete":"Изтриване","Loading ...":"Зареждане ...","Music":"Музика","Next":"Следваща","Nothing in here. Upload your music!":"Няма нищо тук. Качете си музиката!","Pause":"Пауза","Play":"Пусни","Previous":"Предишна","Repeat":"Повтори","Show less ...":"Покажи по-малко ...","Shuffle":"Разбъркване","Unknown album":"Непознат албум","Unknown artist":"Непознат изпълнител"});
    gettextCatalog.setStrings('bn_BD', {"Delete":"মুছে","Music":"গানবাজনা","Next":"পরবর্তী","Pause":"বিরতি","Play":"বাজাও","Previous":"পূর্ববর্তী","Repeat":"পূনঃসংঘটন"});
    gettextCatalog.setStrings('bs', {"Next":"Sljedeći"});
    gettextCatalog.setStrings('ca', {"Delete":"Esborra","Loading ...":"Carregant...","Music":"Música","Next":"Següent","Nothing in here. Upload your music!":"Res per aquí. Pugeu la vostra música!","Pause":"Pausa","Play":"Reprodueix","Previous":"Anterior","Repeat":"Repeteix","Show all {{ trackcount }} songs ...":["Mostra totes les {{ trackcount }} peces...","Mostra totes les {{ trackcount }} peces..."],"Show less ...":"Mostra'n menys...","Shuffle":"Aleatori","Unknown album":"Àlbum desconegut","Unknown artist":"Artista desconegut"});
    gettextCatalog.setStrings('cs_CZ', {"Delete":"Smazat","Loading ...":"Načítám ...","Music":"Hudba","Next":"Následující","Nothing in here. Upload your music!":"Zde nic není. Nahrajte vaši hudbu!","Pause":"Pozastavit","Play":"Přehrát","Previous":"Předchozí","Repeat":"Opakovat","Show all {{ trackcount }} songs ...":["Zobrazit {{ trackcount }} písničku ...","Zobrazit {{ trackcount }} písničky ...","Zobrazit {{ trackcount }} písniček ..."],"Show less ...":"Zobrazit méně ...","Shuffle":"Promíchat","Unknown album":"Neznámé album","Unknown artist":"Neznámý umělec"});
    gettextCatalog.setStrings('cy_GB', {"Delete":"Dileu","Music":"Cerddoriaeth","Next":"Nesaf","Pause":"Seibio","Play":"Chwarae","Previous":"Blaenorol","Repeat":"Ailadrodd"});
    gettextCatalog.setStrings('da', {"Delete":"Slet","Loading ...":"Indlæser...","Music":"Musik","Next":"Næste","Nothing in here. Upload your music!":"Her er tomt. Upload din musik!","Pause":"Pause","Play":"Afspil","Previous":"Forrige","Repeat":"Gentag","Show all {{ trackcount }} songs ...":["Vis alle {{ trackcount }} sange ...","Vis alle {{ trackcount }} sange ..."],"Show less ...":"Vis færre ...","Shuffle":"Bland","Unknown album":"Ukendt album","Unknown artist":"Ukendt artist"});
    gettextCatalog.setStrings('de', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Chrome ist nur in der Lage, MP3-Dateien wiederzugeben - siehe in das <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">Wiki</a>","Delete":"Löschen","Loading ...":"Lade ...","Music":"Musik","Next":"Weiter","Nothing in here. Upload your music!":"Alles leer. Lade Deine Musik hoch!","Pause":"Anhalten","Play":"Abspielen","Previous":"Zurück","Repeat":"Wiederholen","Show all {{ trackcount }} songs ...":["{{ trackcount }} Lied anzeigen  ...","Alle {{ trackcount }} Lieder anzeigen  ..."],"Show less ...":"Weniger anzeigen ...","Shuffle":"Zufallswiedergabe","Unknown album":"Unbekanntes Album","Unknown artist":"Unbekannter Künstler"});
    gettextCatalog.setStrings('de_AT', {"Delete":"Löschen","Loading ...":"Lade ...","Music":"Musik","Next":"Nächstes","Nothing in here. Upload your music!":"Alles leer. Lade deine Musik hoch!","Pause":"Pause","Play":"Abspielen","Previous":"Vorheriges","Repeat":"Wiederholen","Show all {{ trackcount }} songs ...":["{{ trackcount }} Lied anzeigen ...","Alle {{ trackcount }} Lieder anzeigen ..."],"Show less ...":"Weniger anzeigen ...","Shuffle":"Zufallswiedergabe","Unknown album":"Unbekanntes Album","Unknown artist":"Unbekannter Künstler"});
    gettextCatalog.setStrings('de_CH', {"Delete":"Löschen","Music":"Musik","Next":"Weiter","Pause":"Anhalten","Play":"Abspielen","Previous":"Vorheriges","Repeat":"Wiederholen"});
    gettextCatalog.setStrings('de_DE', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Chrome ist nur in der Lage, MP3-Dateien wiederzugeben - siehe in das <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">Wiki</a>","Delete":"Löschen","Loading ...":"Lade …","Music":"Musik","Next":"Weiter","Nothing in here. Upload your music!":"Alles leer. Laden Sie Ihre Musik hoch!","Pause":"Anhalten","Play":"Abspielen","Previous":"Zurück","Repeat":"Wiederholen","Show all {{ trackcount }} songs ...":["{{ trackcount }} Lied anzeigen  ...","Alle {{ trackcount }} Lieder anzeigen  ..."],"Show less ...":"Weniger anzeigen ...","Shuffle":"Zufallswiedergabe","Unknown album":"Unbekanntes Album","Unknown artist":"Unbekannter Künstler"});
    gettextCatalog.setStrings('el', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Ο Chrome μπορεί να αναπαράγει μόνο αρχεία MP3 - δείτε το <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>","Delete":"Διαγραφή","Loading ...":"Φόρτωση ...","Music":"Μουσική","Next":"Επόμενο","Nothing in here. Upload your music!":"Δεν υπάρχει τίποτα εδώ. Μεταφορτώστε την μουσική σας!","Pause":"Παύση","Play":"Αναπαραγωγή","Previous":"Προηγούμενο","Repeat":"Επανάληψη","Show all {{ trackcount }} songs ...":["Εμφάνιση του τραγουδιού","Εμφάνιση όλων των {{ trackcount }} τραγουδιών ..."],"Show less ...":"Προβολή λιγότερων...","Shuffle":"Τυχαία αναπαραγωγή","Unknown album":"Άγνωστο άλμπουμ","Unknown artist":"Άγνωστος καλλιτέχνης"});
    gettextCatalog.setStrings('en@pirate', {"Music":"Music","Pause":"Pause"});
    gettextCatalog.setStrings('en_GB', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>","Delete":"Delete","Loading ...":"Loading...","Music":"Music","Next":"Next","Nothing in here. Upload your music!":"Nothing in here. Upload your music!","Pause":"Pause","Play":"Play","Previous":"Previous","Repeat":"Repeat","Show all {{ trackcount }} songs ...":["Show all {{ trackcount }} songs...","Show all {{ trackcount }} songs..."],"Show less ...":"Show less...","Shuffle":"Shuffle","Unknown album":"Unknown album","Unknown artist":"Unknown artist"});
    gettextCatalog.setStrings('eo', {"Delete":"Forigi","Loading ...":"Ŝargante...","Music":"Muziko","Next":"Jena","Nothing in here. Upload your music!":"Nenio estas ĉi tie. Alŝutu vian muzikon!","Pause":"Paŭzi...","Play":"Ludi","Previous":"Maljena","Repeat":"Ripeti","Show all {{ trackcount }} songs ...":["Montri ĉiujn {{ trackcount }} kantaĵojn...","Montri ĉiujn {{ trackcount }} kantaĵojn..."],"Show less ...":"Montri malpli...","Shuffle":"Miksi"});
    gettextCatalog.setStrings('es', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Chrome sólo puede reproducir archivos MP3 - vea el <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>","Delete":"Eliminar","Loading ...":"Cargando...","Music":"Música","Next":"Siguiente","Nothing in here. Upload your music!":"Aquí no hay nada. ¡Sube tu música!","Pause":"Pausa","Play":"Reproducir","Previous":"Anterior","Repeat":"Repetir","Show all {{ trackcount }} songs ...":["Mostrar todas las {{ trackcount}} canciones...","Mostrar todas las {{ trackcount }} canciones..."],"Show less ...":"Mostrar menos...","Shuffle":"Mezclar","Unknown album":"Álbum desconocido","Unknown artist":"Artista desconocido"});
    gettextCatalog.setStrings('es_AR', {"Delete":"Borrar","Loading ...":"Cargando...","Music":"Música","Next":"Siguiente","Nothing in here. Upload your music!":"No hay nada aquí. ¡Suba su música!","Pause":"Pausar","Play":"Reproducir","Previous":"Previo","Repeat":"Repetir","Show all {{ trackcount }} songs ...":["Mostrar la única canción...","Mostrar las {{ trackcount }} canciones..."],"Show less ...":"Mostrar menos...","Shuffle":"Aleatorio","Unknown album":"Album desconocido","Unknown artist":"Artista desconocido"});
    gettextCatalog.setStrings('es_CL', {});
    gettextCatalog.setStrings('es_MX', {"Delete":"Eliminar","Loading ...":"Cargando ...","Music":"Música","Next":"Siguiente","Nothing in here. Upload your music!":"Aquí no hay nada. ¡Sube tu música!","Pause":"Pausa","Play":"Reproducir","Previous":"Anterior","Repeat":"Repetir","Show all {{ trackcount }} songs ...":["Mostrar todas las {{ trackcount}} canciones ...","Mostrar todas las {{ trackcount }} canciones ..."],"Show less ...":"Mostrar menos ...","Shuffle":"Mezclar"});
    gettextCatalog.setStrings('et_EE', {"Delete":"Kustuta","Loading ...":"Laadimine ...","Music":"Muusika","Next":"Järgmine","Nothing in here. Upload your music!":"Siin pole midagi. Laadi oma muusikat üles!","Pause":"Paus","Play":"Esita","Previous":"Eelmine","Repeat":"Korda","Show all {{ trackcount }} songs ...":["Näita {{ trackcount }} lugu ...","Näita kõiki {{ trackcount }} lugu ..."],"Show less ...":"Näita vähem ...","Shuffle":"Juhuslik esitus","Unknown album":"Tundmatu album","Unknown artist":"Tundmatu esitaja"});
    gettextCatalog.setStrings('eu', {"Delete":"Ezabatu","Loading ...":"Kargatzen...","Music":"Musika","Next":"Hurrengoa","Nothing in here. Upload your music!":"Ez dago ezer. Igo zure musika!","Pause":"Pausarazi","Play":"Erreproduzitu","Previous":"Aurrekoa","Repeat":"Errepikatu","Show all {{ trackcount }} songs ...":["Bistaratu {{ trackcount}} abesti guztiak ...","Bistaratu {{ trackcount}} abesti guztiak ..."],"Show less ...":"Bistaratu gutxiago...","Shuffle":"Nahastu","Unknown album":"Diska ezezaguna","Unknown artist":"Artista ezezaguna"});
    gettextCatalog.setStrings('eu_ES', {"Delete":"Ezabatu","Music":"Musika","Next":"Aurrera","Pause":"geldi","Play":"jolastu","Previous":"Atzera","Repeat":"Errepikatu"});
    gettextCatalog.setStrings('fa', {"Delete":"حذف","Music":"موزیک","Next":"بعدی","Pause":"توقف کردن","Play":"پخش کردن","Previous":"قبلی","Repeat":"تکرار"});
    gettextCatalog.setStrings('fi_FI', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Chrome kykenee toistamaan vain MP3-tiedostoja - lue lisää <a href=\\\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\\\">wikistä</a>","Delete":"Poista","Loading ...":"Ladataan...","Music":"Musiikki","Next":"Seuraava","Nothing in here. Upload your music!":"Täällä ei ole mitään. Lähetä musiikkia tänne!","Pause":"Keskeytä","Play":"Toista","Previous":"Edellinen","Repeat":"Kertaa","Show all {{ trackcount }} songs ...":["Näytä {{ trackcount }} kappale...","Näytä kaikki {{ trackcount }} kappaletta..."],"Show less ...":"Näytä vähemmän...","Shuffle":"Sekoita","Unknown album":"Tuntematon levy","Unknown artist":"Tuntematon esittäjä"});
    gettextCatalog.setStrings('fr', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Chrome n'est capable de jouer que des fichiers MP3 uniquement - voir le <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>","Delete":"Supprimer","Loading ...":"Chargement…","Music":"Musique","Next":"Suivant","Nothing in here. Upload your music!":"Il n'y a rien ici ! Envoyez donc votre musique :)","Pause":"Pause","Play":"Lire","Previous":"Précédent","Repeat":"Répéter","Show all {{ trackcount }} songs ...":["Afficher le morceau {{ trackcount }}...","Afficher les {{ trackcount }} morceaux..."],"Show less ...":"Afficher moins…","Shuffle":"Lecture aléatoire","Unknown album":"Album inconnu","Unknown artist":"Artiste inconnu"});
    gettextCatalog.setStrings('fr_CA', {});
    gettextCatalog.setStrings('gl', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Chrome só é quen de reproducir ficheiros MP3 - vexa o <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>","Delete":"Eliminar","Loading ...":"Cargando ...","Music":"Música","Next":"Seguinte","Nothing in here. Upload your music!":"Aquí non hai nada. Envíe a súa música!","Pause":"Pausa","Play":"Reproducir","Previous":"Anterior","Repeat":"Repetir","Show all {{ trackcount }} songs ...":["Amosar todas as {{ trackcount }} cancións ...","Amosar todas as {{ trackcount }} cancións ..."],"Show less ...":"Amosar menos ...","Shuffle":"Ao chou","Unknown album":"Album descoñecido","Unknown artist":"Interprete descoñecido"});
    gettextCatalog.setStrings('he', {"Delete":"מחיקה","Loading ...":"טוען...","Music":"מוזיקה","Next":"הבא","Nothing in here. Upload your music!":"אין כאן שום דבר. אולי ברצונך להעלות משהו?","Pause":"השהה","Play":"נגן","Previous":"קודם","Repeat":"חזרה","Show all {{ trackcount }} songs ...":["הצג את כל {{trackcount}} שירים","הצג את כל {{trackcount}} שירים..."],"Show less ...":"הצג פחות...","Shuffle":"ערבב","Unknown album":"אלבום לא ידוע","Unknown artist":"אמן לא ידוע"});
    gettextCatalog.setStrings('hi', {});
    gettextCatalog.setStrings('hr', {"Delete":"Obriši","Music":"Muzika","Next":"Sljedeća","Pause":"Pauza","Play":"Reprodukcija","Previous":"Prethodna","Repeat":"Ponavljanje"});
    gettextCatalog.setStrings('hu_HU', {"Delete":"Törlés","Loading ...":"Betöltés ...","Music":"Zene","Next":"Következő","Nothing in here. Upload your music!":"Nincs itt semmi. Töltsd fel a zenédet!","Pause":"Szünet","Play":"Lejátszás","Previous":"Előző","Repeat":"Ismétlés","Show all {{ trackcount }} songs ...":["Mutasd mind a {{ trackcount }} zenét ...","Mutasd mind a {{ trackcount }} zenét ..."],"Show less ...":"Mutass kevesebbet...","Shuffle":"Keverés","Unknown album":"Ismeretlen album","Unknown artist":"Ismeretlen előadó"});
    gettextCatalog.setStrings('hy', {"Delete":"Ջնջել"});
    gettextCatalog.setStrings('ia', {"Delete":"Deler","Music":"Musica","Next":"Proxime","Pause":"Pausa","Play":"Reproducer","Previous":"Previe","Repeat":"Repeter"});
    gettextCatalog.setStrings('id', {"Delete":"Hapus","Loading ...":"Memuat ...","Music":"Musik","Next":"Selanjutnya","Nothing in here. Upload your music!":"Tidak ada apapun disini. Unggah musik anda!","Pause":"Jeda","Play":"Putar","Previous":"Sebelumnya","Repeat":"Ulangi","Show all {{ trackcount }} songs ...":"Menampilkan semua {{ trackcount }} lagu ...","Show less ...":"Menampilkan ringkas ...","Shuffle":"Acak"});
    gettextCatalog.setStrings('is', {"Delete":"Eyða","Music":"Tónlist","Next":"Næst","Pause":"Pása","Play":"Spila","Previous":"Fyrra"});
    gettextCatalog.setStrings('it', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Chrome è in grado di riprodurre solo file MP3 - vedi il <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>","Delete":"Elimina","Loading ...":"Caricamento in corso...","Music":"Musica","Next":"Successivo","Nothing in here. Upload your music!":"Non c'è niente qui. Carica la tua musica!","Pause":"Pausa","Play":"Riproduci","Previous":"Precedente","Repeat":"Ripeti","Show all {{ trackcount }} songs ...":["Mostra {{ trackcount }} brano...","Mostra tutti i {{ trackcount }} brani..."],"Show less ...":"Mostra meno...","Shuffle":"Mescola","Unknown album":"Album sconosciuto","Unknown artist":"Artista sconosciuto"});
    gettextCatalog.setStrings('ja_JP', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Chrome は MP3 ファイルの再生のみ可能です - <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a> を参照してください。","Delete":"削除","Loading ...":"読込中 ...","Music":"ミュージック","Next":"次","Nothing in here. Upload your music!":"ここには何もありません。ミュージックをアップロードしてください！","Pause":"一時停止","Play":"再生","Previous":"前","Repeat":"繰り返し","Show all {{ trackcount }} songs ...":"すべての {{ trackcount }} 曲を表示 ...","Show less ...":"簡略表示 ...","Shuffle":"シャッフル","Unknown album":"不明なアルバム","Unknown artist":"不明なアーティスト"});
    gettextCatalog.setStrings('ka', {});
    gettextCatalog.setStrings('ka_GE', {"Delete":"წაშლა","Music":"მუსიკა","Next":"შემდეგი","Pause":"პაუზა","Play":"დაკვრა","Previous":"წინა","Repeat":"გამეორება"});
    gettextCatalog.setStrings('km', {"Delete":"លុប","Loading ...":"កំពុងផ្ទុក ...","Music":"តន្ត្រី","Next":"បន្ទាប់","Nothing in here. Upload your music!":"គ្មាន​អ្វីទេ​នៅទីនេះ។ ដាក់តន្ត្រី​របស់​អ្នកឡើង!","Pause":"ផ្អាក","Play":"លេង","Previous":"មុន","Repeat":"ធ្វើម្ដងទៀត","Show all {{ trackcount }} songs ...":"បង្ហាញ​ទាំង {{ trackcount }} ចម្រៀង ...","Show less ...":"បង្ហាញ​តិច ...","Shuffle":"បង្អូស","Unknown album":"អាល់ប៊ុមអត់​ឈ្មោះ","Unknown artist":"សិល្បករអត់​ឈ្មោះ"});
    gettextCatalog.setStrings('kn', {});
    gettextCatalog.setStrings('ko', {"Delete":"삭제","Loading ...":"불러오는 중...","Music":"음악","Next":"다음","Nothing in here. Upload your music!":"아무 것도 없습니다. 음악을 업로드하십시오!","Pause":"일시 정지","Play":"재생","Previous":"이전","Repeat":"반복","Show all {{ trackcount }} songs ...":"모든 {{ trackcount }} 곡 보기...","Show less ...":"덜 보기...","Shuffle":"임의 재생","Unknown album":"알려지지 않은 앨범","Unknown artist":"알려지지 않은 아티스트"});
    gettextCatalog.setStrings('ku_IQ', {"Music":"مۆسیقا","Next":"دوواتر","Pause":"وه‌ستان","Play":"لێدان","Previous":"پێشووتر"});
    gettextCatalog.setStrings('lb', {"Delete":"Läschen","Music":"Musek","Next":"Weider","Pause":"Paus","Play":"Ofspillen","Previous":"Zeréck","Repeat":"Widderhuelen"});
    gettextCatalog.setStrings('lt_LT', {"Delete":"Ištrinti","Loading ...":"Įkeliama ...","Music":"Muzika","Next":"Kitas","Nothing in here. Upload your music!":"Nieko nėra. Įkelkite sava muziką!","Pause":"Pristabdyti","Play":"Groti","Previous":"Ankstesnis","Repeat":"Kartoti","Show all {{ trackcount }} songs ...":["Rodyti visas {{ trackcount }} dainas ...","Rodyti visas {{ trackcount }} dainas ...","Rodyti visas {{ trackcount }} dainas ..."],"Show less ...":"Rodyti mažiau ...","Shuffle":"Maišyti","Unknown album":"Nežinomas albumas","Unknown artist":"Nežinomas atlikėjas"});
    gettextCatalog.setStrings('lv', {"Delete":"Dzēst","Music":"Mūzika","Next":"Nākamā","Pause":"Pauzēt","Play":"Atskaņot","Previous":"Iepriekšējā","Repeat":"Atkārtot"});
    gettextCatalog.setStrings('mk', {"Delete":"Избриши","Loading ...":"Вчитувам ...","Music":"Музика","Next":"Следно","Nothing in here. Upload your music!":"Тука нема ништо. Ставете своја музика!","Pause":"Пауза","Play":"Пушти","Previous":"Претходно","Repeat":"Повтори","Show all {{ trackcount }} songs ...":["Прикажи ги сите {{ trackcount }} песни ...","Прикажи ги сите {{ trackcount }} песни ..."],"Show less ...":"Прикажи помалку ...","Shuffle":"Помешај"});
    gettextCatalog.setStrings('ml', {});
    gettextCatalog.setStrings('ml_IN', {"Music":"സംഗീതം"});
    gettextCatalog.setStrings('mn', {});
    gettextCatalog.setStrings('ms_MY', {"Delete":"Padam","Loading ...":"Memuatkan ...","Music":"Muzik","Next":"Seterus","Nothing in here. Upload your music!":"Tiada apa-apa di sini. Muat naik muzik anda!","Pause":"Jeda","Play":"Main","Previous":"Sebelum","Repeat":"Ulang","Show all {{ trackcount }} songs ...":"Paparkan semua {{ trackcount }} lagu ...","Show less ...":"Kurangkan paparan ...","Shuffle":"Kocok"});
    gettextCatalog.setStrings('my_MM', {});
    gettextCatalog.setStrings('nb_NO', {"Delete":"Slett","Loading ...":"Laster ...","Music":"Musikk","Next":"Neste","Nothing in here. Upload your music!":"Ingenting her. Last opp musikken din!","Pause":"Pause","Play":"Spill","Previous":"Forrige","Repeat":"Gjenta","Show all {{ trackcount }} songs ...":["Vis alle {{ trackcount }} sanger ...","Vis alle {{ trackcount }} sanger ..."],"Show less ...":"Vis mindre ...","Shuffle":"Tilfeldig","Unknown album":"Ukjent album","Unknown artist":"Ukjent artist"});
    gettextCatalog.setStrings('nds', {});
    gettextCatalog.setStrings('ne', {});
    gettextCatalog.setStrings('nl', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Chrome kan alleen MP3 bestanden - zie <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>","Delete":"Verwijder","Loading ...":"Laden ...","Music":"Muziek","Next":"Volgende","Nothing in here. Upload your music!":"Nog niets aanwezig. Upload uw muziek!","Pause":"Pause","Play":"Afspelen","Previous":"Vorige","Repeat":"Herhaling","Show all {{ trackcount }} songs ...":["Toon elk {{ trackcount }} nummer ...","Toon alle {{ trackcount }} nummers ..."],"Show less ...":"Toon minder ...","Shuffle":"Shuffle","Unknown album":"Onbekend album","Unknown artist":"Onbekende artiest"});
    gettextCatalog.setStrings('nn_NO', {"Delete":"Slett","Music":"Musikk","Next":"Neste","Pause":"Pause","Play":"Spel","Previous":"Førre","Repeat":"Gjenta"});
    gettextCatalog.setStrings('nqo', {});
    gettextCatalog.setStrings('oc', {"Delete":"Escafa","Music":"Musica","Next":"Venent","Pause":"Pausa","Play":"Fai tirar","Previous":"Darrièr","Repeat":"Torna far"});
    gettextCatalog.setStrings('pa', {"Delete":"ਹਟਾਓ","Music":"ਸੰਗੀਤ"});
    gettextCatalog.setStrings('pl', {"Delete":"Usuń","Loading ...":"Wczytuję ...","Music":"Muzyka","Next":"Następny","Nothing in here. Upload your music!":"Brak zawartości. Proszę wysłać muzykę!","Pause":"Wstrzymaj","Play":"Odtwarzaj","Previous":"Poprzedni","Repeat":"Powtarzanie","Show all {{ trackcount }} songs ...":["Pokaż wszystkie {{ trackcount }} piosenek ...","Pokaż wszystkie {{ trackcount }} piosenek ...","Pokaż wszystkie {{ trackcount }} piosenek ..."],"Show less ...":"Pokaz mniej ...","Shuffle":"Losowo","Unknown album":"Nieznany album","Unknown artist":"Nieznany artysta"});
    gettextCatalog.setStrings('pt_BR', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Chrome só é capaz de reproduzir arquivos MP3 - veja <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>","Delete":"Eliminar","Loading ...":"Carregando...","Music":"Música","Next":"Próxima","Nothing in here. Upload your music!":"Nada aqui. Enviar sua música!","Pause":"Paisa","Play":"Reproduzir","Previous":"Anterior","Repeat":"Repatir","Show all {{ trackcount }} songs ...":["Mostrar todas as {{ trackcount }} músicas ...","Mostrar todas as {{ trackcount }} músicas ..."],"Show less ...":"Exibição mais simples...","Shuffle":"Embaralhar","Unknown album":"Album desconhecido","Unknown artist":"Artista desconhecido"});
    gettextCatalog.setStrings('pt_PT', {"Delete":"Eliminar","Loading ...":"A carregar...","Music":"Musica","Next":"Próxima","Nothing in here. Upload your music!":"Não existe nada aqui. Carregue a sua musica!","Pause":"Pausa","Play":"Reproduzir","Previous":"Anterior","Repeat":"Repetir","Show all {{ trackcount }} songs ...":["Mostrar todas as {{ trackcount }} músicas ...","Mostrar todas as {{ trackcount }} músicas ..."],"Show less ...":"Mostrar menos...","Shuffle":"Baralhar","Unknown album":"Álbum desconhecido","Unknown artist":"Artista desconhecido"});
    gettextCatalog.setStrings('ro', {"Delete":"Șterge","Music":"Muzică","Next":"Următor","Pause":"Pauză","Play":"Redă","Previous":"Anterior","Repeat":"Repetă"});
    gettextCatalog.setStrings('ru', {"Delete":"Удалить","Loading ...":"Загружается...","Music":"Музыка","Next":"Следующий","Nothing in here. Upload your music!":"Здесь ничего нет. Загрузите вашу музыку!","Pause":"Пауза","Play":"Проиграть","Previous":"Предыдущий","Repeat":"Повтор","Show all {{ trackcount }} songs ...":["Показать {{ trackcount }} песню...","Показать все {{ trackcount }} песни...","Показать все {{ trackcount }} песен..."],"Show less ...":"Показать меньше...","Shuffle":"Перемешать","Unknown album":"Неизвестный альбом","Unknown artist":"Неизвестный исполнитель"});
    gettextCatalog.setStrings('ru_RU', {"Delete":"Удалить","Music":"Музыка"});
    gettextCatalog.setStrings('si_LK', {"Delete":"මකා දමන්න","Music":"සංගීතය","Next":"ඊලඟ","Pause":"විරාමය","Play":"ධාවනය","Previous":"පෙර","Repeat":"පුනරාවර්ථන"});
    gettextCatalog.setStrings('sk', {"Delete":"Odstrániť","Repeat":"Opakovať"});
    gettextCatalog.setStrings('sk_SK', {"Delete":"Zmazať","Loading ...":"Nahrávam...","Music":"Hudba","Next":"Ďalšia","Nothing in here. Upload your music!":"Nič tu nie je. Nahrajte si vašu hudbu!","Pause":"Pauza","Play":"Prehrať","Previous":"Predošlá","Repeat":"Opakovať","Show all {{ trackcount }} songs ...":["Zobraziť {{ trackcount }} skladieb ...","Zobraziť {{ trackcount }} skladieb ...","Zobraziť všetky {{ trackcount }} skladieb ..."],"Show less ...":"Zobraziť menej ...","Shuffle":"Zamiešať","Unknown album":"Neznámy album","Unknown artist":"Neznámy umelec"});
    gettextCatalog.setStrings('sl', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Brskalnik Chrome lahko predvaja le datoteke MP3 - več podrobnosti je na <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">straneh wiki</a>.","Delete":"Izbriši","Loading ...":"Nalaganje ...","Music":"Glasba","Next":"Naslednja","Nothing in here. Upload your music!":"V mapi ni glasbenih datotek. Dodajte skladbe!","Pause":"Premor","Play":"Predvajaj","Previous":"Predhodna","Repeat":"Ponovi","Show all {{ trackcount }} songs ...":["Pokaži {{ trackcount }} posnetek ...","Pokaži {{ trackcount }} posnetka ...","Pokaži {{ trackcount }} posnetke ...","Pokaži {{ trackcount }} posnetkov ..."],"Show less ...":"Pokaži manj ...","Shuffle":"Premešaj","Unknown album":"Neznan album","Unknown artist":"Neznan izvajalec"});
    gettextCatalog.setStrings('sq', {"Delete":"Elimino","Loading ...":"Ngarkim...","Music":"Muzikë","Next":"Mëpasshëm","Nothing in here. Upload your music!":"Këtu nuk ka asgjë. Ngarkoni muziken tuaj!","Pause":"Pauzë","Play":"Luaj","Previous":"Mëparshëm","Repeat":"Përsëritet","Show less ...":"Shfaq m'pak...","Shuffle":"Përziej"});
    gettextCatalog.setStrings('sr', {"Delete":"Обриши","Music":"Музика","Next":"Следећа","Pause":"Паузирај","Play":"Пусти","Previous":"Претходна","Repeat":"Понављај"});
    gettextCatalog.setStrings('sr@latin', {"Delete":"Obriši","Music":"Muzika","Next":"Sledeća","Pause":"Pauziraj","Play":"Pusti","Previous":"Prethodna","Repeat":"Ponavljaj"});
    gettextCatalog.setStrings('su', {});
    gettextCatalog.setStrings('sv', {"Delete":"Radera","Loading ...":"Laddar  ...","Music":"Musik","Next":"Nästa","Nothing in here. Upload your music!":"Det finns inget här. Ladda upp din musik!","Pause":"Paus","Play":"Spela","Previous":"Föregående","Repeat":"Upprepa","Show all {{ trackcount }} songs ...":["Visa alla {{ trackcount }} låtar ...","Visa alla {{ trackcount }} låtar ..."],"Show less ...":"Visa mindre ...","Shuffle":"Blanda"});
    gettextCatalog.setStrings('sw_KE', {});
    gettextCatalog.setStrings('ta_LK', {"Delete":"நீக்குக","Music":"இசை","Next":"அடுத்த","Pause":"இடைநிறுத்துக","Play":"Play","Previous":"முன்தைய","Repeat":"மீண்டும்"});
    gettextCatalog.setStrings('te', {"Delete":"తొలగించు","Music":"సంగీతం","Next":"తదుపరి","Previous":"గత"});
    gettextCatalog.setStrings('th_TH', {"Delete":"ลบ","Music":"เพลง","Next":"ถัดไป","Pause":"หยุดชั่วคราว","Play":"เล่น","Previous":"ก่อนหน้า","Repeat":"ทำซ้ำ"});
    gettextCatalog.setStrings('tr', {"Chrome is only able to play MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Chrome, sadece MP3 dosyalarını oynatabilir - bkz. <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">viki</a>","Delete":"Sil","Loading ...":"Yükleniyor...","Music":"Müzik","Next":"Sonraki","Nothing in here. Upload your music!":"Burada hiçbir şey yok. Müziğinizi yükleyin!","Pause":"Beklet","Play":"Oynat","Previous":"Önceki","Repeat":"Tekrar","Show all {{ trackcount }} songs ...":["Tüm {{ trackcount }} şarkıyı göster ...","Tüm {{ trackcount }} şarkıyı göster ..."],"Show less ...":"Daha az göster ...","Shuffle":"Karıştır","Unknown album":"Bilinmeyen albüm","Unknown artist":"Bilinmeyen sanatçı"});
    gettextCatalog.setStrings('tzm', {});
    gettextCatalog.setStrings('ug', {"Delete":"ئۆچۈر","Music":"نەغمە","Next":"كېيىنكى","Pause":"ۋاقىتلىق توختا","Play":"چال","Previous":"ئالدىنقى","Repeat":"قايتىلا"});
    gettextCatalog.setStrings('uk', {"Delete":"Видалити","Loading ...":"Завантаження ...","Music":"Музика","Next":"Наступний","Nothing in here. Upload your music!":"Тут зараз нічого немає. Завантажте свою музику!","Pause":"Пауза","Play":"Грати","Previous":"Попередній","Repeat":"Повторювати","Show all {{ trackcount }} songs ...":["Показати {{ trackcount }} пісню ...","Показати всі {{ trackcount }} пісні ...","Показати всі {{ trackcount }} пісні ..."],"Show less ...":"Показати меньше ...","Shuffle":"Перемішати"});
    gettextCatalog.setStrings('ur', {});
    gettextCatalog.setStrings('ur_PK', {});
    gettextCatalog.setStrings('uz', {});
    gettextCatalog.setStrings('vi', {"Delete":"Xóa","Loading ...":"Đang tải ...","Music":"Âm nhạc","Next":"Kế tiếp","Nothing in here. Upload your music!":"Không có gì ở đây. Hãy tải nhạc của bạn lên!","Pause":"Tạm dừng","Play":"Play","Previous":"Lùi lại","Repeat":"Lặp lại","Show all {{ trackcount }} songs ...":"Hiển thị tất cả {{ trackcount }} bài hát ...","Show less ...":"Hiển thị ít hơn ...","Shuffle":"Ngẫu nhiên","Unknown album":"Không tìm thấy album","Unknown artist":"Không tìm thấy nghệ sĩ"});
    gettextCatalog.setStrings('zh_CN', {"Delete":"删除","Loading ...":"加载中...","Music":"音乐","Next":"下一个","Nothing in here. Upload your music!":"这里还什么都没有。上传你的音乐吧！","Pause":"暂停","Play":"播放","Previous":"前一首","Repeat":"重复","Show all {{ trackcount }} songs ...":"显示所有 {{ trackcount }} 首歌曲 ...","Show less ...":"显示概要","Shuffle":"随机","Unknown album":"未知专辑","Unknown artist":"未知艺术家"});
    gettextCatalog.setStrings('zh_HK', {"Delete":"刪除","Music":"音樂","Next":"下一首","Pause":"暫停","Play":"播放","Previous":"上一首"});
    gettextCatalog.setStrings('zh_TW', {"Delete":"刪除","Loading ...":"載入中…","Music":"音樂","Next":"下一個","Nothing in here. Upload your music!":"這裡沒有東西，上傳你的音樂！","Pause":"暫停","Play":"播放","Previous":"上一個","Repeat":"重覆","Show all {{ trackcount }} songs ...":"顯示全部 {{ trackcount }} 首歌曲","Show less ...":"顯示更少","Shuffle":"隨機播放"});

/* jshint +W100 */
}]);
