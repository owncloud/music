/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2013
 * @copyright Pauli Järvinen 2017 - 2023
 */

import * as ng from "angular";
import * as _ from "lodash";
import { MusicRootScope } from "app/config/musicrootscope";

interface PlaylistEntry {
};

ng.module('Music').service('playlistService', ['$rootScope', function($rootScope : MusicRootScope) {
	let playlist : PlaylistEntry[]|null = null;
	let playlistId : string|null = null;
	let playOrder : number[] = [];
	let playOrderIter = -1;
	let startFromIndex : number|null = null;
	let shuffle = false;
	let repeat = false;
	let prevShuffleState = false;

	function shuffledIndices() : number[] {
		let indices = _.range(playlist.length);
		return _.shuffle(indices);
	}

	function shuffledIndicesExcluding(toExclude : number) : number[] {
		let indices = _.range(playlist.length);
		indices.splice(toExclude, 1);
		return _.shuffle(indices);
	}

	function wrapIndexToStart<Type>(list : Type[], index : number) : Type[] {
		if (index > 0) {
			// slice array in two parts and interchange them
			let begin = list.slice(0, index);
			let end = list.slice(index);
			list = end.concat(begin);
		}
		return list;
	}

	function enqueueIndices() : void {
		let nextIndices : number[] = null;

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
	function dropFuturePlayOrder() : void {
		playOrder = _.take(playOrder, playOrderIter + 1);
	}

	function insertMany(hostArray : number[], targetIndex : number, insertedItems : number[]) : void {
		hostArray.splice.apply(hostArray, [targetIndex, 0].concat(insertedItems));
	}

	return {
		setShuffle(state : boolean) : void {
			shuffle = state;
		},
		setRepeat(state : boolean) : void {
			repeat = state;
		},
		getCurrentIndex() : number|null {
			return (playOrderIter >= 0) ? playOrder[playOrderIter] : null;
		},
		getCurrentPlaylistId() : string|null {
			return playlistId;
		},
		getCurrentPlaylist() : PlaylistEntry[]|null {
			return playlist;
		},
		jumpToPrevTrack() : PlaylistEntry|null {
			if (playlist && playOrderIter > 0) {
				--playOrderIter;
				let track = playlist[this.getCurrentIndex()];
				this.publish('trackChanged', track);
				return track;
			}
			return null;
		},
		jumpToNextTrack() : PlaylistEntry|null {
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
					this.clearPlaylist();
					return null;
				}
			}

			let track = playlist[this.getCurrentIndex()];
			this.publish('trackChanged', track);
			return track;
		},
		peekNextTrack() : PlaylistEntry|null {
			// The next track may be peeked only when there are forthcoming tracks already enqueued, not when jumping
			// to the next track would start a new round in the Repeat mode
			if (playlist === null || playOrder === null || playOrderIter < 0 || playOrderIter >= playOrder.length - 1) {
				return null;
			} else {
				return playlist[playOrder[playOrderIter + 1]];
			}
		},
		setPlaylist(listId : string, pl : PlaylistEntry[], startIndex : number|null = null) : void {
			playlist = pl.slice(); // copy
			startFromIndex = startIndex;
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
		clearPlaylist() : void {
			playOrderIter = -1;
			playlist = null;
			playlistId = null;
			this.publish('playlistEnded');
		},
		onPlaylistModified(pl : PlaylistEntry[], currentIndex : number) : void {
			let currentTrack = playlist[this.getCurrentIndex()];
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
		onTracksAdded(newTracks : PlaylistEntry[]) : void {
			let prevListSize = playlist.length;
			playlist = playlist.concat(newTracks);
			let newIndices = _.range(prevListSize, playlist.length);
			if (prevShuffleState) {
				// Shuffle the new tracks with the remaining tracks on the list
				let remaining = _.drop(playOrder, playOrderIter+1);
				remaining = _.shuffle(remaining.concat(newIndices));
				playOrder = _.take(playOrder, playOrderIter+1).concat(remaining);
			}
			else {
				// Try to find the next position of the previously last track of the list,
				// and insert the new tracks in play order after that. If the index is not
				// found, then we have already wrapped over the last track and the new tracks
				// do not need to be added.
				let insertPos = _.indexOf(playOrder, prevListSize-1, playOrderIter);
				if (insertPos >= 0) {
					++insertPos;
					insertMany(playOrder, insertPos, newIndices);
				}
			}
		},
		publish(name : string, ...args : any[]) : void {
			$rootScope.$emit(name, ...args);
		},
		subscribe(name : string, listener : (event: ng.IAngularEvent, ...args: any[]) => any) : () => void {
			return $rootScope.$on(name, listener);
		}
	};
}]);
