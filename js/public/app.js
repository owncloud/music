
angular.module('Music', ['OC', 'restangular']).
	config(
		['$routeProvider', '$interpolateProvider', 'RestangularProvider',
		function ($routeProvider, $interpolateProvider, RestangularProvider) {

	$routeProvider.when('/', {
		templateUrl: 'main.html',
		controller: 'MainController',
		resolve: {
			artists: function(Restangular) {
				return Restangular.all('artists').getList({fulltree: true});
			}
		}
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
	['$scope', '$routeParams', 'artists', 'playerService', function ($scope, $routeParams, artists, playerService) {

	$scope.artists = artists;

	$scope.playTrack = function(track) {
		var artist = _.find($scope.artists,
			function(artist){
				return artist.id === track.artist.id;
			}),
			album = _.find(artist.albums,
			function(album){
				return album.id === track.album.id;
			});
		playerService.publish('play', {track: track, artist: artist, album: album});
	};

	$scope.playAlbum = function(album) {
		var track = album.tracks[0],
			artist = _.find($scope.artists,
			function(artist){
				return artist.id === track.artist.id;
			});
		playerService.publish('play', {track: track, artist: artist, album: album});
	};

	$scope.playArtist = function(artist) {
		var album = artist.albums[0],
			track = album.tracks[0];
		playerService.publish('play', {track: track, artist: artist, album: album});
	};
}]);
angular.module('Music').controller('PlayerController',
	['$scope', '$routeParams', 'playerService', function ($scope, $routeParams, playerService) {

	$scope.playing = false;
	$scope.player = null;
	$scope.currentTime = 0.0;
	$scope.duration = 0.0;
	$scope.currentTrack = null;
	$scope.currentArtist = null;
	$scope.currentAlbum = null;

	$scope.toggle = function(forcePlay) {
		forcePlay = forcePlay || false;
		if($scope.currentTrack === null || $scope.player === null) {
			// don't toggle if there isn't any track or the player isn't initialized
			return;
		}
		if(!$scope.playing || forcePlay) {
			$scope.playing = true;
		} else {
			$scope.playing = false;
		}
	};

	$scope.updatePlayTime = function(playtime) {
		// determine if already inside of an $apply or $digest
		// see http://stackoverflow.com/a/12859093
		if($scope.$$phase) {
			$scope.currentTime = playtime;
		} else {
			$scope.$apply(function(){
				$scope.currentTime = playtime;
			});
		}
	};


	$scope.updateDuration = function(duration) {
		// determine if already inside of an $apply or $digest
		// see http://stackoverflow.com/a/12859093
		if($scope.$$phase) {
			$scope.duration = duration;
		} else {
			$scope.$apply(function(){
				$scope.duration = duration;
			});
		}
	};

	$scope.next = function() {
	};

	playerService.subscribe('play', function(event, parameters){
		// switch initial state
		$scope.$parent.started = true;

		$scope.currentTrack = parameters.track;
		$scope.currentArtist = parameters.artist;
		$scope.currentAlbum = parameters.album;
		// play this track
		$scope.toggle(true);
	});
}]);
angular.module('Music').controller('PlaylistController',
	['$scope', '$routeParams', 'playlists', function ($scope, $routeParams, playlists) {

	$scope.playlists = playlists;

}]);
angular.module('Music').directive('albumart', function() {
	return function(scope, element, attrs, ctrl) {
		attrs.$observe('albumart',function(){
			// TODO fix dependency on md5
			var hash = md5(attrs.albumart),
				maxRange = parseInt('ffffffffff', 16),
				red = parseInt(hash.substr(0,10), 16)/maxRange,
				green = parseInt(hash.substr(10,10), 16)/maxRange,
				blue = parseInt(hash.substr(20,10), 16)/maxRange;
			red *= 256;
			green *= 256;
			blue *= 256;
			rgb = [Math.floor(red), Math.floor(green), Math.floor(blue)];
			element.css('background-color', 'rgb(' + rgb.join(',') + ')');
		});
	};
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
angular.module('Music').service('playerService', ['$rootScope', function($rootScope) {
    return {
        publish: function(name, parameters) {
            $rootScope.$emit(name, parameters);
        },
        subscribe: function(name, listener) {
            $rootScope.$on(name, listener);
        }
    };
}]);
