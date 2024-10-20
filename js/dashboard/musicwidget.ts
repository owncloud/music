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
import * as _ from 'lodash';

declare function t(module : string, text : string) : string;

const AMPACHE_API_URL = 'apps/music/ampache/server/json.server.php';

export class MusicWidget {

	#player: PlayerWrapper;
	#queue: PlayQueue;
	#volumeControl: VolumeControl;
	#progressInfo: ProgressInfo;
	#selectContainer: JQuery<HTMLElement>;
	#modeSelect: JQuery<HTMLSelectElement>;
	#filterSelects: JQuery<HTMLSelectElement>[];
	#trackListContainer: JQuery<HTMLElement>;
	#trackList: JQuery<HTMLUListElement>;
	#progressAndOrder: JQuery<HTMLElement>;
	#controls: JQuery<HTMLElement>;
	#debouncedPlayCurrent: CallableFunction;

	constructor($container: JQuery<HTMLElement>, player: PlayerWrapper, queue: PlayQueue) {
		this.#player = player;
		this.#queue = queue;
		this.#volumeControl = new VolumeControl(player);
		this.#progressInfo = new ProgressInfo(player);
		this.#selectContainer = $('<div class="select-container" />').appendTo($container);
		this.#filterSelects = [];
		this.#trackListContainer = $('<div class="tracks-container" />').appendTo($container);

		const types = [
			{ id: 'album_artists',	name: t('music', 'Album artists') },
			{ id: 'track_artists',	name: t('music', 'Track artists') },
			{ id: 'albums',			name: t('music', 'Albums') },
			{ id: 'genres',			name: t('music', 'Genres') },
			{ id: 'all_tracks',		name: t('music', 'All tracks') },
			{ id: 'playlists',		name: t('music', 'Playlists') },
			{ id: 'podcasts',		name: t('music', 'Podcasts') },
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

		this.#debouncedPlayCurrent = _.debounce(() => {
			const track = this.#queue.getCurrentTrack() as any;
			if (track !== null) {
				const $albumArt = this.#controls.find('.albumart');
				this.#loadBackgroundImage($albumArt, track.art);
				if ('artist' in track) {
					$albumArt.prop('title', `${track.name} (${track.artist.name})`);
					this.#player.fromUrl(track.url, track.stream_mime);
				} else {
					$albumArt.prop('title', track.name);
					this.#player.fromExtUrl(track.url, false);
				}

				this.#player.play();
			}
		}, 300);

		this.#queue.subscribe('trackChanged', (track) => {
			this.#player.stop();
			this.#debouncedPlayCurrent();

			this.#trackList.find('.current').removeClass('current');
			this.#trackList.find(`[data-index='${this.#queue.getCurrentIndex()}']`).addClass('current');

			this.#controls.find('.albumart').css('background-image', '').addClass('icon-loading');
			
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

