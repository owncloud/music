/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2013
 * @copyright Pauli Järvinen 2017 - 2024
 */

import * as _ from "lodash";

interface PlayQueueEntry {
};

export class PlayQueue {
	#list : PlayQueueEntry[]|null = null;
	#listId : string|null = null;
	#playOrder : number[] = [];
	#playOrderIter = -1;
	#startFromIndex : number|null = null;
	#shuffle = false;
	#repeat = false;
	#prevShuffleState = false;
	#eventDispatcher = _.clone(OC.Backbone.Events);

	/* -------------------------------
	 * PRIVATE METHODS
	 * -------------------------------
	 */

	#shuffledIndices() : number[] {
		let indices = _.range(this.#list.length);
		return _.shuffle(indices);
	}

	#shuffledIndicesExcluding(toExclude : number) : number[] {
		let indices = _.range(this.#list.length);
		indices.splice(toExclude, 1);
		return _.shuffle(indices);
	}

	#wrapIndexToStart<Type>(list : Type[], index : number) : Type[] {
		if (index > 0) {
			// slice array in two parts and interchange them
			let begin = list.slice(0, index);
			let end = list.slice(index);
			list = end.concat(begin);
		}
		return list;
	}

	#enqueueIndices() : void {
		let nextIndices : number[] = null;

		if (this.#shuffle) {
			if (this.#startFromIndex !== null) {
				nextIndices = [this.#startFromIndex].concat(this.#shuffledIndicesExcluding(this.#startFromIndex));
			} else {
				nextIndices = this.#shuffledIndices();
			}
			// if the next index ended up to be tha same as the pervious one, flip
			// it to the end of the order
			if (this.#list.length > 1 && _.last(this.#playOrder) == _.first(nextIndices)) {
				nextIndices = this.#wrapIndexToStart(nextIndices, 1);
			}
		}
		else {
			nextIndices = _.range(this.#list.length);
			if (this.#startFromIndex !== null) {
				nextIndices = this.#wrapIndexToStart(nextIndices, this.#startFromIndex);
			}
		}

		this.#playOrder = this.#playOrder.concat(nextIndices);
		this.#prevShuffleState = this.#shuffle;
	}

	// drop the planned play order but preserve the history
	#dropFuturePlayOrder() : void {
		this.#playOrder = _.take(this.#playOrder, this.#playOrderIter + 1);
	}

	#insertMany(hostArray : number[], targetIndex : number, insertedItems : number[]) : void {
		hostArray.splice.apply(hostArray, [targetIndex, 0].concat(insertedItems));
	}

	/* -------------------------------
	 * PUBLIC METHODS
	 * -------------------------------
	 */

	setShuffle(state : boolean) : void {
		this.#shuffle = state;
	}

	setRepeat(state : boolean) : void {
		this.#repeat = state;
	}

	getCurrentIndex() : number|null {
		return (this.#playOrderIter >= 0) ? this.#playOrder[this.#playOrderIter] : null;
	}

	getCurrentPlaylistId() : string|null {
		return this.#listId;
	}

	getCurrentPlaylist() : PlayQueueEntry[]|null {
		return this.#list;
	}

	jumpToPrevTrack() : PlayQueueEntry|null {
		if (this.#list && this.#playOrderIter > 0) {
			--this.#playOrderIter;
			let track = this.#list[this.getCurrentIndex()];
			this.publish('trackChanged', track);
			return track;
		}
		return null;
	}

	jumpToNextTrack() : PlayQueueEntry|null {
		if (this.#list === null || this.#playOrder === null) {
			return null;
		}

		// check if shuffle state has changed after the play order was last updated
		if (this.#shuffle != this.#prevShuffleState) {
			this.#dropFuturePlayOrder();
			this.#startFromIndex = this.#playOrder[this.#playOrderIter];
			this.#playOrder = _.initial(this.#playOrder); // drop also current index as it will be re-added on next step
			this.#enqueueIndices();
		}

		++this.#playOrderIter;

		// check if we have run to the end of the enqueued tracks
		if (this.#playOrderIter >= this.#playOrder.length) {
			if (this.#repeat) { // start another round
				this.#enqueueIndices();
			} else { // we are done
				this.clearPlaylist();
				return null;
			}
		}

		let track = this.#list[this.getCurrentIndex()];
		this.publish('trackChanged', track);
		return track;
	}

	peekNextTrack() : PlayQueueEntry|null {
		// The next track may be peeked only when there are forthcoming tracks already enqueued, not when jumping
		// to the next track would start a new round in the Repeat mode
		if (this.#list === null || this.#playOrder === null || this.#playOrderIter < 0 || this.#playOrderIter >= this.#playOrder.length - 1) {
			return null;
		} else {
			return this.#list[this.#playOrder[this.#playOrderIter + 1]];
		}
	}

	setPlaylist(listId : string, pl : PlayQueueEntry[], startIndex : number|null = null) : void {
		this.#list = pl.slice(); // copy
		this.#startFromIndex = startIndex;
		if (listId === this.#listId) {
			// preserve the history if list wasn't actually changed
			this.#dropFuturePlayOrder();
		} else {
			// drop the history if list changed
			this.#playOrder = [];
			this.#playOrderIter = -1; // jumpToNextTrack will move this to first valid index
			this.#listId = listId;
			this.publish('playlistChanged', this.#listId);
		}
		this.#enqueueIndices();
	}

	clearPlaylist() : void {
		this.#playOrderIter = -1;
		this.#list = null;
		this.#listId = null;
		this.publish('playlistEnded');
	}

	onPlaylistModified(pl : PlayQueueEntry[], currentIndex : number) : void {
		let currentTrack = this.#list[this.getCurrentIndex()];
		// check if the track being played is still available in the list
		if (pl[currentIndex] === currentTrack) {
			// re-init the play-order, erasing any history data
			this.#list = pl.slice(); // copy
			this.#startFromIndex = currentIndex;
			this.#playOrder = [];
			this.#enqueueIndices();
			this.#playOrderIter = 0;
		}
		// if not, then we no longer have a valid list position
		else {
			this.#list = null;
			this.#listId = null;
			this.#playOrder = null;
			this.#playOrderIter = -1;
		}
		this.publish('trackChanged', currentTrack);
	}

	onTracksAdded(newTracks : PlayQueueEntry[]) : void {
		let prevListSize = this.#list.length;
		this.#list = this.#list.concat(newTracks);
		let newIndices = _.range(prevListSize, this.#list.length);
		if (this.#prevShuffleState) {
			// Shuffle the new tracks with the remaining tracks on the list
			let remaining = _.drop(this.#playOrder, this.#playOrderIter+1);
			remaining = _.shuffle(remaining.concat(newIndices));
			this.#playOrder = _.take(this.#playOrder, this.#playOrderIter+1).concat(remaining);
		}
		else {
			// Try to find the next position of the previously last track of the list,
			// and insert the new tracks in play order after that. If the index is not
			// found, then we have already wrapped over the last track and the new tracks
			// do not need to be added.
			let insertPos = _.indexOf(this.#playOrder, prevListSize-1, this.#playOrderIter);
			if (insertPos >= 0) {
				++insertPos;
				this.#insertMany(this.#playOrder, insertPos, newIndices);
			}
		}
	}

	publish(name : string, ...args : any[]) : void {
		this.#eventDispatcher.trigger(name, ...args);
	}

	subscribe(name : string, listener : (...args: any[]) => any, context : any = null) : void {
		// the context must be supplied if there is ever a need to unsubscribe
		this.#eventDispatcher.on(name, listener, context);
	}

	unsubscribeAll(context : any) {
		this.#eventDispatcher.off(null, null, context);
	}
}
