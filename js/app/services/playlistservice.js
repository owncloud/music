/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2013
 * @copyright Pauli Järvinen 2017
 */

angular.module('Music').service('playlistService', ['$rootScope', function($rootScope) {
	var playlist = null;
	var playOrder = [];
	var playOrderIter = -1;
	var startFromIndex = null;
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

	function initPlayOrder(shuffle) {
		if (shuffle) {
			if (startFromIndex !== null) {
				playOrder = [startFromIndex].concat(shuffledIndicesExcluding(startFromIndex));
			} else {
				playOrder = shuffledIndices();
			}
		}
		else {
			playOrder = _.range(playlist.length);
			if (startFromIndex !== null) {
				playOrder = wrapIndexToStart(playOrder, startFromIndex);
			}
		}
		prevShuffleState = shuffle;
	}

	function enqueueIndices(shuffle) {
		var prevIndex = _.last(playOrder);
		var nextIndices = null;

		// Append playlist indices in suitable order, excluding the previously played index
		// to prevent the same track from playing twice in row. Playlist containing only a
		// single track is a special case as there we cannot exclude our only track.
		if (playlist.length === 1) {
			nextIndices = [0];
		} else if (shuffle) {
			nextIndices = shuffledIndicesExcluding(prevIndex);
		} else {
			nextIndices = wrapIndexToStart(_.range(playlist.length), prevIndex);
			nextIndices = _.rest(nextIndices);
		}

		playOrder = playOrder.concat(nextIndices);
	}

	function checkShuffleStateChange(currentShuffleState) {
		if (currentShuffleState != prevShuffleState) {
			// Drop any future indices from the play order when the shuffle state changes
			// and enqueue one playlist worth of indices according the new state.
			playOrder = _.first(playOrder, playOrderIter);
			enqueueIndices(currentShuffleState);
			prevShuffleState = currentShuffleState;
		}
	}

	function insertMany(hostArray, targetIndex, insertedItems) {
		hostArray.splice.apply(hostArray, [targetIndex, 0].concat(insertedItems));
	}

	return {
		getCurrentIndex: function() {
			return (playOrderIter >= 0) ? playOrder[playOrderIter] : null;
		},
		jumpToPrevTrack: function() {
			if(playlist && playOrderIter > 0) {
				--playOrderIter;
				track = playlist[this.getCurrentIndex()];
				this.publish('trackChanged', track);
				return track;
			}
			return null;
		},
		jumpToNextTrack: function(repeat, shuffle) {
			if (playlist === null) {
				return null;
			}
			if (!playOrder) {
				initPlayOrder(shuffle);
			}
			++playOrderIter;
			checkShuffleStateChange(shuffle);

			// check if we have run to the end of the enqueued tracks
			if (playOrderIter >= playOrder.length) {
				if (repeat) { // start another round
					enqueueIndices(shuffle);
				} else { // we are done
					playOrderIter = -1;
					playlist = null;
					this.publish('playlistEnded');
					return null;
				}
			}

			var track = playlist[this.getCurrentIndex()];
			this.publish('trackChanged', track);
			return track;
		},
		setPlaylist: function(pl, startIndex /*optional*/) {
			playlist = pl.slice(); // copy
			playOrder = null;
			playOrderIter = -1;
			startFromIndex = (startIndex === undefined) ? null : startIndex;
		},
		onPlaylistModified: function(pl, currentIndex) {
			var currentTrack = playlist[this.getCurrentIndex()];
			// check if the track being played is still available in the list
			if (pl[currentIndex] === currentTrack) {
				// re-init the play-order, erasing any history data
				playlist = pl.slice(); // copy
				playOrderIter = 0;
				startFromIndex = currentIndex;
				initPlayOrder(prevShuffleState);
			}
			// if not, then we no longer have a valid list position
			else {
				playlist = null;
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
