/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2024
 */

import { PlayerWrapper } from "../shared/playerwrapper";

declare function t(module : string, text : string) : string;

const AMPACHE_API_URL = 'apps/music/ampache/server/json.server.php';

export class MusicWidget {

	#player: PlayerWrapper;
	#selectContainer: JQuery<HTMLElement>;
	#modeSelect: JQuery<HTMLSelectElement>;
	#parent1Select: JQuery<HTMLSelectElement>;
	#parent2Select: JQuery<HTMLSelectElement>;
	#trackListContainer: JQuery<HTMLElement>;
	#trackList: JQuery<HTMLUListElement>;

	constructor($container: JQuery<HTMLElement>, player: PlayerWrapper) {
		this.#player = player;
		this.#selectContainer = $('<div class="select-container" />').appendTo($container);
		this.#trackListContainer = $('<div class="tracks-container" />').appendTo($container);

		const types = [
			{ id: 'album_artists',	name: t('music', 'Album artists') },
			{ id: 'all_tracks',		name: t('music', 'All tracks') }
		];
		const placeholder = t('music', 'Select mode');
		this.#modeSelect = createSelect(types, placeholder).appendTo(this.#selectContainer).on('change', () => this.#onModeSelect());
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
						ampacheApiAction('album_songs', { filter: albumId }, (result: any) => this.#createTrackList(result.song, artistId));
					});
				});
			});
		});
	}

	#showAllTracks() : void {
		ampacheApiAction('songs', {}, (result: any) => this.#createTrackList(result.song, null));
	}

	#createTrackList(tracks: any[], parentId: string|null) : void {
		const player = this.#player;
		this.#trackList = createTrackList(tracks, parentId).appendTo(this.#trackListContainer);
		
		this.#trackList.on('click', 'li', function(_event) {
			const $el = $(this);
			const url = $el.data('url');
			if (url == player.getUrl()) {
				if (player.isPlaying()) {
					player.pause();
				} else {
					player.play();
				}
			}
			else {
				player.fromUrl(url, $el.data('stream_mime'));
				player.play();
			}
		});
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
	$(tracks).each(function() {
		let liContent = this.name;
		if (this.artist.id != parentId) {
			liContent += ` <span class="dimmed">(${this.artist.name})</span>`;
		}
		$(`<li>${liContent}</li>`).data(this).appendTo($ul); // each item stores a `data` reference to the original song object
	});

	return $ul;
}

function ampacheApiAction(action: string, args: JQuery.PlainObject, callback: JQuery.jqXHR.DoneCallback) {
	const url = OC.generateUrl(AMPACHE_API_URL);
	args['action'] = action;
	args['auth'] = 'internal';

	$.get(url, args, callback).fail((error) => {
		console.error(error)
	});
}
