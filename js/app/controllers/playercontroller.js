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
		$scope.currentTrack = parameters.track;
		$scope.currentArtist = parameters.artist;
		$scope.currentAlbum = parameters.album;
		// play this track
		$scope.toggle(true);
	});
}]);