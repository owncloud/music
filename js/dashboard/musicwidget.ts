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
import { ProgressInfo } from "shared/progressinfo";
import { VolumeControl } from "shared/volumecontrol";

declare function t(module : string, text : string) : string;

const AMPACHE_API_URL = 'apps/music/ampache/server/json.server.php';

export class MusicWidget {

	#player: PlayerWrapper;
	#queue: PlayQueue;
	#volumeControl: VolumeControl;
	#progressInfo: ProgressInfo;
	#selectContainer: JQuery<HTMLElement>;
	#modeSelect: JQuery<HTMLSelectElement>;
	#parent1Select: JQuery<HTMLSelectElement>;
	#parent2Select: JQuery<HTMLSelectElement>;
	#trackListContainer: JQuery<HTMLElement>;
	#trackList: JQuery<HTMLUListElement>;
	#progressAndOrder: JQuery<HTMLElement>;
	#controls: JQuery<HTMLElement>;

	constructor($container: JQuery<HTMLElement>, player: PlayerWrapper, queue: PlayQueue) {
		this.#player = player;
		this.#queue = queue;
		this.#volumeControl = new VolumeControl(player);
		this.#progressInfo = new ProgressInfo(player);
		this.#selectContainer = $('<div class="select-container" />').appendTo($container);
		this.#trackListContainer = $('<div class="tracks-container" />').appendTo($container);

		const types = [
			{ id: 'album_artists',	name: t('music', 'Album artists') },
			{ id: 'all_tracks',		name: t('music', 'All tracks') }
		];
		const placeholder = t('music', 'Select mode');
		this.#modeSelect = createSelect(types, placeholder).appendTo(this.#selectContainer).on('change', () => this.#onModeSelect());
		this.#progressAndOrder = createProgressAndOrder(
			this.#progressInfo,
			() => this.#setShuffle(!this.#queue.getShuffle()),
			() => this.#setRepeat(!this.#queue.getRepeat())
		).hide().appendTo($container);
		this.#controls = createControls(
			() => this.#player.play(),
			() => this.#player.pause(),
			() => this.#jumpToPrev(),
			() => this.#jumpToNext(),
			this.#volumeControl
		).hide().appendTo($container);

		this.#setShuffle(OCA.Music.Storage.get('shuffle') === 'true');
		this.#setRepeat(OCA.Music.Storage.get('repeat') === 'true');

		this.#queue.subscribe('trackChanged', (track) => {
			player.fromUrl(track.url, track.stream_mime);
			player.play();

			this.#trackList.find('.current').removeClass('current');
			this.#trackList.find(`[data-index='${this.#queue.getCurrentIndex()}']`).addClass('current');

			this.#controls.find('.albumart').css('background-image', `url(${track.art})`)
				.prop('title', `${track.name} (${track.artist.name})`);
			
			this.#progressAndOrder.show();
			this.#controls.show();
		});

		this.#queue.subscribe('playlistEnded', () => {
			player.stop();
			this.#progressAndOrder.hide();
			this.#controls.hide();
		});

		this.#player.on('play', () => {
			this.#controls.find('.icon-play').hide();
			this.#controls.find('.icon-pause').show();
		});

		this.#player.on('pause', () => {
			this.#controls.find('.icon-play').show();
			this.#controls.find('.icon-pause').hide();
		});

		this.#player.on('end', () => this.#jumpToNext());
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

	#jumpToNext() : void {
		this.#queue.jumpToNextTrack();
	}

	#jumpToPrev() : void {
		this.#queue.jumpToPrevTrack();
	}

	#setShuffle(active : boolean) : void {
		if (active) {
			this.#progressAndOrder.find('.icon-shuffle').addClass('active');
		} else {
			this.#progressAndOrder.find('.icon-shuffle').removeClass('active');
		}
		this.#queue.setShuffle(active);
		OCA.Music.Storage.set('shuffle', active.toString());
	}

	#setRepeat(active : boolean) : void {
		if (active) {
			this.#progressAndOrder.find('.icon-repeat').addClass('active');
		} else {
			this.#progressAndOrder.find('.icon-repeat').removeClass('active');
		}
		this.#queue.setRepeat(active);
		OCA.Music.Storage.set('repeat', active.toString());
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
		// Each item stores a `data` reference to its index. This is done using the jQuery .attr() instead of .data() because
		// the latter doesn't store the reference to the DOM itself, making finding the element by the attribute impossible.
		$(`<li title="${tooltip}">${liContent}</li>`).attr('data-index', index).appendTo($ul);
	});

	return $ul;
}

function createProgressAndOrder(progress : ProgressInfo, onShuffleBtn : CallableFunction, onRepeatBtn : CallableFunction) : JQuery<HTMLElement>
{
	const $container = $('<div class="progress-and-order"/>');
	$('<div class="control toggle icon-shuffle"/>').appendTo($container).on('click', () => onShuffleBtn());
	progress.addToContainer($container);
	$('<div class="control toggle icon-repeat"/>').appendTo($container).on('click', () => onRepeatBtn());
	return $container;
}

function createControls(onPlay : CallableFunction, onPause : CallableFunction, onPrev : CallableFunction, onNext : CallableFunction, volumeControl : VolumeControl) : JQuery<HTMLElement> {
	const $container = $('<div class="player-controls"/>');
	$('<div class="albumart"/>').appendTo($container);
	$('<div class="playback control icon-skip-prev"/>').appendTo($container).on('click', () => onPrev());
	$('<div class="playback control icon-play"/>').appendTo($container).on('click', () => onPlay());
	$('<div class="playback control icon-pause"/>').appendTo($container).on('click', () => onPause()).hide();
	$('<div class="playback control icon-skip-next"/>').appendTo($container).on('click', () => onNext());
	volumeControl.addToContainer($container);
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
