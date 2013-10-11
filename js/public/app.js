
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
		templateUrl: 'main.html',
		controller: 'MainController'
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
	['$scope', '$routeParams', '$rootScope', 'playlistService', 'Audio', 'Artists', 'Restangular', 'gettext',
	function ($scope, $routeParams, $rootScope, playlistService, Audio, Artists, Restangular, gettext) {

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
		$scope.player.stopAll();
		$scope.player.destroySound('ownCloudSound');
		if(newValue !== null) {
			// switch initial state
			$scope.$parent.started = true;
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
					element.placeholder(attrs.albumart);
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
    gettextCatalog.setStrings('ach', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["",""],"Show less ...":""});
    gettextCatalog.setStrings('ady', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all {{ trackcount }} songs ...":["",""],"Show less ...":"","Chrome is just able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":""});
    gettextCatalog.setStrings('af_ZA', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["",""],"Show less ...":""});
    gettextCatalog.setStrings('ar', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["","","","","",""],"Show less ...":""});
    gettextCatalog.setStrings('be', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["","","",""],"Show less ...":""});
    gettextCatalog.setStrings('bg_BG', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["",""],"Show less ...":""});
    gettextCatalog.setStrings('bn_BD', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["",""],"Show less ...":""});
    gettextCatalog.setStrings('bs', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["","",""],"Show less ...":""});
    gettextCatalog.setStrings('ca', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["",""],"Show less ...":""});
    gettextCatalog.setStrings('cs_CZ', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["","",""],"Show less ...":""});
    gettextCatalog.setStrings('cy_GB', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["","","",""],"Show less ...":""});
    gettextCatalog.setStrings('da', {"Loading ...":"Indlæser...","Previous":"Forrige","Play":"Afspil","Pause":"Pause","Next":"Næste","Shuffle":"Bland","Repeat":"Gentag","Delete":"Slet","Nothing in here. Upload your music!":"Her er tomt. Upload din musik!","Show all [[ trackcount ]] songs ...":["Vis alle [[ trackcount ]] sange ...","Vis alle [[ trackcount ]] sange ..."],"Show less ...":"Vis færre ..."});
    gettextCatalog.setStrings('de', {"Loading ...":"Lade ...","Previous":"Zurück","Play":"Abspielen","Pause":"Anhalten","Next":"Weiter","Shuffle":"Zufallswiedergabe","Repeat":"Wiederholen","Delete":"Löschen","Nothing in here. Upload your music!":"Alles leer. Lade Deine Musik hoch!","Show all [[ trackcount ]] songs ...":["[[ trackcount ]] Lied anzeigen  ...","Alle [[ trackcount ]] Lieder anzeigen  ..."],"Show less ...":"Weniger anzeigen ..."});
    gettextCatalog.setStrings('de_AT', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["",""],"Show less ...":""});
    gettextCatalog.setStrings('de_CH', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["",""],"Show less ...":""});
    gettextCatalog.setStrings('de_DE', {"Loading ...":"Lade …","Previous":"Zurück","Play":"Abspielen","Pause":"Anhalten","Next":"Weiter","Shuffle":"Zufallswiedergabe","Repeat":"Wiederholen","Delete":"Löschen","Nothing in here. Upload your music!":"Alles leer. Laden Sie Ihre Musik hoch!","Show all [[ trackcount ]] songs ...":["[[ trackcount ]] Lied anzeigen  ...","Alle [[ trackcount ]] Lieder anzeigen  ..."],"Show less ...":"Weniger anzeigen ..."});
    gettextCatalog.setStrings('el', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["",""],"Show less ...":""});
    gettextCatalog.setStrings('en@pirate', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["",""],"Show less ...":""});
    gettextCatalog.setStrings('en_GB', {"Loading ...":"Loading...","Previous":"Previous","Play":"Play","Pause":"Pause","Next":"Next","Shuffle":"Shuffle","Repeat":"Repeat","Delete":"Delete","Nothing in here. Upload your music!":"Nothing in here. Upload your music!","Show all {{ trackcount }} songs ...":["Show all {{ trackcount }} songs...","Show all {{ trackcount }} songs..."],"Show less ...":"Show less...","Chrome is just able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Chrome is only able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>"});
    gettextCatalog.setStrings('eo', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["",""],"Show less ...":""});
    gettextCatalog.setStrings('es', {"Loading ...":"Cargando...","Previous":"Anterior","Play":"Reproducir","Pause":"Pausa","Next":"Siguiente","Shuffle":"Mezclar","Repeat":"Repetir","Delete":"Eliminar","Nothing in here. Upload your music!":"Aquí no hay nada. ¡Sube tu música!","Show all {{ trackcount }} songs ...":["Mostrar todas {{ trackcount}} canción ...","Mostrar todas {{ trackcount}} canciones ..."],"Show less ...":"Mostrar menos...","Chrome is just able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Chrome puede reproducir directamente archivos MP3 - ver <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>"});
    gettextCatalog.setStrings('es_AR', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["",""],"Show less ...":""});
    gettextCatalog.setStrings('es_MX', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["",""],"Show less ...":""});
    gettextCatalog.setStrings('et_EE', {"Loading ...":"Laadimine ...","Previous":"Eelmine","Play":"Esita","Pause":"Paus","Next":"Järgmine","Shuffle":"Juhuslik esitus","Repeat":"Korda","Delete":"Kustuta","Nothing in here. Upload your music!":"Siin pole midagi. Laadi oma muusikat üles!","Show all {{ trackcount }} songs ...":["Näita {{ trackcount }} lugu ...","Näita kõiki {{ trackcount }} lugu ..."],"Show less ...":"Näita vähem ...","Chrome is just able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Chromega saab lihtsalt MP3 faile mängida - vaata seda <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a> lehte."});
    gettextCatalog.setStrings('eu', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["",""],"Show less ...":""});
    gettextCatalog.setStrings('fa', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":"","Show less ...":""});
    gettextCatalog.setStrings('fi_FI', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["",""],"Show less ...":""});
    gettextCatalog.setStrings('fr', {"Loading ...":"Chargement en cours…","Previous":"Précédent","Play":"Lire","Pause":"Pause","Next":"Suivant","Shuffle":"Lecture aléatoire","Repeat":"Répéter","Delete":"Supprimer","Nothing in here. Upload your music!":"Il n'y a rien ici ! Envoyez donc votre musique :)","Show all {{ trackcount }} songs ...":["Afficher le morceau {{ trackcount }}...","Afficher les {{ trackcount }} morceaux..."],"Show less ...":"Afficher moins…","Chrome is just able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Chrome n'est capable de jouer que les fichiers MP3 - voir le <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>"});
    gettextCatalog.setStrings('gl', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["",""],"Show less ...":""});
    gettextCatalog.setStrings('he', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["",""],"Show less ...":""});
    gettextCatalog.setStrings('hi', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["",""],"Show less ...":""});
    gettextCatalog.setStrings('hr', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["","",""],"Show less ...":""});
    gettextCatalog.setStrings('hu_HU', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["",""],"Show less ...":""});
    gettextCatalog.setStrings('hy', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["",""],"Show less ...":""});
    gettextCatalog.setStrings('ia', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["",""],"Show less ...":""});
    gettextCatalog.setStrings('id', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":"","Show less ...":""});
    gettextCatalog.setStrings('is', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["",""],"Show less ...":""});
    gettextCatalog.setStrings('it', {"Loading ...":"Caricamento in corso...","Previous":"Precedente","Play":"Riproduci","Pause":"Pausa","Next":"Successivo","Shuffle":"Mescola","Repeat":"Ripeti","Delete":"Elimina","Nothing in here. Upload your music!":"Non c'è niente qui. Carica la tua musica!","Show all [[ trackcount ]] songs ...":["Mostra tutti i [[ trackcount ]] brani...","Mostra tutti i [[ trackcount ]] brani..."],"Show less ...":"Mostra meno..."});
    gettextCatalog.setStrings('ja_JP', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":"","Show less ...":""});
    gettextCatalog.setStrings('ka', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":"","Show less ...":""});
    gettextCatalog.setStrings('ka_GE', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":"","Show less ...":""});
    gettextCatalog.setStrings('km', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":"","Show less ...":""});
    gettextCatalog.setStrings('kundefined', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":"","Show less ...":""});
    gettextCatalog.setStrings('ko', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":"","Show less ...":""});
    gettextCatalog.setStrings('ku_IQ', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["",""],"Show less ...":""});
    gettextCatalog.setStrings('lb', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["",""],"Show less ...":""});
    gettextCatalog.setStrings('lt_LT', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["","",""],"Show less ...":""});
    gettextCatalog.setStrings('lv', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["","",""],"Show less ...":""});
    gettextCatalog.setStrings('mk', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["",""],"Show less ...":""});
    gettextCatalog.setStrings('ml_IN', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["",""],"Show less ...":""});
    gettextCatalog.setStrings('ms_MY', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":"","Show less ...":""});
    gettextCatalog.setStrings('my_MM', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":"","Show less ...":""});
    gettextCatalog.setStrings('nb_NO', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["",""],"Show less ...":""});
    gettextCatalog.setStrings('ne', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["",""],"Show less ...":""});
    gettextCatalog.setStrings('nl', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["",""],"Show less ...":""});
    gettextCatalog.setStrings('nn_NO', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["",""],"Show less ...":""});
    gettextCatalog.setStrings('nqo', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":"","Show less ...":""});
    gettextCatalog.setStrings('oc', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["",""],"Show less ...":""});
    gettextCatalog.setStrings('pa', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["",""],"Show less ...":""});
    gettextCatalog.setStrings('pl', {"Loading ...":"Wczytuję ...","Previous":"Poprzedni","Play":"Odtwarzaj","Pause":"Wstrzymaj","Next":"Następny","Shuffle":"Losowo","Repeat":"Powtarzanie","Delete":"Usuń","Nothing in here. Upload your music!":"Brak zawartości. Proszę wysłać muzykę!","Show all {{ trackcount }} songs ...":["Pokaż wszystkie {{ trackcount }} piosenek ...","Pokaż wszystkie {{ trackcount }} piosenek ...","Pokaż wszystkie {{ trackcount }} piosenek ..."],"Show less ...":"Pokaz mniej ...","Chrome is just able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"W Chrome jest tylko możliwość odtwarzania muzyki MP3 - zobacz <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>"});
    gettextCatalog.setStrings('pt_BR', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["",""],"Show less ...":""});
    gettextCatalog.setStrings('pt_PT', {"Loading ...":"A carregar...","Previous":"Anterior","Play":"Reproduzir","Pause":"Pausa","Next":"Próxima","Shuffle":"Baralhar","Repeat":"Repetir","Delete":"Eliminar","Nothing in here. Upload your music!":"Não existe nada aqui. Carregue a sua musica!","Show all {{ trackcount }} songs ...":["Mostrar todas as {{ trackcount }} músicas ...","Mostrar todas as {{ trackcount }} músicas ..."],"Show less ...":"Mostrar menos...","Chrome is just able to playback MP3 files - see <a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>":"Chrome apenas é capaz de reproduzir ficheiros MP3 - Consulte a<a href=\"https://github.com/owncloud/music/wiki/Frequently-Asked-Questions#why-can-chromechromium-just-playback-mp3-files\">wiki</a>"});
    gettextCatalog.setStrings('ro', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["","",""],"Show less ...":""});
    gettextCatalog.setStrings('ru', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["","",""],"Show less ...":""});
    gettextCatalog.setStrings('si_LK', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["",""],"Show less ...":""});
    gettextCatalog.setStrings('sk', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["","",""],"Show less ...":""});
    gettextCatalog.setStrings('sk_SK', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["","",""],"Show less ...":""});
    gettextCatalog.setStrings('sl', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["","","",""],"Show less ...":""});
    gettextCatalog.setStrings('sq', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["",""],"Show less ...":""});
    gettextCatalog.setStrings('sr', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["","",""],"Show less ...":""});
    gettextCatalog.setStrings('sr@latiundefined', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["","",""],"Show less ...":""});
    gettextCatalog.setStrings('sv', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["",""],"Show less ...":""});
    gettextCatalog.setStrings('sw_KE', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["",""],"Show less ...":""});
    gettextCatalog.setStrings('ta_LK', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["",""],"Show less ...":""});
    gettextCatalog.setStrings('te', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["",""],"Show less ...":""});
    gettextCatalog.setStrings('th_TH', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":"","Show less ...":""});
    gettextCatalog.setStrings('tr', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["",""],"Show less ...":""});
    gettextCatalog.setStrings('ug', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":"","Show less ...":""});
    gettextCatalog.setStrings('uk', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["","",""],"Show less ...":""});
    gettextCatalog.setStrings('ur_PK', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":["",""],"Show less ...":""});
    gettextCatalog.setStrings('vi', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":"","Show less ...":""});
    gettextCatalog.setStrings('zh_CN', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":"","Show less ...":""});
    gettextCatalog.setStrings('zh_HK', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":"","Show less ...":""});
    gettextCatalog.setStrings('zh_TW', {"Loading ...":"","Previous":"","Play":"","Pause":"","Next":"","Shuffle":"","Repeat":"","Delete":"","Nothing in here. Upload your music!":"","Show all [[ trackcount ]] songs ...":"","Show less ...":""});

}]);
