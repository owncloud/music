/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2024
 */

import { PlayerWrapper } from "./playerwrapper";

interface BrowserMediaSessionInfo {
	title? : string|null;
	artist? : string|null;
	album? : string|null;
	cover? : string|null;
	coverMime? : string|null;
};

interface BrowserMediaSessionControls {
	play? : null | (() => void);
	pause? : null | (() => void);
	stop? : null | (() => void);
	seekBackward? : null | (() => void);
	seekForward? : null | (() => void);
	previousTrack? : null | (() => void);
	nextTrack? : null | (() => void);
}

/**
 * Wrapper for the media session API available on many modern browsers, including at least
 * Chrome, Edge, and Firefox. The API brings the bindings with the special multimedia keys
 * possibly present on the keyboard, as well as any OS multimedia controls available e.g. 
 * in status pane and/or lock screen.
 * 
 * This class can be safely used regardless of the capabilities of the browser used.
 */
export class BrowserMediaSession {

	constructor(player : PlayerWrapper) {
		player.on('play', () => {
			this.#setPlayingState(true);
		});
		player.on('pause', () => {
			this.#setPlayingState(false);
		});
		player.on('stop', () => {
			this.#setPlayingState(false);
		});
	}

	registerControls(controls : BrowserMediaSessionControls) : void {
		if ('mediaSession' in navigator) {
			const registerHandler = (action : MediaSessionAction, handler : () => void) => {
				try {
					navigator.mediaSession.setActionHandler(action, handler);
				} catch (error) {
					console.log('The media control "' + action + '" is not supported by the browser');
				}
			};
	
			registerHandler('play', controls.play);
			registerHandler('pause', controls.pause);
			registerHandler('stop', controls.stop);
			registerHandler('seekbackward', controls.seekBackward);
			registerHandler('seekforward', controls.seekForward);
			registerHandler('previoustrack', controls.previousTrack);
			registerHandler('nexttrack', controls.nextTrack);
		}
	}

	showInfo(info : BrowserMediaSessionInfo) : void {
		if ('mediaSession' in navigator) {
			navigator.mediaSession.metadata = new MediaMetadata({
				title: info.title ?? undefined,
				artist: info.artist ?? undefined,
				album: info.album ?? undefined,
				artwork: [{
					src: info.cover ?? '',
					type: info.coverMime ?? undefined
				}]
			});
		}
	}

	clearInfo() : void {
		if ('mediaSession' in navigator) {
			navigator.mediaSession.metadata = null;
		}
	}

	#setPlayingState(playing : boolean) : void {
		if ('mediaSession' in navigator) {
			navigator.mediaSession.playbackState = playing ? 'playing' : 'paused';
		}
	}
}