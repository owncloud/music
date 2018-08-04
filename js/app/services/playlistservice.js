/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2013
 * @copyright Pauli Järvinen 2017, 2018
 */

angular.module('Music').service('playlistService', ['$rootScope', function($rootScope) {
	var playlist = null;
	var playlistId = null;
	var playOrder = [];
	var playOrderIter = -1;
	var startFromIndex = null;
	var shuffle = false;
	var repeat = false;
	var prevShuffleState = false;

	function shuffledIndices() {
		var indices = _.range(playlist.length);
		return _.shuffle(indices);
	}

	function shuffledIndicesExcluding(toExclude) {
		var indices = _.range(playlist.length);
		indices.splice(toExclude, 1);
		return _.shuffle(indices);
	}

	function wrapIndexToStart(list, index) {
		if (index > 0) {
			// slice array in two parts and interchange them
			var begin = list.slice(0, index);
			var end = list.slice(index);
			list = end.concat(begin);
		}
		return list;
	}

	function enqueueIndices() {
		var nextIndices = null;

		if (shuffle) {
			if (startFromIndex !== null) {
				nextIndices = [startFromIndex].concat(shuffledIndicesExcluding(startFromIndex));
			} else {
				nextIndices = shuffledIndices();
			}
			// if the next index ended up to be tha same as the pervious one, flip
			// it to the end of the order
			if (playlist.length > 1 && _.last(playOrder) == _.first(nextIndices)) {
				nextIndices = wrapIndexToStart(nextIndices, 1);
			}
		}
		else {
			nextIndices = _.range(playlist.length);
			if (startFromIndex !== null) {
				nextIndices = wrapIndexToStart(nextIndices, startFromIndex);
			}
		}

		playOrder = playOrder.concat(nextIndices);
		prevShuffleState = shuffle;
	}

	// drop the planned play order but preserve the history
	function dropFuturePlayOrder() {
		playOrder = _.first(playOrder, playOrderIter + 1);
	}

	function insertMany(hostArray, targetIndex, insertedItems) {
		hostArray.splice.apply(hostArray, [targetIndex, 0].concat(insertedItems));
	}

	return {
		setShuffle: function(state) {
			shuffle = state;
		},
		setRepeat: function(state) {
			repeat = state;
		},
		getCurrentIndex: function() {
			return (playOrderIter >= 0) ? playOrder[playOrderIter] : null;
		},
		getCurrentPlaylistId: function() {
			return playlistId;
		},
		getCurrentPlaylist: function() {
			return playlist;
		},
		jumpToPrevTrack: function() {
			if (playlist && playOrderIter > 0) {
				--playOrderIter;
				track = playlist[this.getCurrentIndex()];
				this.publish('trackChanged', track);
				return track;
			}
			return null;
		},
		jumpToNextTrack: function() {
			if (playlist === null || playOrder === null) {
				return null;
			}

			// check if shuffle state has changed after the play order was last updated
			if (shuffle != prevShuffleState) {
				dropFuturePlayOrder();
				startFromIndex = playOrder[playOrderIter];
				playOrder = _.initial(playOrder); // drop also current index as it will be readded on next step
				enqueueIndices();
			}

			++playOrderIter;

			// check if we have run to the end of the enqueued tracks
			if (playOrderIter >= playOrder.length) {
				if (repeat) { // start another round
					enqueueIndices();
				} else { // we are done
					playOrderIter = -1;
					playlist = null;
					playlistId = null;
					this.publish('playlistEnded');
					return null;
				}
			}

			var track = playlist[this.getCurrentIndex()];
			this.publish('trackChanged', track);
			return track;
		},
		setPlaylist: function(listId, pl, startIndex /*optional*/) {
			playlist = pl.slice(); // copy
			startFromIndex = (startIndex === undefined) ? null : startIndex;
			if (listId === playlistId) {
				// preserve the history if list wasn't actually changed
				dropFuturePlayOrder();
			} else {
				// drop the history if list changed
				playOrder = [];
				playOrderIter = -1; // jumpToNextTrack will move this to first valid index
				playlistId = listId;
				this.publish('playlistChanged', playlistId);
			}
			enqueueIndices();
		},
		onPlaylistModified: function(pl, currentIndex) {
			var currentTrack = playlist[this.getCurrentIndex()];
			// check if the track being played is still available in the list
			if (pl[currentIndex] === currentTrack) {
				// re-init the play-order, erasing any history data
				playlist = pl.slice(); // copy
				startFromIndex = currentIndex;
				playOrder = [];
				enqueueIndices();
				playOrderIter = 0;
			}
			// if not, then we no longer have a valid list position
			else {
				playlist = null;
				playlistId = null;
				playOrder = null;
				playOrderIter = -1;
			}
			this.publish('trackChanged', currentTrack);
		},
		onTracksAdded: function(newTracks) {
			var prevListSize = playlist.length;
			playlist = playlist.concat(newTracks);
			var newIndices = _.range(prevListSize, playlist.length);
			if (prevShuffleState) {
				// Shuffle the new tracks with the remaining tracks on the list
				var remaining = _.tail(playOrder, playOrderIter+1);
				remaining = _.shuffle(remaining.concat(newIndices));
				playOrder = _.first(playOrder, playOrderIter+1).concat(remaining);
			}
			else {
				// Try to find the next position of the previously last track of the list,
				// and insert the new tracks in play order after that. If the index is not
				// found, then we have already wrapped over the last track and the new tracks
				// do not need to be added.
				var insertPos = _.indexOf(playOrder, prevListSize-1, playOrderIter);
				if (insertPos >= 0) {
					++insertPos;
					insertMany(playOrder, insertPos, newIndices);
				}
			}
		},
		publish: function(name, parameters) {
			$rootScope.$emit(name, parameters);
		},
		subscribe: function(name, listener) {
			return $rootScope.$on(name, listener);
		}
	};
}]);
