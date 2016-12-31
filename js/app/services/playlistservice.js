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

angular.module('Music').service('playlistService', ['$rootScope', function($rootScope) {
	var playlist = null;
	var currentIndex = null;
	var played = [];

	function wrapIndexToStart(list, index) {
		if(index > 0) {
			// slice array in two parts and interchange them
			var begin = list.slice(0, index);
			var end = list.slice(index);
			list = end.concat(begin);
		}
		return list;
	}

	return {
		getCurrentIndex: function() {
			return currentIndex;
		},
		jumpToPrevTrack: function() {
			if(played.length > 0) {
				currentIndex = played.pop();
				return playlist[currentIndex];
			}
			return null;
		},
		jumpToNextTrack: function(repeat, shuffle) {
			if(playlist === null) {
				return null;
			}
			if(currentIndex !== null) {
				// add previous track id to the played list
				played.push(currentIndex);
			}
			if(shuffle === true) {
				if(playlist.length === played.length) {
					if(repeat === true) {
						played = [];
					} else {
						currentIndex = null;
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
				currentIndex = all[Math.round(Math.random() * (all.length - 1))];
			} else {
				if(currentIndex === null ||
					currentIndex === (playlist.length - 1) && repeat === true) {
					currentIndex = 0;
				} else {
					currentIndex++;
				}
			}
			// repeat is disabled and the end of the playlist is reached
			// -> abort
			if(currentIndex >= playlist.length) {
				currentIndex = null;
				return null;
			}
			return playlist[currentIndex];
		},
		setPlaylist: function(pl, startIndex /*optional*/) {
			playlist = wrapIndexToStart(pl, startIndex);
			currentIndex = null;
			played = [];
		},
		publish: function(name, parameters) {
			$rootScope.$emit(name, parameters);
		},
		subscribe: function(name, listener) {
			$rootScope.$on(name, listener);
		}
	};
}]);
