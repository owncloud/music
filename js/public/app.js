
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

angular.module('Music', ['restangular', 'gettext', 'ngRoute']).
	config(['RestangularProvider', function (RestangularProvider) {

	// configure RESTAngular path
	RestangularProvider.setBaseUrl('api');
}]).
	run(function(Token, Restangular){

	// add CSRF token
	Restangular.setDefaultHeaders({requesttoken: Token});

});

angular.module('Music').controller('MainController',
	['$rootScope', '$scope', 'ArtistFactory', 'playlistService', 'gettextCatalog', 'Restangular',
	function ($rootScope, $scope, ArtistFactory, playlistService, gettextCatalog, Restangular) {

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

	$scope.update = function() {
		ArtistFactory.getArtists().then(function(artists){
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

			$rootScope.$emit('artistsLoaded');
		});
	};

	// initial loading of artists
	$scope.update();


	var scanLoopFunction = function(dry) {
		Restangular.all('scan').getList({dry: dry}).then(function(scan){
			$scope.scanningScanned = scan.processed;
			$scope.scanningTotal = scan.total;
			$scope.update();
			if(scan.processed < scan.total) {
				$scope.scanning = true;
				scanLoopFunction(0);
			} else {
				if(scan.processed !== scan.total) {
					Restangular.all('log').post({message: 'Processed more files than available ' + scan.processed + '/' + scan.total });
				}
				$scope.scanning = false;
			}
		});
	};

	scanLoopFunction(1);

	$scope.scanning = false;
	$scope.scanningScanned = 0;
	$scope.scanningTotal = 0;


	$scope.play = function (type, object) {
		$scope.playRequest = {
			type: type,
			object: object
		};
		window.location.hash = '#/' + type + '/' + object.id;
	};
}]);

