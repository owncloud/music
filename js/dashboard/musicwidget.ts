/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2024
 */

declare function t(module : string, text : string) : string;

export class MusicWidget {

	#selectContainer: JQuery<HTMLElement> = null;
	#modeSelect: JQuery<HTMLSelectElement> = null;
	#parent1Select: JQuery<HTMLSelectElement> = null;
	#parent2Select: JQuery<HTMLSelectElement> = null;
	#trackListContainer: JQuery<HTMLElement> = null;
	#trackList: JQuery<HTMLUListElement> = null;

	constructor($container: JQuery<HTMLElement>) {
		this.#selectContainer = $('<div class="select-container" />').appendTo($container);
		this.#trackListContainer = $('<div class="tracks-container" />').appendTo($container);

		const types = [
			{ id: 'album_artists',	name: t('music', 'Album artists') },
			{ id: 'all_tracks',		name: t('music', 'All tracks') }
		];
		const placeholder = t('music', 'Select mode');
		this.#modeSelect = createSelect(types, placeholder).appendTo(this.#selectContainer).on('change', () => this.#onModeSelect());
	}

	#onModeSelect() {
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

	#showAlbumArtists() {
		ampacheApiAction('list', { type: 'album_artist' }, (result: any) => {
			this.#parent1Select = createSelect(result.list, t('music', 'Select artist')).appendTo(this.#selectContainer);
			this.#parent1Select.on('change', () => {
				this.#parent2Select?.remove();
				this.#trackList?.remove();
				const artistId = this.#parent1Select.val();
				ampacheApiAction('artist_albums', { filter: artistId }, (result: any) => {
					this.#parent2Select = createSelect(result.album, t('music', 'Select album')).appendTo(this.#selectContainer);
					this.#parent2Select.on('change', () => {
						this.#trackList?.remove();
						const albumId = this.#parent2Select.val();
						ampacheApiAction('album_songs', { filter: albumId }, (result: any) => {
							this.#trackList = createTrackList(result.song).appendTo(this.#trackListContainer);
						})
					});
				});
			});
		});
	}

	#showAllTracks() {
		//TODO
	}
}

function createSelect(items: any[], placeholder: string|null = null) : JQuery<HTMLSelectElement> {
	const $select = $('<select/>').attr('required', '') as JQuery<HTMLSelectElement>;

	if (placeholder !== null) {
		$select.append($("<option/>").attr('value', '').text(placeholder).attr('selected', '').attr('disabled', '').attr('hidden', ''));
	}

	$(items).each(function() {
		$select.append($("<option/>").attr('value', this.id).text(this.name));
	});
	return $select;
}

function createTrackList(tracks: any[]) : JQuery<HTMLUListElement> {
	const $ul = $('<ul/>') as JQuery<HTMLUListElement>;
	$(tracks).each(function() {
		$ul.append($("<li/>").attr('data-id', this.id).text(this.name));
	});

	return $ul;
}

function ampacheApiAction(action: string, args: JQuery.PlainObject, callback: JQuery.jqXHR.DoneCallback) {
	const url = OC.generateUrl('apps/music/ampache/server/json.server.php');
	args['action'] = action;
	args['auth'] = 'internal';

	$.get(url, args, callback).fail((error) => {
		console.error(error)
	});
}
