/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @copyright Morris Jobke 2013, 2014
 */

angular.module('Music').controller('MainController',
	['$rootScope', '$scope', '$route', 'ArtistFactory', 'playlistService', 'gettextCatalog', 'Restangular',
	function ($rootScope, $scope, $route, ArtistFactory, playlistService, gettextCatalog, Restangular) {

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
		$scope.updateAvailable = false;
		$scope.loading = true;
		ArtistFactory.getArtists().then(function(artists){
			$scope.loading = false;
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

	$scope.processNextScanStep = function(dry) {
		$scope.toScan = false;
		$scope.dryScanRun = dry;

		// if it's not a dry run it will scan
		if(dry === 0) {
			$scope.scanning = true;
		}
		Restangular.all('scan').getList({dry: dry}).then(function(scanItems){
			var scan = scanItems[0];

			// if it was not a dry run and the processed count is bigger than
			// the previous value there are new music files available
			if(scan.processed > $scope.scanningScanned && $scope.dryScanRun === 0) {
				$scope.updateAvailable = true;
			}

			$scope.scanningScanned = scan.processed;
			$scope.scanningTotal = scan.total;

			if(scan.processed < scan.total) {
				// allow recursion but just if it was not a dry run previously
				if($scope.dryScanRun === 0) {
					$scope.processNextScanStep(0);
				} else {
					$scope.toScan = true;
				}
			} else {
				if(scan.processed !== scan.total) {
					Restangular.all('log').post({message: 'Processed more files than available ' + scan.processed + '/' + scan.total });
				}
				$scope.scanning = false;
			}

			if($scope.updateAvailable && $scope.artists.length === 0) {
				$scope.update();
			}
		});
	};

	// initial lookup if new files are available
	$scope.processNextScanStep(1);

	$scope.scanning = false;
	$scope.scanningScanned = 0;
	$scope.scanningTotal = 0;

}]);