	#loadBackgroundImage($albumArt: JQuery<HTMLElement>, url: string) {
		/* Load the image first using an out-of-DOM <img> element and then use the same image
		   as the background for the element. This is needed because loading the bacground-image
		   doesn't fire the onload event, making it impossible to timely remove the loading icon. */
		$('<img/>').attr('src', url).on('load', function() {
			$(this).remove(); // prevent memory leaks
			$albumArt.css('background-image', `url(${url})`).removeClass('icon-loading');
		});
	}

	#onModeSelect() : void {
		// remove the previous selections first
		this.#filterSelects.forEach((select) => select.remove());
		this.#filterSelects = [];
		this.#trackList?.remove();
		this.#trackList = null;

		switch (this.#modeSelect.val()) {
			case 'album_artists':
				this.#showAlbumArtists();
				break;
			case 'track_artists':
				this.#showTrackArtists();
				break;
			case 'albums':
				this.#showAlbums();
				break;
			case 'genres':
				this.#showGenres();
				break;
			case 'all_tracks':
				this.#showAllTracks();
				break;
			case 'playlists':
				this.#showPlaylists();
				break;
			case 'podcasts':
				this.#showPodcasts();
				break;
			default:
				console.error('unexpected mode selection:', this.#modeSelect.val());
		}
	}

	#showAlbumArtists() : void {
		ampacheApiAction('list', { type: 'album_artist' }, (result: any) => {
			this.#addFilterSelect(result.list, t('music', 'Select artist'), (artist) => {
				if (this.#filterSelects.length > 1) {
					this.#filterSelects.pop().remove();
				}
				this.#trackList?.remove();

				ampacheApiAction('browse', { type: 'artist', filter: artist.id }, (result: any) => {
					// Append the "(All albums)" option after the albums
					const albumOptions = result.browse.concat([{id: 'all', name: t('music', '(All albums)')}]);

					this.#addFilterSelect(albumOptions, t('music', 'Select album'), (album) => {
						if (album.id === 'all') {
							const searchArgs = { type: 'song', rule_1: 'album_artist_id', rule_1_operator: 0, rule_1_input: artist.id };
							this.#ampacheLoadAndShowTracks('search', searchArgs, artist.id);
						} else {
							this.#ampacheLoadAndShowTracks('album_songs', { filter: album.id }, artist.id);
						}
					});
				});
			});
		});
	}

	#showTrackArtists() : void {
		ampacheApiAction('get_indexes', { type: 'song_artist' }, (result: any) => {
			this.#addFilterSelect(
				result.artist,
				t('music', 'Select artist'),
				(artist) => {
					this.#ampacheLoadAndShowTracks('artist_songs', { filter: artist.id }, artist.id);
				}
			);
		});
	}

	#showAlbums() : void {
		ampacheApiAction('get_indexes', { type: 'album' }, (result: any) => {
			this.#addFilterSelect(
				result.album,
				t('music', 'Select album'),
				(album) => {
					this.#ampacheLoadAndShowTracks('album_songs', { filter: album.id }, album.artist.id);
				},
				(album) => `${album.name} (${album.artist.name})`
			);
		});
	}

	#showGenres() : void {
		ampacheApiAction('list', { type: 'genre' }, (result: any) => {
			this.#addFilterSelect(
				result.list,
				t('music', 'Select genre'),
				(genre) => {
					this.#ampacheLoadAndShowTracks('genre_songs', { filter: genre.id }, null);
				}
			);
		});
	}

	#showAllTracks() : void {
		this.#ampacheLoadAndShowTracks('songs', {}, null);
	}

	#showPlaylists() : void {
		ampacheApiAction('list', { type: 'playlist' }, (result: any) => {
			this.#addFilterSelect(
				result.list,
				t('music', 'Select playlist'),
				(playlist) => {
					this.#ampacheLoadAndShowTracks('playlist_songs', { filter: playlist.id }, null);
				}
			);
		});
	}

	#showPodcasts() : void {
		ampacheApiAction('list', { type: 'podcast' }, (result: any) => {
			this.#addFilterSelect(
				result.list,
				t('music', 'Select channel'),
				(channel) => {
					this.#ampacheLoadAndShowTracks('podcast_episodes', { filter: channel.id }, channel.id);
				}
			);
		});
	}

	#addFilterSelect(options: any[], placeholder: string, onChange: (selectedItem: any) => void, fmtTitle: (item: any) => string = null) {
		const filter = createSelect(options, placeholder, fmtTitle).appendTo(this.#selectContainer);

		filter.on('change', () => {
			const selItem = filter.find(":selected").data('item');
			onChange(selItem);
		});

		this.#filterSelects.push(filter);
	}

	#ampacheLoadAndShowTracks(action: string, args: JQuery.PlainObject, parentId: string|null) {
		this.#trackList?.remove();
		this.#trackListContainer.addClass('icon-loading');

		const listId = this.#getSelectedListId();
		ampacheApiAction(action, args, (result: any) => {
			this.#listTracks(listId, result.song ?? result.podcast_episode, parentId);
			this.#trackListContainer.removeClass('icon-loading');

			// highlight the current song if the currently playing list was re-entered
			if (this.#queue.getCurrentPlaylistId() == listId) {
				this.#trackList.find(`[data-index='${this.#queue.getCurrentIndex()}']`).addClass('current');
			}
		});
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

	#getSelectedListId() : string {
		let listId = this.#modeSelect.val().toString();

		this.#filterSelects.forEach((filter) => {
			listId += '/' + filter.val();
		});

		return listId;
	}
}

function createSelect(items: any[], placeholder: string|null = null, fmtTitle: (item: any) => string = null) : JQuery<HTMLSelectElement> {
	const $select = $('<select required/>') as JQuery<HTMLSelectElement>;

	if (placeholder !== null) {
		$select.append($('<option selected disabled hidden/>').attr('value', '').text(placeholder));
	}

	if (fmtTitle === null) {
		fmtTitle = (item: any) => item.name;
	}

	$(items).each(function() {
		$("<option/>").attr('value', this.id).text(fmtTitle(this)).data('item', this).appendTo($select);
	});
	return $select;
}

function createTrackList(tracks: any[], parentId: string|null) : JQuery<HTMLUListElement> {
	const $ul = $('<ul/>') as JQuery<HTMLUListElement>;
	$(tracks).each(function(index: number) {
		let liContent = this.name;
		let tooltip = this.name;
		if ('artist' in this && this.artist.id != parentId) {
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

function ampacheApiAction(action: string, args: JQuery.PlainObject, callback: JQuery.jqXHR.DoneCallback) : void {
	const url = OC.generateUrl(AMPACHE_API_URL);
	args['action'] = action;
	args['auth'] = 'internal';

	$.get(url, args, callback).fail((error) => {
		console.error(error)
	});
}