angular.module('Music').controller('PlayerController',
	['$scope', '$rootScope', 'playlistService', 'Audio', 'Restangular', 'gettext', 'gettextCatalog', '$filter',
	function ($scope, $rootScope, playlistService, Audio, Restangular, gettext, gettextCatalog, $filter) {

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

	$scope.eventsBeforePlaying = 2;
	$scope.$playPosition = $('.play-position');
	$scope.$bufferBar = $('.buffer-bar');
	$scope.$playBar = $('.play-bar');

	// will be invoked by the audio factory
	$rootScope.$on('SoundManagerReady', function() {
		if($rootScope.started) {
			// invoke play after the flash gets unblocked
			$scope.$apply(function(){
				$scope.next();
			});
		}
		if (!--$scope.eventsBeforePlaying) $scope.handlePlayRequest();
	});

	$rootScope.$on('artistsLoaded', function () {
		if (!--$scope.eventsBeforePlaying) $scope.handlePlayRequest();
	});

	$(window).on('hashchange', function() {
		$scope.handlePlayRequest();
		$scope.$apply();
	});

	$scope.handlePlayRequest = function() {
		if (!$scope.$parent.artists) {
			return;
		}

		var type,
			object,
			playRequest = $scope.$parent.playRequest;

		if (playRequest) {
			type = playRequest.type;
			object = playRequest.object;
			$scope.$parent.playRequest = null;
		} else {
			var hashParts = window.location.hash.substr(1).split('/');
			if (!hashParts[0] && hashParts[1] && hashParts[2]) {
				type = hashParts[1];
				var id = hashParts[2];

				if (type == 'file') {
					object = id;
				} else if (type == 'artist') {
					// search for the artist by id
					object = _.find($scope.$parent.artists, function(artist) {
						return artist.id == id;
					});
				} else {
					var albums = _.flatten(_.pluck($scope.$parent.artists, 'albums'));
					if (type == 'album') {
						// search for the album by id
						object = _.find(albums, function(album) {
							return album.id == id;
						});
					} else if (type == 'track') {
						var tracks = _.flatten(_.pluck(albums, 'tracks'));
						// search for the track by id
						object = _.find(tracks, function(track) {
							return track.id == id;
						});
					}
				}
			}
		}
		if (type && object) {
			if (type == 'artist') {
				$scope.playArtist(object);
			} else if (type == 'album') {
				$scope.playAlbum(object);
			} else if (type == 'track') {
				$scope.playTrack(object);
			} else if (type == 'file') {
				$scope.playFile(object);
			}
		}
	};

	$scope.playTrack = function(track) {
		var artist = _.find($scope.$parent.artists,
			function(artist) {
				return artist.id === track.artistId;
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
		for(var mimeType in track.files) {
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

			$scope.player.createSound({
				id: 'ownCloudSound',
				url: $scope.getPlayableFileURL($scope.currentTrack),
				whileplaying: function() {
					$scope.setTime(this.position/1000, this.durationEstimate/1000);
				},
				whileloading: function() {
					var position = this.buffered.reduce(function(prevBufferEnd, buffer) {
						return buffer.end > prevBufferEnd ? buffer.end : prevBuffer.end;
					}, 0);
					$scope.setBuffer(position/1000, this.durationEstimate/1000);
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
			$rootScope.started = false;
		}
	}, true);

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

angular.module('Music').controller('PlaylistController',
	['$scope', 'playlists', function ($scope, playlists) {

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
					// remove style of the placeholder to allow mobile styling
					element.css('line-height', '');
					element.css('font-size', '');
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

			// Hide or replace every second letter on short screens
			if(height < 300) {
				$(".alphabet-navigation a").removeClass("dotted").addClass("stripped");
			} else if(height < 500) {
				$(".alphabet-navigation a").removeClass("stripped").addClass("dotted");
			} else {
				$(".alphabet-navigation a").removeClass("dotted stripped");
			}

			if(height < 300) {
				element.css('line-height', Math.floor(height/13) + 'px');
			} else {
				element.css('line-height', Math.floor(height/26) + 'px');
			}
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
angular.module('Music').factory('ArtistFactory', ['Restangular', '$rootScope', function (Restangular, $rootScope) {
	return {
		getArtists: function() { return Restangular.all('collection').getList(); }
	};
}]);

angular.module('Music').factory('Audio', ['$rootScope', function ($rootScope) {
	soundManager.setup({
		url: OC.linkTo('music', '3rdparty/soundmanager'),
		flashVersion: 8,
		useHTML5Audio: true,
		preferFlash: false,
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

angular.module('Music').factory('Token', [function () {
	return document.getElementsByTagName('head')[0].getAttribute('data-requesttoken');
}]);

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
angular.module('Music').run(['gettextCatalog', function (gettextCatalog) {
/* jshint -W100 */
    gettextCatalog.setStrings('ach', {});
    gettextCatalog.setStrings('ady', {});
    gettextCatalog.setStrings('af', {});
    gettextCatalog.setStrings('af_ZA', {});
    gettextCatalog.setStrings('ak', {});
    gettextCatalog.setStrings('am_ET', {});
    gettextCatalog.setStrings('ar', {"Albums":"الألبومات","Artists":"الفنانون","Delete":"حذف","Description":"وصف","Loading ...":"تحميل ...","Music":"الموسيقى","Next":"التالي","Nothing in here. Upload your music!":"لا شيء موجود هنا. إرفع بعض الموسيقى !","Pause":"إيقاف","Play":"تشغيل","Previous":"السابق","Repeat":"إعادة","Show all {{ trackcount }} songs ...":[".","إظهار المقطع","إظهار المقطعين","إظهار كافة المقاطع الـ {{ trackcount }}","إظهار كافة المقاطع الـ {{ trackcount }}","إظهار كافة المقاطع الـ {{ trackcount }}"],"Show less ...":"إظهار أقل ...","Shuffle":"اختيار عشوائي","Tracks":"المقاطع","Unknown album":"ألبوم غير معروف","Unknown artist":"فنان غير معروف"});
    gettextCatalog.setStrings('ast', {"Albums":"Álbumes","Artists":"Artistes","Delete":"Desaniciar","Description":"Descripción","Description (e.g. App name)":"Descripción (p.ex, nome de l'aplicación)","Generate API password":"Xenerar contraseña pa la API","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Equí pues xenerar contraseñes pa usales cola API d'Ampache, yá que nun puen almacenase de mou seguru pol diseñu de la API d'Ampache. Pues crear toles contraseñes que quieras y revocales en cualquier momentu.","Invalid path":"Camín inválidu","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Recuerda que la API d'Ampache namái ye un prototipu y ye inestable. Informa de la to esperiencia con esta nueva carauterística nel <a href=\"https://github.com/owncloud/music/issues/60\">informe de fallu</a> correspondiente. Prestábame tener una llista de veceros cola que probala. Gracies.","Loading ...":"Cargando...","Music":"Música","Next":"Siguiente","Nothing in here. Upload your music!":"Equí nun hai un res. ¡Xubi la to música!","Path to your music collection":"Camín a la to coleición de música","Pause":"Posa","Play":"Reproducir","Previous":"Anterior","Repeat":"Repitir","Revoke API password":"Revocar contraseña pa la API","Scanning ...":"Escaniando...","Show all {{ trackcount }} songs ...":["Amosar toles {{ trackcount}} canciones...","Amosar toles {{ trackcount }} canciones..."],"Show less ...":"Amosar menos...","Shuffle":"Mecer","This setting restricts the shown music in the web interface of the music app.":"Estos axustes restrinxen la música amosada na interfaz web de l'aplicación de música.","Tracks":"Canciones","Unknown album":"Álbum desconocíu","Unknown artist":"Artista desconocíu","Use your username and following password to connect to this Ampache instance:":"Usa'l to nome d'usuariu y la siguiente contraseña pa coneutate con esta instancia d'Ampache:"});
    gettextCatalog.setStrings('az', {});
    gettextCatalog.setStrings('be', {});
    gettextCatalog.setStrings('bg_BG', {"Delete":"Изтриване","Description":"Описание","Loading ...":"Зареждане ...","Music":"Музика","Next":"Следваща","Nothing in here. Upload your music!":"Няма нищо тук. Качете си музиката!","Pause":"Пауза","Play":"Пусни","Previous":"Предишна","Repeat":"Повтори","Show less ...":"Покажи по-малко ...","Shuffle":"Разбъркване","Unknown album":"Непознат албум","Unknown artist":"Непознат изпълнител"});
    gettextCatalog.setStrings('bn_BD', {"Delete":"মুছে","Description":"বিবরণ","Music":"গানবাজনা","Next":"পরবর্তী","Pause":"বিরতি","Play":"বাজাও","Previous":"পূর্ববর্তী","Repeat":"পূনঃসংঘটন"});
    gettextCatalog.setStrings('bs', {"Next":"Sljedeći"});
    gettextCatalog.setStrings('ca', {"Delete":"Esborra","Description":"Descripció","Loading ...":"Carregant...","Music":"Música","Next":"Següent","Nothing in here. Upload your music!":"Res per aquí. Pugeu la vostra música!","Pause":"Pausa","Play":"Reprodueix","Previous":"Anterior","Repeat":"Repeteix","Show all {{ trackcount }} songs ...":["Mostra totes les {{ trackcount }} peces...","Mostra totes les {{ trackcount }} peces..."],"Show less ...":"Mostra'n menys...","Shuffle":"Aleatori","Unknown album":"Àlbum desconegut","Unknown artist":"Artista desconegut"});
    gettextCatalog.setStrings('cs_CZ', {"Albums":"Alba","Artists":"Umělci","Delete":"Smazat","Description":"Popis","Description (e.g. App name)":"Popis (např. Jméno aplikace)","Generate API password":"Generovat heslo API","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Zde můžete vytvářet hesla pro Ampache API, protože tato nemohou být uložena skutečně bezpečným způsobem z důvodu designu Ampache API. Je možné vygenerovat libovolné množství hesel a kdykoliv je zneplatnit.","Invalid path":"Chybná cesta","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Mějte na paměti, že Ampache API je stále ve vývoji a není stabilní. Můžete nás bez obav informovat o zkušenostech s touto funkcí odesláním hlášení v příslušném <a href=\"https://github.com/owncloud/music/issues/60\">tiketu</a>. Chtěl bych také sestavit seznam zájemců o testování. Díky","Loading ...":"Načítám ...","Music":"Hudba","Next":"Následující","Nothing in here. Upload your music!":"Zde nic není. Nahrajte vaši hudbu!","Path to your music collection":"Cesta k vaší sbírce hudby","Pause":"Pozastavit","Play":"Přehrát","Previous":"Předchozí","Repeat":"Opakovat","Revoke API password":"Odvolat heslo API","Scanning ...":"Procházím ...","Show all {{ trackcount }} songs ...":["Zobrazit {{ trackcount }} písničku ...","Zobrazit {{ trackcount }} písničky ...","Zobrazit {{ trackcount }} písniček ..."],"Show less ...":"Zobrazit méně ...","Shuffle":"Promíchat","This setting restricts the shown music in the web interface of the music app.":"Toto nastavení zamezí zobrazení hudby ve webovém rozhraní hudební aplikace.","Tracks":"Stopy","Unknown album":"Neznámé album","Unknown artist":"Neznámý umělec","Use your username and following password to connect to this Ampache instance:":"Použijte Vaše uživatelské jméno a následující heslo pro připojení k této instanci Ampache:"});
    gettextCatalog.setStrings('cy_GB', {"Delete":"Dileu","Description":"Disgrifiad","Music":"Cerddoriaeth","Next":"Nesaf","Pause":"Seibio","Play":"Chwarae","Previous":"Blaenorol","Repeat":"Ailadrodd"});
    gettextCatalog.setStrings('da', {"Albums":"Album","Artists":"Kunstnere","Delete":"Slet","Description":"Beskrivelse","Loading ...":"Indlæser...","Music":"Musik","Next":"Næste","Nothing in here. Upload your music!":"Her er tomt. Upload din musik!","Pause":"Pause","Play":"Afspil","Previous":"Forrige","Repeat":"Gentag","Show all {{ trackcount }} songs ...":["Vis alle {{ trackcount }} sange ...","Vis alle {{ trackcount }} sange ..."],"Show less ...":"Vis færre ...","Shuffle":"Bland","Tracks":"Spor","Unknown album":"Ukendt album","Unknown artist":"Ukendt artist"});
    gettextCatalog.setStrings('de', {"Albums":"Alben","Artists":"Künstler","Delete":"Löschen","Description":"Beschreibung","Description (e.g. App name)":"Beschreibung (z.B. Name der Anwendung)","Generate API password":"API Passwort erzeugen","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Hier kannst Du Passwörter zur Benutzung mit der Ampache-API erzeugen, da diese aufgrund des Designs der Ampache-API auf keine wirklich sichere Art und Weise gespeichert werden können. Du kannst soviele Passwörter generieren, wie Du möchtest und diese jederzeit verwerfen.","Invalid path":"Ungültiger Pfad","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Bitte bedenke, dass die Ampache-API derzeit eine Vorschau und instabil ist. Du kannst gerne Deine Erfahrungen mit dieser Funktion im entsprechenden <a href=\"https://github.com/owncloud/music/issues/60\">Fehlerbericht</a> melden. Ich würde ebenfalls eine Liste von Anwendung zu Testzwecken sammeln. Dankeschön","Loading ...":"Lade ...","Music":"Musik","Next":"Weiter","Nothing in here. Upload your music!":"Alles leer. Lade Deine Musik hoch!","Path to your music collection":"Pfad zu Deiner Musiksammlung","Pause":"Anhalten","Play":"Abspielen","Previous":"Zurück","Repeat":"Wiederholen","Revoke API password":"API Passwort verwerfen","Scanning ...":"Untersuche ...","Show all {{ trackcount }} songs ...":["{{ trackcount }} Lied anzeigen  ...","Alle {{ trackcount }} Lieder anzeigen  ..."],"Show less ...":"Weniger anzeigen ...","Shuffle":"Zufallswiedergabe","This setting restricts the shown music in the web interface of the music app.":"Diese Einstellung beschränkt die angezeigte Musik im Webinterface der Musik-Applikation","Tracks":"Titel","Unknown album":"Unbekanntes Album","Unknown artist":"Unbekannter Künstler","Use your username and following password to connect to this Ampache instance:":"Nutze Deinen Benutzernamen und folgendes Passwort, um zu dieser Ampache-Instanz zu verbinden:"});
    gettextCatalog.setStrings('de_AT', {"Delete":"Löschen","Description":"Beschreibung","Loading ...":"Lade ...","Music":"Musik","Next":"Nächstes","Nothing in here. Upload your music!":"Alles leer. Lade deine Musik hoch!","Pause":"Pause","Play":"Abspielen","Previous":"Vorheriges","Repeat":"Wiederholen","Show all {{ trackcount }} songs ...":["{{ trackcount }} Lied anzeigen ...","Alle {{ trackcount }} Lieder anzeigen ..."],"Show less ...":"Weniger anzeigen ...","Shuffle":"Zufallswiedergabe","Unknown album":"Unbekanntes Album","Unknown artist":"Unbekannter Künstler"});
    gettextCatalog.setStrings('de_CH', {"Delete":"Löschen","Description":"Beschreibung","Music":"Musik","Next":"Weiter","Pause":"Anhalten","Play":"Abspielen","Previous":"Vorheriges","Repeat":"Wiederholen"});
    gettextCatalog.setStrings('de_DE', {"Albums":"Alben","Artists":"Künstler","Delete":"Löschen","Description":"Beschreibung","Description (e.g. App name)":"Beschreibung (z.B. Name der Anwendung)","Generate API password":"API-Passwort erzeugen","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Hier können Sie Passwörter zur Benutzung mit der Ampache-API erzeugen, da diese aufgrund des Designs der Ampache-API auf keine wirklich sichere Art und Weise gespeichert werden können. Sie könenn soviele Passwörter generieren, wie Sie möchten und diese jederzeit verwerfen.","Invalid path":"Ungültiger Pfad","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Bitte bedenken Sie, dass die Ampache-API derzeit eine Vorschau und instabil ist. Sie können gerne Ihre Erfahrungen mit dieser Funktion im entsprechenden <a href=\"https://github.com/owncloud/music/issues/60\">Fehlerbericht</a> melden. Ich würde ebenfalls eine Liste von Anwendung zu Testzwecken sammeln. Dankeschön","Loading ...":"Lade …","Music":"Musik","Next":"Weiter","Nothing in here. Upload your music!":"Alles leer. Laden Sie Ihre Musik hoch!","Path to your music collection":"Pfad zu Ihrer Musiksammlung","Pause":"Anhalten","Play":"Abspielen","Previous":"Zurück","Repeat":"Wiederholen","Revoke API password":"API-Passwort verwerfen","Scanning ...":"Untersuche ...","Show all {{ trackcount }} songs ...":["{{ trackcount }} Lied anzeigen  ...","Alle {{ trackcount }} Lieder anzeigen  ..."],"Show less ...":"Weniger anzeigen ...","Shuffle":"Zufallswiedergabe","This setting restricts the shown music in the web interface of the music app.":"Diese Einstellung beschränkt die angezeigte Musik im Webinterface der Musik-Applikation","Tracks":"Titel","Unknown album":"Unbekanntes Album","Unknown artist":"Unbekannter Künstler","Use your username and following password to connect to this Ampache instance:":"Nutzen Sie Ihren Benutzernamen und folgendes Passwort, um zu dieser Ampache-Instanz zu verbinden:"});
    gettextCatalog.setStrings('el', {"Albums":"Δίσκοι","Artists":"Καλλιτέχνες","Delete":"Διαγραφή","Description":"Περιγραφή","Description (e.g. App name)":"Περιγραφή (π.χ. όνομα εφαρμογής)","Generate API password":"Δημιουργία συνθηματικού API","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Εδώ μπορείτε να δημιουργήσετε συνθηματικά για χρήση με το API Ampache, γιατί δεν είναι δυνατό να αποθηκευτούν με πραγματικά ασφαλή τρόπο, λόγω της σχεδίασης του API Ampache. Μπορείτε να δημιουργήσετε όσα συνθηματικά θέλετε και να τα ανακαλέσετε οποτεδήποτε.","Invalid path":"Άκυρη διαδρομή","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Θυμηθείτε ότι το API Ampache είναι απλά μια επισκόπηση και είναι ασταθές. Παρακαλούμε αναφέρετε την εμπειρία σας με αυτή την λειτουργία στην αντίστοιχη <a href=\"https://github.com/owncloud/music/issues/60\">αναφορά</a>. Θα ήταν καλό να υπάρχει επίσης μια λίστα με εφαρμογές για δοκιμή. Ευχαριστώ!","Loading ...":"Φόρτωση ...","Music":"Μουσική","Next":"Επόμενο","Nothing in here. Upload your music!":"Δεν υπάρχει τίποτα εδώ. Μεταφορτώστε την μουσική σας!","Path to your music collection":"Διαδρομή για την μουσική σας συλλογή","Pause":"Παύση","Play":"Αναπαραγωγή","Previous":"Προηγούμενο","Repeat":"Επανάληψη","Revoke API password":"Ανάκληση συνθηματικού API","Scanning ...":"Σάρωση...","Show all {{ trackcount }} songs ...":["Εμφάνιση του τραγουδιού","Εμφάνιση όλων των {{ trackcount }} τραγουδιών ..."],"Show less ...":"Προβολή λιγότερων...","Shuffle":"Τυχαία αναπαραγωγή","This setting restricts the shown music in the web interface of the music app.":"Αυτή η ρύθμιση περιορίζει την μουσική που εμφανίζεται στην διεπαφή ιστού της εφαρμογής μουσικής.","Tracks":"Κομμάτια","Unknown album":"Άγνωστο άλμπουμ","Unknown artist":"Άγνωστος καλλιτέχνης","Use your username and following password to connect to this Ampache instance:":"Χρησιμοποιήστε το όνομα χρήστη σας και το παρακάτω συνθηματικό για να συνδεθείτε σε αυτή την εκτέλεση του Ampache:"});
    gettextCatalog.setStrings('en@pirate', {"Music":"Music","Pause":"Pause"});
    gettextCatalog.setStrings('en_GB', {"Albums":"Albums","Artists":"Artists","Delete":"Delete","Description":"Description","Description (e.g. App name)":"Description (e.g. App name)","Generate API password":"Generate API password","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.","Invalid path":"Invalid path","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks","Loading ...":"Loading...","Music":"Music","Next":"Next","Nothing in here. Upload your music!":"Nothing in here. Upload your music!","Path to your music collection":"Path to your music collection","Pause":"Pause","Play":"Play","Previous":"Previous","Repeat":"Repeat","Revoke API password":"Revoke API password","Scanning ...":"Scanning ...","Show all {{ trackcount }} songs ...":["Show all {{ trackcount }} songs...","Show all {{ trackcount }} songs..."],"Show less ...":"Show less...","Shuffle":"Shuffle","This setting restricts the shown music in the web interface of the music app.":"This setting restricts the music shown in the web interface of the music app.","Tracks":"Tracks","Unknown album":"Unknown album","Unknown artist":"Unknown artist","Use your username and following password to connect to this Ampache instance:":"Use your username and following password to connect to this Ampache instance:"});
    gettextCatalog.setStrings('eo', {"Delete":"Forigi","Description":"Priskribo","Loading ...":"Ŝargante...","Music":"Muziko","Next":"Jena","Nothing in here. Upload your music!":"Nenio estas ĉi tie. Alŝutu vian muzikon!","Pause":"Paŭzi...","Play":"Ludi","Previous":"Maljena","Repeat":"Ripeti","Show all {{ trackcount }} songs ...":["Montri ĉiujn {{ trackcount }} kantaĵojn...","Montri ĉiujn {{ trackcount }} kantaĵojn..."],"Show less ...":"Montri malpli...","Shuffle":"Miksi"});
    gettextCatalog.setStrings('es', {"Albums":"Álbumes","Artists":"Artistas","Delete":"Eliminar","Description":"Descripción","Description (e.g. App name)":"Descripción (p.ej., nombre de la aplicación)","Generate API password":"Generar contraseña para la API","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Aquí uno puede crear contraseñas para usarlas con la API de Ampache, pues no pueden ser almacenadas de forma segura debido al diseño de la API de Ampache. Puede crear todas las contraseñas que quiera y revocarlas en cualquier momento.","Invalid path":"Ruta inválida","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Recuerde que la API de Ampache solo es un prototipo y es inestable. Sírvase reportar su experiencia con esta nueva característica en el <a href=\"https://github.com/owncloud/music/issues/60\">informe de error</a> correspondiente. También quisiera tener una lista de clientes con qué probarla. Gracias.","Loading ...":"Cargando...","Music":"Música","Next":"Siguiente","Nothing in here. Upload your music!":"Aquí no hay nada. ¡Sube tu música!","Path to your music collection":"Ruta a su colección de música","Pause":"Pausa","Play":"Reproducir","Previous":"Anterior","Repeat":"Repetir","Revoke API password":"Revocar contraseña para la API","Scanning ...":"Escaneando...","Show all {{ trackcount }} songs ...":["Mostrar todas las {{ trackcount}} canciones...","Mostrar todas las {{ trackcount }} canciones..."],"Show less ...":"Mostrar menos...","Shuffle":"Mezclar","This setting restricts the shown music in the web interface of the music app.":"Esta configuración restringe la música mostrada en la interfaz web de la aplicación de música.","Tracks":"Canciones","Unknown album":"Álbum desconocido","Unknown artist":"Artista desconocido","Use your username and following password to connect to this Ampache instance:":"Use su nombre de usuario y la siguiente contraseña para conectarse con esta instancia de Ampache:"});
    gettextCatalog.setStrings('es_AR', {"Delete":"Borrar","Description":"Descripción","Loading ...":"Cargando...","Music":"Música","Next":"Siguiente","Nothing in here. Upload your music!":"No hay nada aquí. ¡Suba su música!","Pause":"Pausar","Play":"Reproducir","Previous":"Previo","Repeat":"Repetir","Show all {{ trackcount }} songs ...":["Mostrar la única canción...","Mostrar las {{ trackcount }} canciones..."],"Show less ...":"Mostrar menos...","Shuffle":"Aleatorio","Unknown album":"Album desconocido","Unknown artist":"Artista desconocido"});
    gettextCatalog.setStrings('es_CL', {});
    gettextCatalog.setStrings('es_MX', {"Delete":"Eliminar","Description":"Descripción","Loading ...":"Cargando ...","Music":"Música","Next":"Siguiente","Nothing in here. Upload your music!":"Aquí no hay nada. ¡Sube tu música!","Pause":"Pausa","Play":"Reproducir","Previous":"Anterior","Repeat":"Repetir","Show all {{ trackcount }} songs ...":["Mostrar todas las {{ trackcount}} canciones ...","Mostrar todas las {{ trackcount }} canciones ..."],"Show less ...":"Mostrar menos ...","Shuffle":"Mezclar"});
    gettextCatalog.setStrings('et_EE', {"Albums":"Albumid","Artists":"Artistid","Delete":"Kustuta","Description":"Kirjeldus","Description (e.g. App name)":"Kirjeldus (nt. rakendi nimi)","Generate API password":"Tekita API parool","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Siin sa saad tekitada parooli, mida kasutada Ampache API-ga, kuid neid ei ole võimalik talletada turvalisel moel Ampache API olemuse tõttu. Sa saad genereerida nii palju paroole kui soovid ning tühistada neid igal ajal.","Invalid path":"Vigane tee","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Pea meeles, et Ampache APi ei ole küps ning on ebastabiilne. Anna teada oma kogemustest selle funktsionaalsusega vastavalt <a href=\"https://github.com/owncloud/music/issues/60\">teemaarendusele</a>. Ühtlasi soovin nimistut klientidest, mida testida. Tänan.","Loading ...":"Laadimine ...","Music":"Muusika","Next":"Järgmine","Nothing in here. Upload your music!":"Siin pole midagi. Laadi oma muusikat üles!","Path to your music collection":"Tee sinu muusikakoguni","Pause":"Paus","Play":"Esita","Previous":"Eelmine","Repeat":"Korda","Revoke API password":"Keeldu API paroolist","Scanning ...":"Skaneerin ...","Show all {{ trackcount }} songs ...":["Näita {{ trackcount }} lugu ...","Näita kõiki {{ trackcount }} lugu ..."],"Show less ...":"Näita vähem ...","Shuffle":"Juhuslik esitus","This setting restricts the shown music in the web interface of the music app.":"See määrang piirab muusika rakendi veebiliideses muusika kuvamise.","Tracks":"Rajad","Unknown album":"Tundmatu album","Unknown artist":"Tundmatu esitaja","Use your username and following password to connect to this Ampache instance:":"Kasuta oma kasutajatunnust ja järgmist parooli ühendumaks selle Ampache instantsiga:"});
    gettextCatalog.setStrings('eu', {"Delete":"Ezabatu","Description":"Deskribapena","Loading ...":"Kargatzen...","Music":"Musika","Next":"Hurrengoa","Nothing in here. Upload your music!":"Ez dago ezer. Igo zure musika!","Pause":"Pausarazi","Play":"Erreproduzitu","Previous":"Aurrekoa","Repeat":"Errepikatu","Show all {{ trackcount }} songs ...":["Bistaratu {{ trackcount}} abesti guztiak ...","Bistaratu {{ trackcount}} abesti guztiak ..."],"Show less ...":"Bistaratu gutxiago...","Shuffle":"Nahastu","Unknown album":"Diska ezezaguna","Unknown artist":"Artista ezezaguna"});
    gettextCatalog.setStrings('eu_ES', {"Delete":"Ezabatu","Description":"Deskripzioa","Music":"Musika","Next":"Aurrera","Pause":"geldi","Play":"jolastu","Previous":"Atzera","Repeat":"Errepikatu"});
    gettextCatalog.setStrings('fa', {"Delete":"حذف","Description":"توضیحات","Music":"موزیک","Next":"بعدی","Pause":"توقف کردن","Play":"پخش کردن","Previous":"قبلی","Repeat":"تکرار"});
    gettextCatalog.setStrings('fi_FI', {"Albums":"Levyt","Artists":"Esittäjät","Delete":"Poista","Description":"Kuvaus","Description (e.g. App name)":"Kuvaus (esim. sovelluksen nimi)","Generate API password":"Luo API-salasana","Invalid path":"Virheellinen polku","Loading ...":"Ladataan...","Music":"Musiikki","Next":"Seuraava","Nothing in here. Upload your music!":"Täällä ei ole mitään. Lähetä musiikkia tänne!","Path to your music collection":"Musiikkikokoelman polku","Pause":"Keskeytä","Play":"Toista","Previous":"Edellinen","Repeat":"Kertaa","Revoke API password":"Peru API-salasana","Scanning ...":"Tutkitaan...","Show all {{ trackcount }} songs ...":["Näytä {{ trackcount }} kappale...","Näytä kaikki {{ trackcount }} kappaletta..."],"Show less ...":"Näytä vähemmän...","Shuffle":"Sekoita","This setting restricts the shown music in the web interface of the music app.":"Tämä asetus rajoittaa näytettävää musiikkia musiikkisovelluksen verkkokäyttöliittymässä.","Tracks":"Kappaleet","Unknown album":"Tuntematon levy","Unknown artist":"Tuntematon esittäjä","Use your username and following password to connect to this Ampache instance:":"Käytä käyttäjätunnustasi ja seuraavaa salasanaa yhditäessäsi tähän Ampache-istuntoon:"});
    gettextCatalog.setStrings('fr', {"Albums":"Albums","Artists":"Artistes","Delete":"Supprimer","Description":"Description","Description (e.g. App name)":"Description (ex. nom de l'application)","Generate API password":"Générer un mot de passe de l'API","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Ici, vous pouvez générer des mots de passe à utiliser avec l'API Ampache, parce qu'ils ne peuvent être stockés d'une manière très sécurisée en raison de la conception de l'API d'Ampache. Vous pouvez générer autant de mots de passe que vous voulez et vous pouvez les révoquer à tout instant.","Invalid path":"Chemin invalide","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Gardez en mémoire que l'API Ampache est une avant-première et n'est pas encore stable. N'hésitez pas à donner un retour d'expérience de cette fonctionnalité dans l'<a href=\"https://github.com/owncloud/music/issues/60\">item</a> dédié. On aimerait également obtenir une liste des clients avec lesquels tester. Merci.","Loading ...":"Chargement…","Music":"Musique","Next":"Suivant","Nothing in here. Upload your music!":"Il n'y a rien ici ! Envoyez donc votre musique :)","Path to your music collection":"Chemin vers votre collection de musique","Pause":"Pause","Play":"Lire","Previous":"Précédent","Repeat":"Répéter","Revoke API password":"Révoquer le mot de passe de l'API","Scanning ...":"Analyse...","Show all {{ trackcount }} songs ...":["Afficher le morceau {{ trackcount }}...","Afficher les {{ trackcount }} morceaux..."],"Show less ...":"Afficher moins…","Shuffle":"Lecture aléatoire","This setting restricts the shown music in the web interface of the music app.":"Ce paramètre restreint les musiques affichées dans l'interface web de l'application de musique.","Tracks":"Pistes","Unknown album":"Album inconnu","Unknown artist":"Artiste inconnu","Use your username and following password to connect to this Ampache instance:":"Utilisez votre nom d'utilisateur et le mot de passe suivant pour vous connecter à cette instance d'Ampache : "});
    gettextCatalog.setStrings('fr_CA', {});
    gettextCatalog.setStrings('gl', {"Albums":"Albumes","Artists":"Interpretes","Delete":"Eliminar","Description":"Descrición","Description (e.g. App name)":"Descrición (p.ex. o nome do aplicativo)","Generate API password":"Xerar o contrasinal da API","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Aquí pode xerar contrasinais para utilizar coa API de Ampache, xa que non poden ser almacenados nunha forma abondo segura por mor do deseño da API de Ampache. Pode xerar tantos contrasinais como queira e revogalos en calquera momento.","Invalid path":"Ruta incorrecta","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Teña presente que a API de Ampache é só unha edición preliminar e é inestábel. Non dubide en informarnos da súa experiencia con esta característica na correspondente páxina de  <a href=\"https://github.com/owncloud/music/issues/60\">problemas</a>. Gustaríanos tamén, ter unha lista de clientes cos que facer probas. Grazas","Loading ...":"Cargando ...","Music":"Música","Next":"Seguinte","Nothing in here. Upload your music!":"Aquí non hai nada. Envíe a súa música!","Path to your music collection":"Ruta á súa colección de música","Pause":"Pausa","Play":"Reproducir","Previous":"Anterior","Repeat":"Repetir","Revoke API password":"Revogar o contrasinal da API","Scanning ...":"Examinando ...","Show all {{ trackcount }} songs ...":["Amosar todas as {{ trackcount }} cancións ...","Amosar todas as {{ trackcount }} cancións ..."],"Show less ...":"Amosar menos ...","Shuffle":"Ao chou","This setting restricts the shown music in the web interface of the music app.":"Este axuste restrinxe que a música sexa amosada na interface web do aplicativo de música.","Tracks":"Pistas","Unknown album":"Álbum descoñecido","Unknown artist":"Interprete descoñecido","Use your username and following password to connect to this Ampache instance:":"Utilice o seu nome de usuario e o seguinte contrasinal para conectarse a esta instancia do Ampache:"});
    gettextCatalog.setStrings('he', {"Delete":"מחיקה","Description":"תיאור","Loading ...":"טוען...","Music":"מוזיקה","Next":"הבא","Nothing in here. Upload your music!":"אין כאן שום דבר. אולי ברצונך להעלות משהו?","Pause":"השהה","Play":"נגן","Previous":"קודם","Repeat":"חזרה","Show all {{ trackcount }} songs ...":["הצג את כל {{trackcount}} שירים","הצג את כל {{trackcount}} שירים..."],"Show less ...":"הצג פחות...","Shuffle":"ערבב","Unknown album":"אלבום לא ידוע","Unknown artist":"אמן לא ידוע"});
    gettextCatalog.setStrings('hi', {});
    gettextCatalog.setStrings('hr', {"Delete":"Obriši","Description":"Opis","Music":"Muzika","Next":"Sljedeća","Pause":"Pauza","Play":"Reprodukcija","Previous":"Prethodna","Repeat":"Ponavljanje"});
    gettextCatalog.setStrings('hu_HU', {"Delete":"Törlés","Description":"Leírás","Loading ...":"Betöltés ...","Music":"Zene","Next":"Következő","Nothing in here. Upload your music!":"Nincs itt semmi. Töltsd fel a zenédet!","Pause":"Szünet","Play":"Lejátszás","Previous":"Előző","Repeat":"Ismétlés","Show all {{ trackcount }} songs ...":["Mutasd mind a {{ trackcount }} zenét ...","Mutasd mind a {{ trackcount }} zenét ..."],"Show less ...":"Mutass kevesebbet...","Shuffle":"Keverés","Unknown album":"Ismeretlen album","Unknown artist":"Ismeretlen előadó"});
    gettextCatalog.setStrings('hy', {"Delete":"Ջնջել","Description":"Նկարագրություն"});
    gettextCatalog.setStrings('ia', {"Delete":"Deler","Description":"Description","Music":"Musica","Next":"Proxime","Pause":"Pausa","Play":"Reproducer","Previous":"Previe","Repeat":"Repeter"});
    gettextCatalog.setStrings('id', {"Delete":"Hapus","Description":"Penjelasan","Loading ...":"Memuat ...","Music":"Musik","Next":"Selanjutnya","Nothing in here. Upload your music!":"Tidak ada apapun disini. Unggah musik anda!","Pause":"Jeda","Play":"Putar","Previous":"Sebelumnya","Repeat":"Ulangi","Show all {{ trackcount }} songs ...":"Menampilkan semua {{ trackcount }} lagu ...","Show less ...":"Menampilkan ringkas ...","Shuffle":"Acak"});
    gettextCatalog.setStrings('is', {"Delete":"Eyða","Music":"Tónlist","Next":"Næst","Pause":"Pása","Play":"Spila","Previous":"Fyrra"});
    gettextCatalog.setStrings('it', {"Albums":"Album","Artists":"Artisti","Delete":"Elimina","Description":"Descrizione","Description (e.g. App name)":"Descrizione (ad es. Nome applicazione)","Generate API password":"Genera una password API","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Qui puoi generare le password da utilizzare con l'API di Ampache, perché esse non possono essere memorizzate in maniera sicura a causa della forma dell'API di Ampache. Puoi generare tutte le password che vuoi e revocarle quando vuoi.","Invalid path":"Percorso non valido","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Ricorda, l'API di Ampache è solo un'anteprima e non è stabile. Sentiti libero di segnalare la tua esperienza con questa funzionalità nel corrispondente <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. Preferirei inoltre avere un elenco di client da provare. Grazie.","Loading ...":"Caricamento in corso...","Music":"Musica","Next":"Successivo","Nothing in here. Upload your music!":"Non c'è niente qui. Carica la tua musica!","Path to your music collection":"Percorso alla tua collezione musicale","Pause":"Pausa","Play":"Riproduci","Previous":"Precedente","Repeat":"Ripeti","Revoke API password":"Revoca la password API","Scanning ...":"Scansione in corso...","Show all {{ trackcount }} songs ...":["Mostra {{ trackcount }} brano...","Mostra tutti i {{ trackcount }} brani..."],"Show less ...":"Mostra meno...","Shuffle":"Mescola","This setting restricts the shown music in the web interface of the music app.":"Questa impostazione filtra la musica mostrata nell'interfaccia web dell'applicazione musicale.","Tracks":"Tracce","Unknown album":"Album sconosciuto","Unknown artist":"Artista sconosciuto","Use your username and following password to connect to this Ampache instance:":"Utilizza il tuo nome utente e la password per collegarti a questa istanza di Ampache:"});
    gettextCatalog.setStrings('ja_JP', {"Delete":"削除","Description":"説明","Loading ...":"読込中 ...","Music":"ミュージック","Next":"次","Nothing in here. Upload your music!":"ここには何もありません。ミュージックをアップロードしてください！","Pause":"一時停止","Play":"再生","Previous":"前","Repeat":"繰り返し","Show all {{ trackcount }} songs ...":"すべての {{ trackcount }} 曲を表示 ...","Show less ...":"簡略表示 ...","Shuffle":"シャッフル","Unknown album":"不明なアルバム","Unknown artist":"不明なアーティスト"});
    gettextCatalog.setStrings('jv', {"Music":"Gamelan","Next":"Sak bare","Play":"Puter","Previous":"Sak durunge"});
    gettextCatalog.setStrings('ka', {});
    gettextCatalog.setStrings('ka_GE', {"Delete":"წაშლა","Description":"გვერდის დახასიათება","Music":"მუსიკა","Next":"შემდეგი","Pause":"პაუზა","Play":"დაკვრა","Previous":"წინა","Repeat":"გამეორება"});
    gettextCatalog.setStrings('km', {"Delete":"លុប","Description":"ការ​អធិប្បាយ","Loading ...":"កំពុងផ្ទុក ...","Music":"តន្ត្រី","Next":"បន្ទាប់","Nothing in here. Upload your music!":"គ្មាន​អ្វីទេ​នៅទីនេះ។ ដាក់តន្ត្រី​របស់​អ្នកឡើង!","Pause":"ផ្អាក","Play":"លេង","Previous":"មុន","Repeat":"ធ្វើម្ដងទៀត","Show all {{ trackcount }} songs ...":"បង្ហាញ​ទាំង {{ trackcount }} ចម្រៀង ...","Show less ...":"បង្ហាញ​តិច ...","Shuffle":"បង្អូស","Unknown album":"អាល់ប៊ុមអត់​ឈ្មោះ","Unknown artist":"សិល្បករអត់​ឈ្មោះ"});
    gettextCatalog.setStrings('kn', {});
    gettextCatalog.setStrings('ko', {"Delete":"삭제","Description":"종류","Loading ...":"불러오는 중...","Music":"음악","Next":"다음","Nothing in here. Upload your music!":"아무 것도 없습니다. 음악을 업로드하십시오!","Pause":"일시 정지","Play":"재생","Previous":"이전","Repeat":"반복","Show all {{ trackcount }} songs ...":"모든 {{ trackcount }} 곡 보기...","Show less ...":"덜 보기...","Shuffle":"임의 재생","Unknown album":"알려지지 않은 앨범","Unknown artist":"알려지지 않은 아티스트"});
    gettextCatalog.setStrings('ku_IQ', {"Description":"پێناسه","Music":"مۆسیقا","Next":"دوواتر","Pause":"وه‌ستان","Play":"لێدان","Previous":"پێشووتر"});
    gettextCatalog.setStrings('lb', {"Delete":"Läschen","Description":"Beschreiwung","Music":"Musek","Next":"Weider","Pause":"Paus","Play":"Ofspillen","Previous":"Zeréck","Repeat":"Widderhuelen"});
    gettextCatalog.setStrings('lt_LT', {"Delete":"Ištrinti","Description":"Aprašymas","Loading ...":"Įkeliama ...","Music":"Muzika","Next":"Kitas","Nothing in here. Upload your music!":"Nieko nėra. Įkelkite sava muziką!","Pause":"Pristabdyti","Play":"Groti","Previous":"Ankstesnis","Repeat":"Kartoti","Show all {{ trackcount }} songs ...":["Rodyti visas {{ trackcount }} dainas ...","Rodyti visas {{ trackcount }} dainas ...","Rodyti visas {{ trackcount }} dainas ..."],"Show less ...":"Rodyti mažiau ...","Shuffle":"Maišyti","Unknown album":"Nežinomas albumas","Unknown artist":"Nežinomas atlikėjas"});
    gettextCatalog.setStrings('lv', {"Delete":"Dzēst","Description":"Apraksts","Music":"Mūzika","Next":"Nākamā","Pause":"Pauzēt","Play":"Atskaņot","Previous":"Iepriekšējā","Repeat":"Atkārtot"});
    gettextCatalog.setStrings('mk', {"Delete":"Избриши","Description":"Опис","Loading ...":"Вчитувам ...","Music":"Музика","Next":"Следно","Nothing in here. Upload your music!":"Тука нема ништо. Ставете своја музика!","Pause":"Пауза","Play":"Пушти","Previous":"Претходно","Repeat":"Повтори","Show all {{ trackcount }} songs ...":["Прикажи ги сите {{ trackcount }} песни ...","Прикажи ги сите {{ trackcount }} песни ..."],"Show less ...":"Прикажи помалку ...","Shuffle":"Помешај"});
    gettextCatalog.setStrings('ml', {});
    gettextCatalog.setStrings('ml_IN', {"Music":"സംഗീതം","Next":"അടുത്തത്","Pause":" നിറുത്ത്","Play":"തുടങ്ങുക","Previous":"മുന്‍പത്തേത്"});
    gettextCatalog.setStrings('mn', {});
    gettextCatalog.setStrings('ms_MY', {"Delete":"Padam","Description":"Keterangan","Loading ...":"Memuatkan ...","Music":"Muzik","Next":"Seterus","Nothing in here. Upload your music!":"Tiada apa-apa di sini. Muat naik muzik anda!","Pause":"Jeda","Play":"Main","Previous":"Sebelum","Repeat":"Ulang","Show all {{ trackcount }} songs ...":"Paparkan semua {{ trackcount }} lagu ...","Show less ...":"Kurangkan paparan ...","Shuffle":"Kocok"});
    gettextCatalog.setStrings('my_MM', {"Description":"ဖော်ပြချက်"});
    gettextCatalog.setStrings('nb_NO', {"Delete":"Slett","Description":"Beskrivelse","Loading ...":"Laster ...","Music":"Musikk","Next":"Neste","Nothing in here. Upload your music!":"Ingenting her. Last opp musikken din!","Pause":"Pause","Play":"Spill","Previous":"Forrige","Repeat":"Gjenta","Scanning ...":"Skanner...","Show all {{ trackcount }} songs ...":["Vis alle {{ trackcount }} sanger ...","Vis alle {{ trackcount }} sanger ..."],"Show less ...":"Vis mindre ...","Shuffle":"Tilfeldig","Unknown album":"Ukjent album","Unknown artist":"Ukjent artist"});
    gettextCatalog.setStrings('nds', {});
    gettextCatalog.setStrings('ne', {});
    gettextCatalog.setStrings('nl', {"Albums":"Albums","Artists":"Artiesten","Delete":"Verwijder","Description":"Beschrijving","Description (e.g. App name)":"Beschrijving (bijv. appnaam)","Generate API password":"Genereren API wachtwoord","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Hier kunt u wachtwoorden genereren voor gebruik met de Ampacht API, omdat ze door het ontwerp van de Ampache API niet op een echt veilige manier kunnen worden bewaard. U kunt zoveel wachtwoorden genereren als u wilt en ze op elk moment weer intrekken.","Invalid path":"Ongeldig pad","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Vergeet niet dat de Ampache API volop in ontwikkeling is en dus instabiel is. Rapporteer gerust uw ervaringen met deze functionaliteit in deze <a href=\"https://github.com/owncloud/music/issues/60\">melding</a>. Ik zou ook graag een lijst met clients hebben om te kunnen testen. Bij voorbaat dank!","Loading ...":"Laden ...","Music":"Muziek","Next":"Volgende","Nothing in here. Upload your music!":"Nog niets aanwezig. Upload uw muziek!","Path to your music collection":"Pad naar uw muziekverzameling","Pause":"Pause","Play":"Afspelen","Previous":"Vorige","Repeat":"Herhaling","Revoke API password":"Intrekken API wachtwoord","Scanning ...":"Scannen ...","Show all {{ trackcount }} songs ...":["Toon elk {{ trackcount }} nummer ...","Toon alle {{ trackcount }} nummers ..."],"Show less ...":"Toon minder ...","Shuffle":"Shuffle","This setting restricts the shown music in the web interface of the music app.":"Deze instelling beperkt de in de webinterface van de muziek app te tonen muziek.","Tracks":"Nummers","Unknown album":"Onbekend album","Unknown artist":"Onbekende artiest","Use your username and following password to connect to this Ampache instance:":"Gebruik uw gebruikersnaam en het volgende wachtwoord om te verbinden met deze Ampache installatie:"});
    gettextCatalog.setStrings('nn_NO', {"Delete":"Slett","Description":"Skildring","Music":"Musikk","Next":"Neste","Pause":"Pause","Play":"Spel","Previous":"Førre","Repeat":"Gjenta"});
    gettextCatalog.setStrings('nqo', {});
    gettextCatalog.setStrings('oc', {"Delete":"Escafa","Description":"Descripcion","Music":"Musica","Next":"Venent","Pause":"Pausa","Play":"Fai tirar","Previous":"Darrièr","Repeat":"Torna far"});
    gettextCatalog.setStrings('pa', {"Delete":"ਹਟਾਓ","Music":"ਸੰਗੀਤ"});
    gettextCatalog.setStrings('pl', {"Albums":"Albumy","Artists":"Artyści","Delete":"Usuń","Description":"Opis","Description (e.g. App name)":"Opis (np. Nazwa aplikacji)","Generate API password":"Wygeneruj hasło API","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Tutaj możesz wygenerować hasła do używania API Ampache, ponieważ nie mogą one być przechowywane w rzeczywiście bezpieczny sposób z powodu architektury API Ampache. Możesz wygenerować tyle haseł ile chcesz i odwołać je w dowolnym momencie.","Invalid path":"niewłaściwa ścieżka","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Miej na uwadze, że API Ampache jest tylko poglądowe i niestabilne. Możesz swobodnie raportować swoje doświadczenia z tą funkcją w odpowiednim <a href=\"https://github.com/owncloud/music/issues/60\">dokumencie</a>. Chciałbym mieć również listę klientów z którymi będę przeprowadzać testy. Dzięki","Loading ...":"Wczytuję ...","Music":"Muzyka","Next":"Następny","Nothing in here. Upload your music!":"Brak zawartości. Proszę wysłać muzykę!","Path to your music collection":"Ścieżka do Twojej kolekcji muzyki","Pause":"Wstrzymaj","Play":"Odtwarzaj","Previous":"Poprzedni","Repeat":"Powtarzanie","Revoke API password":"Odwołaj hasło API","Scanning ...":"Skanowanie...","Show all {{ trackcount }} songs ...":["Pokaż wszystkie {{ trackcount }} piosenek ...","Pokaż wszystkie {{ trackcount }} piosenek ...","Pokaż wszystkie {{ trackcount }} piosenek ..."],"Show less ...":"Pokaz mniej ...","Shuffle":"Losowo","This setting restricts the shown music in the web interface of the music app.":"To ustawienie ogranicza muzykę pokazaną w przeglądarce w aplikacji muzycznej","Tracks":"Utwory","Unknown album":"Nieznany album","Unknown artist":"Nieznany artysta","Use your username and following password to connect to this Ampache instance:":"Użyj nazwy użytkownika i następującego hasła do połączenia do tej instancji Ampache:"});
    gettextCatalog.setStrings('pt_BR', {"Albums":"Albuns","Artists":"Artistas","Delete":"Eliminar","Description":"Descrição","Description (e.g. App name)":"Descrição (por exemplo, nome do App)","Generate API password":"Gerar senha API","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Aqui você pode gerar senhas para usar com a API Ampache, porque eles não podem ser armazenados de uma forma muito segura devido ao design da API Ampache. Você pode gerar o maior número de senhas que você quiser e revogá-las a qualquer hora.","Invalid path":"Caminho inválido","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Tenha em mente, que a API Ampache é apenas uma pré-visualização e é instável. Sinta-se livre para relatar sua experiência com esse recurso na questão correspondente <a href=\"https://github.com/owncloud/music/issues/60\">assunto</a>. Eu também gostaria de ter uma lista de clientes para testar. obrigado","Loading ...":"Carregando...","Music":"Música","Next":"Próxima","Nothing in here. Upload your music!":"Nada aqui. Enviar sua música!","Path to your music collection":"Caminho para a sua coleção de músicas","Pause":"Paisa","Play":"Reproduzir","Previous":"Anterior","Repeat":"Repatir","Revoke API password":"Revogar senha API","Scanning ...":"Escaneando ...","Show all {{ trackcount }} songs ...":["Mostrar todas as {{ trackcount }} músicas ...","Mostrar todas as {{ trackcount }} músicas ..."],"Show less ...":"Exibição mais simples...","Shuffle":"Embaralhar","This setting restricts the shown music in the web interface of the music app.":"Essa configuração restringe-se  a mostrar a música na interface web do aplicativo de música.","Tracks":"Trilhas","Unknown album":"Album desconhecido","Unknown artist":"Artista desconhecido","Use your username and following password to connect to this Ampache instance:":"Use o seu nome de usuário e senha a seguir para se conectar a essa instância Ampache:"});
    gettextCatalog.setStrings('pt_PT', {"Delete":"Eliminar","Description":"Descrição","Loading ...":"A carregar...","Music":"Musica","Next":"Próxima","Nothing in here. Upload your music!":"Não existe nada aqui. Carregue a sua musica!","Pause":"Pausa","Play":"Reproduzir","Previous":"Anterior","Repeat":"Repetir","Show all {{ trackcount }} songs ...":["Mostrar todas as {{ trackcount }} músicas ...","Mostrar todas as {{ trackcount }} músicas ..."],"Show less ...":"Mostrar menos...","Shuffle":"Baralhar","Unknown album":"Álbum desconhecido","Unknown artist":"Artista desconhecido"});
    gettextCatalog.setStrings('ro', {"Delete":"Șterge","Description":"Descriere","Music":"Muzică","Next":"Următor","Pause":"Pauză","Play":"Redă","Previous":"Anterior","Repeat":"Repetă"});
    gettextCatalog.setStrings('ru', {"Delete":"Удалить","Description":"Описание","Loading ...":"Загружается...","Music":"Музыка","Next":"Следующий","Nothing in here. Upload your music!":"Здесь ничего нет. Загрузите вашу музыку!","Pause":"Пауза","Play":"Проиграть","Previous":"Предыдущий","Repeat":"Повтор","Show all {{ trackcount }} songs ...":["Показать {{ trackcount }} песню...","Показать все {{ trackcount }} песни...","Показать все {{ trackcount }} песен..."],"Show less ...":"Показать меньше...","Shuffle":"Перемешать","Unknown album":"Неизвестный альбом","Unknown artist":"Неизвестный исполнитель"});
    gettextCatalog.setStrings('ru_RU', {"Delete":"Удалить","Music":"Музыка"});
    gettextCatalog.setStrings('si_LK', {"Delete":"මකා දමන්න","Description":"විස්තරය","Music":"සංගීතය","Next":"ඊලඟ","Pause":"විරාමය","Play":"ධාවනය","Previous":"පෙර","Repeat":"පුනරාවර්ථන"});
    gettextCatalog.setStrings('sk', {"Delete":"Odstrániť","Description":"Popis","Repeat":"Opakovať"});
    gettextCatalog.setStrings('sk_SK', {"Albums":"Albumy","Artists":"Interpreti","Delete":"Zmazať","Description":"Popis","Description (e.g. App name)":"Popis (napr. App name)","Generate API password":"Vygenerovanie hesla API","Invalid path":"Neplatná cesta","Loading ...":"Nahrávam...","Music":"Hudba","Next":"Ďalšia","Nothing in here. Upload your music!":"Nič tu nie je. Nahrajte si vašu hudbu!","Path to your music collection":"Cesta k vašej hudobnej zbierke","Pause":"Pauza","Play":"Prehrať","Previous":"Predošlá","Repeat":"Opakovať","Revoke API password":"Zneplatniť heslo API","Scanning ...":"Skenujem...","Show all {{ trackcount }} songs ...":["Zobraziť {{ trackcount }} skladieb ...","Zobraziť {{ trackcount }} skladieb ...","Zobraziť všetky {{ trackcount }} skladieb ..."],"Show less ...":"Zobraziť menej ...","Shuffle":"Zamiešať","This setting restricts the shown music in the web interface of the music app.":"Toto nastavenie obmedzuje zobrazenie hudby vo webovom rozhraní hudobnej aplikácie.","Tracks":"Skladby","Unknown album":"Neznámy album","Unknown artist":"Neznámy umelec","Use your username and following password to connect to this Ampache instance:":"Použite svoje používateľské meno a heslo pre pripojenie k tejto inštancii Ampache:"});
    gettextCatalog.setStrings('sl', {"Albums":"Albumi","Artists":"Izvajalci","Delete":"Izbriši","Description":"Opis","Description (e.g. App name)":"Opis (na primer ime programa)","Generate API password":"Ustvari geslo API","Invalid path":"Neveljavna pot","Loading ...":"Nalaganje ...","Music":"Glasba","Next":"Naslednja","Nothing in here. Upload your music!":"V mapi ni glasbenih datotek. Dodajte skladbe!","Path to your music collection":"Pot do zbirke glasbe","Pause":"Premor","Play":"Predvajaj","Previous":"Predhodna","Repeat":"Ponovi","Revoke API password":"Razveljavi geslo API","Scanning ...":"Poteka preiskovanje ...","Show all {{ trackcount }} songs ...":["Pokaži {{ trackcount }} posnetek ...","Pokaži {{ trackcount }} posnetka ...","Pokaži {{ trackcount }} posnetke ...","Pokaži {{ trackcount }} posnetkov ..."],"Show less ...":"Pokaži manj ...","Shuffle":"Premešaj","This setting restricts the shown music in the web interface of the music app.":"Nastavitev omeji prikaz glasbe v spletnem vmesniku programa glasbe.","Tracks":"Sledi","Unknown album":"Neznan album","Unknown artist":"Neznan izvajalec","Use your username and following password to connect to this Ampache instance:":"Uporabite uporabniško ime in navedeno geslo za povezavo z Ampache:"});
    gettextCatalog.setStrings('sq', {"Delete":"Elimino","Description":"Përshkrimi","Loading ...":"Ngarkim...","Music":"Muzikë","Next":"Mëpasshëm","Nothing in here. Upload your music!":"Këtu nuk ka asgjë. Ngarkoni muziken tuaj!","Pause":"Pauzë","Play":"Luaj","Previous":"Mëparshëm","Repeat":"Përsëritet","Show less ...":"Shfaq m'pak...","Shuffle":"Përziej"});
    gettextCatalog.setStrings('sr', {"Delete":"Обриши","Description":"Опис","Music":"Музика","Next":"Следећа","Pause":"Паузирај","Play":"Пусти","Previous":"Претходна","Repeat":"Понављај"});
    gettextCatalog.setStrings('sr@latin', {"Delete":"Obriši","Description":"Opis","Music":"Muzika","Next":"Sledeća","Pause":"Pauziraj","Play":"Pusti","Previous":"Prethodna","Repeat":"Ponavljaj"});
    gettextCatalog.setStrings('su', {});
    gettextCatalog.setStrings('sv', {"Albums":"Album","Artists":"Artister","Delete":"Radera","Description":"Beskrivning","Description (e.g. App name)":"Beskrivning (ex. App namn)","Generate API password":"Generera API lösenord","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Här kan du generera lösenord för användning med Ampaches API, eftersom de inte kan lagras på ett riktigt säkert sätt på grund av Ampachi API:ns design. Du kan generera så många lösenord du vill och upphäva dem när som helst.","Invalid path":"Ogiltig sökväg","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Kom ihåg, att Ampaches API endast är en förhandsvisning och är ostabil. Du är välkommen att rapportera din upplevelse med denna funktionen i motsvarande <a href=\"https://github.com/owncloud/music/issues/60\">problem</a>. Jag skulle också vilja ha en lista över klienter att testa med.\\nTack","Loading ...":"Laddar  ...","Music":"Musik","Next":"Nästa","Nothing in here. Upload your music!":"Det finns inget här. Ladda upp din musik!","Path to your music collection":"Sökvägen till din musiksamling","Pause":"Paus","Play":"Spela","Previous":"Föregående","Repeat":"Upprepa","Revoke API password":"Upphäv API lösenord","Scanning ...":"Skannar ...","Show all {{ trackcount }} songs ...":["Visa alla {{ trackcount }} låtar ...","Visa alla {{ trackcount }} låtar ..."],"Show less ...":"Visa mindre ...","Shuffle":"Blanda","This setting restricts the shown music in the web interface of the music app.":"Den här inställningen begränsar musiken som visas i musik appens webbgränssnitt.","Tracks":"Spår","Unknown album":"Okänt album","Unknown artist":"Okänd artist","Use your username and following password to connect to this Ampache instance:":"Använd ditt användarnamn och följande lösenord för att ansluta mot denna Ampache instansen:"});
    gettextCatalog.setStrings('sw_KE', {});
    gettextCatalog.setStrings('ta_LK', {"Delete":"நீக்குக","Description":"விவரிப்பு","Music":"இசை","Next":"அடுத்த","Pause":"இடைநிறுத்துக","Play":"Play","Previous":"முன்தைய","Repeat":"மீண்டும்"});
    gettextCatalog.setStrings('te', {"Delete":"తొలగించు","Music":"సంగీతం","Next":"తదుపరి","Previous":"గత"});
    gettextCatalog.setStrings('th_TH', {"Delete":"ลบ","Description":"คำอธิบาย","Music":"เพลง","Next":"ถัดไป","Pause":"หยุดชั่วคราว","Play":"เล่น","Previous":"ก่อนหน้า","Repeat":"ทำซ้ำ"});
    gettextCatalog.setStrings('tr', {"Albums":"Albümler","Artists":"Sanatçılar","Delete":"Sil","Description":"Tanımlama","Description (e.g. App name)":"Açıklama (örn. Uygulama adı)","Generate API password":"API parolası oluştur","Here you can generate passwords to use with the Ampache API, because they can't be stored in a really secure way due to the design of the Ampache API. You can generate as many passwords as you want and revoke them anytime.":"Burada Ampache API'si ile kullanılacak parolaları oluşturabilirsiniz. Çünkü Ampache API'sinin tasarımından dolayı bu parolalar yeterince güvenli bir şekilde depolanamamaktadır. İstediğiniz kadar parola oluşturup; ardından istediğiniz zaman geçersiz kılabilirsiniz.","Invalid path":"Geçersiz yol","Keep in mind, that the Ampache API is just a preview and is unstable. Feel free to report your experience with this feature in the corresponding <a href=\"https://github.com/owncloud/music/issues/60\">issue</a>. I would also like to have a list of clients to test with. Thanks":"Unutmayın, Ampache API'si henüz bir önizleme olup, kararlı sürüm değildir. Bu özellikle ilgili deneyiminizi ilgili <a href=\"https://github.com/owncloud/music/issues/60\">sorunlar</a> kısmında bildirmekten çekinmeyin. Ayrıca test edilmesi gereken istemcilerin listesini de edinmek isterim. Teşekkürler.","Loading ...":"Yükleniyor...","Music":"Müzik","Next":"Sonraki","Nothing in here. Upload your music!":"Burada hiçbir şey yok. Müziğinizi yükleyin!","Path to your music collection":"Müzik koleksiyonunuzun yolu","Pause":"Beklet","Play":"Oynat","Previous":"Önceki","Repeat":"Tekrar","Revoke API password":"API parolasını geçersiz kıl","Scanning ...":"Taranıyor...","Show all {{ trackcount }} songs ...":["Tüm {{ trackcount }} şarkıyı göster ...","Tüm {{ trackcount }} şarkıyı göster ..."],"Show less ...":"Daha az göster ...","Shuffle":"Karıştır","This setting restricts the shown music in the web interface of the music app.":"Bu ayar, müzik uygulamasının web arayüzünde gösterdiği müziği kısıtlar.","Tracks":"Parçalar","Unknown album":"Bilinmeyen albüm","Unknown artist":"Bilinmeyen sanatçı","Use your username and following password to connect to this Ampache instance:":"Bu Ampache örneğine bağlanmak için kullanıcı adı ve parolanızı kullanın:"});
    gettextCatalog.setStrings('tzm', {});
    gettextCatalog.setStrings('ug', {"Delete":"ئۆچۈر","Description":"چۈشەندۈرۈش","Music":"نەغمە","Next":"كېيىنكى","Pause":"ۋاقىتلىق توختا","Play":"چال","Previous":"ئالدىنقى","Repeat":"قايتىلا"});
    gettextCatalog.setStrings('uk', {"Delete":"Видалити","Description":"Опис","Loading ...":"Завантаження ...","Music":"Музика","Next":"Наступний","Nothing in here. Upload your music!":"Тут зараз нічого немає. Завантажте свою музику!","Pause":"Пауза","Play":"Грати","Previous":"Попередній","Repeat":"Повторювати","Show all {{ trackcount }} songs ...":["Показати {{ trackcount }} пісню ...","Показати всі {{ trackcount }} пісні ...","Показати всі {{ trackcount }} пісні ..."],"Show less ...":"Показати меньше ...","Shuffle":"Перемішати"});
    gettextCatalog.setStrings('ur', {});
    gettextCatalog.setStrings('ur_PK', {});
    gettextCatalog.setStrings('uz', {});
    gettextCatalog.setStrings('vi', {"Delete":"Xóa","Description":"Mô tả","Loading ...":"Đang tải ...","Music":"Âm nhạc","Next":"Kế tiếp","Nothing in here. Upload your music!":"Không có gì ở đây. Hãy tải nhạc của bạn lên!","Pause":"Tạm dừng","Play":"Play","Previous":"Lùi lại","Repeat":"Lặp lại","Show all {{ trackcount }} songs ...":"Hiển thị tất cả {{ trackcount }} bài hát ...","Show less ...":"Hiển thị ít hơn ...","Shuffle":"Ngẫu nhiên","Unknown album":"Không tìm thấy album","Unknown artist":"Không tìm thấy nghệ sĩ"});
    gettextCatalog.setStrings('zh_CN', {"Delete":"删除","Description":"描述","Loading ...":"加载中...","Music":"音乐","Next":"下一个","Nothing in here. Upload your music!":"这里还什么都没有。上传你的音乐吧！","Pause":"暂停","Play":"播放","Previous":"前一首","Repeat":"重复","Show all {{ trackcount }} songs ...":"显示所有 {{ trackcount }} 首歌曲 ...","Show less ...":"显示概要","Shuffle":"随机","Unknown album":"未知专辑","Unknown artist":"未知艺术家"});
    gettextCatalog.setStrings('zh_HK', {"Delete":"刪除","Music":"音樂","Next":"下一首","Pause":"暫停","Play":"播放","Previous":"上一首"});
    gettextCatalog.setStrings('zh_TW', {"Delete":"刪除","Description":"描述","Loading ...":"載入中…","Music":"音樂","Next":"下一個","Nothing in here. Upload your music!":"這裡沒有東西，上傳你的音樂！","Pause":"暫停","Play":"播放","Previous":"上一個","Repeat":"重覆","Show all {{ trackcount }} songs ...":"顯示全部 {{ trackcount }} 首歌曲","Show less ...":"顯示更少","Shuffle":"隨機播放"});
/* jshint +W100 */
}]);
