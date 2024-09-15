/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2024
 */

import { PlayerWrapper } from "shared/playerwrapper";
import { PlayQueue } from "shared/playqueue";

declare function t(module : string, text : string) : string;

const AMPACHE_API_URL = 'apps/music/ampache/server/json.server.php';

export class MusicWidget {

	#player: PlayerWrapper;
	#queue: PlayQueue;
	#selectContainer: JQuery<HTMLElement>;
	#modeSelect: JQuery<HTMLSelectElement>;
	#parent1Select: JQuery<HTMLSelectElement>;
	#parent2Select: JQuery<HTMLSelectElement>;
	#trackListContainer: JQuery<HTMLElement>;
	#trackList: JQuery<HTMLUListElement>;
	#controls: JQuery<HTMLElement>;

	constructor($container: JQuery<HTMLElement>, player: PlayerWrapper, queue: PlayQueue) {
		this.#player = player;
		this.#queue = queue;
		this.#selectContainer = $('<div class="select-container" />').appendTo($container);
		this.#trackListContainer = $('<div class="tracks-container" />').appendTo($container);

		const types = [
			{ id: 'album_artists',	name: t('music', 'Album artists') },
			{ id: 'all_tracks',		name: t('music', 'All tracks') }
		];
		const placeholder = t('music', 'Select mode');
		this.#modeSelect = createSelect(types, placeholder).appendTo(this.#selectContainer).on('change', () => this.#onModeSelect());
		this.#controls = createControls(
			() => this.#player.togglePlay(),
			() => this.#jumpToPrev(),
			() => this.#jumpToNext()
		).appendTo($container);

		this.#queue.subscribe('trackChanged', (track) => {
			player.fromUrl(track.url, track.stream_mime);
			player.play();

			this.#trackList.find('.current').removeClass('current');
			this.#trackList.find(`[data-index='${this.#queue.getCurrentIndex()}']`).addClass('current');
		});

		this.#queue.subscribe('playlistEnded', () => {
			player.stop();
		});
	}

	#onModeSelect() : void {
		// remove the previous selections first
		this.#parent1Select?.remove();
		this.#parent2Select?.remove();
		this.#trackList?.remove();

		switch (this.#modeSelect.val()) {
			case 'album_artists':
				this.#showAlbumArtists();
				break;
			case 'all_tracks':
				this.#showAllTracks();
				break;
			default:
				console.error('unexpected mode selection:', this.#modeSelect.val());
		}
	}

	#showAlbumArtists() : void {
		ampacheApiAction('list', { type: 'album_artist' }, (result: any) => {
			this.#parent1Select = createSelect(result.list, t('music', 'Select artist')).appendTo(this.#selectContainer);
			this.#parent1Select.on('change', () => {
				this.#parent2Select?.remove();
				this.#trackList?.remove();
				const artistId = this.#parent1Select.val() as string;
				ampacheApiAction('artist_albums', { filter: artistId }, (result: any) => {
					this.#parent2Select = createSelect(result.album, t('music', 'Select album')).appendTo(this.#selectContainer);
					this.#parent2Select.on('change', () => {
						this.#trackList?.remove();
						const albumId = this.#parent2Select.val();
						ampacheApiAction('album_songs', { filter: albumId }, (result: any) => this.#listTracks('album-' + albumId, result.song, artistId));
					});
				});
			});
		});
	}

	#showAllTracks() : void {
		ampacheApiAction('songs', {}, (result: any) => this.#listTracks('all-tracks', result.song, null));
	}

	#listTracks(listId: string, tracks: any[], parentId: string|null) : void {
		const player = this.#player;
		const queue = this.#queue;

		this.#trackList = createTrackList(tracks, parentId).appendTo(this.#trackListContainer);
		
		this.#trackList.on('click', 'li', function(_event) {
			const $el = $(this);
			const index = $el.data('index');
			if (listId == queue.getCurrentPlaylistId() && index == queue.getCurrentIndex()) {
				player.togglePlay();
			}
			else {
				queue.setPlaylist(listId, tracks, index);
				queue.jumpToNextTrack();
			}
		});
	}

	#jumpToNext() {
		this.#queue.jumpToNextTrack();
	}

	#jumpToPrev() {
		this.#queue.jumpToPrevTrack();
	}
}

function createSelect(items: any[], placeholder: string|null = null) : JQuery<HTMLSelectElement> {
	const $select = $('<select required/>') as JQuery<HTMLSelectElement>;

	if (placeholder !== null) {
		$select.append($('<option selected disabled hidden/>').attr('value', '').text(placeholder));
	}

	$(items).each(function() {
		$select.append($("<option/>").attr('value', this.id).text(this.name));
	});
	return $select;
}

function createTrackList(tracks: any[], parentId: string|null) : JQuery<HTMLUListElement> {
	const $ul = $('<ul/>') as JQuery<HTMLUListElement>;
	$(tracks).each(function(index: number) {
		let liContent = this.name;
		let tooltip = this.name;
		if (this.artist.id != parentId) {
			liContent += ` <span class="dimmed">(${this.artist.name})</span>`;
			tooltip += ` (${this.artist.name})`
		}
		$(`<li title="${tooltip}">${liContent}</li>`).data('index', index).appendTo($ul); // each item stores a `data` reference to its index
	});

	return $ul;
}

function createControls(onPlayPause : CallableFunction, onPrev : CallableFunction, onNext : CallableFunction) : JQuery<HTMLElement> {
	const $container = $('<div class="player-controls"/>');
	const $albumArt = $('<div class="albumart icon-music"/>').appendTo($container);
	const $prev = $('<div class="control icon-skip-prev"/>').appendTo($container).on('click', () => onPrev());
	const $play = $('<div class="control icon-play"/>').appendTo($container).on('click', () => onPlayPause());
	const $next = $('<div class="control icon-skip-next"/>').appendTo($container).on('click', () => onNext());
	return $container;
}

function ampacheApiAction(action: string, args: JQuery.PlainObject, callback: JQuery.jqXHR.DoneCallback) {
	const url = OC.generateUrl(AMPACHE_API_URL);
	args['action'] = action;
	args['auth'] = 'internal';

	$.get(url, args, callback).fail((error) => {
		console.error(error)
	});
}
