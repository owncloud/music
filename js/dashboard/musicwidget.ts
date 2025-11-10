/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2024, 2025
 */

import { BrowserMediaSession } from "shared/browsermediasession";
import { PlayerWrapper } from "shared/playerwrapper";
import { PlayQueue } from "shared/playqueue";
import { ProgressInfo } from "shared/progressinfo";
import { VolumeControl } from "shared/volumecontrol";
import * as _ from 'lodash';

declare function t(module : string, text : string) : string;

const AMPACHE_API_URL = 'apps/music/ampache/internal';

export class MusicWidget {

	#player: PlayerWrapper;
	#queue: PlayQueue;
	#volumeControl: VolumeControl;
	#progressInfo: ProgressInfo;
	#browserMediaSession: BrowserMediaSession;
	#selectContainer: JQuery<HTMLElement>;
	#modeSelect: JQuery<HTMLSelectElement>;
	#filterSelects: JQuery<HTMLSelectElement>[];
	#trackListContainer: JQuery<HTMLElement>;
	#trackList: JQuery<HTMLUListElement>;
	#progressAndOrder: JQuery<HTMLElement>;
	#currentSongLabel: JQuery<HTMLElement>;
	#controls: JQuery<HTMLElement>;
	#events: typeof OC.Backbone.Events;
	#debouncedPlayCurrent: () => void;

	constructor($container: JQuery<HTMLElement>, player: PlayerWrapper, queue: PlayQueue) {
		this.#player = player;
		this.#queue = queue;
		this.#volumeControl = new VolumeControl(player);
		this.#progressInfo = new ProgressInfo(player);
		this.#browserMediaSession = new BrowserMediaSession(player);
		this.#selectContainer = $('<div class="select-container" />').appendTo($container);
		this.#filterSelects = [];
		this.#trackListContainer = $('<div class="tracks-container" />').appendTo($container);
		this.#events = _.clone(OC.Backbone.Events);

		const modes = [
			{ id: 'album_artists',	name: t('music', 'Album artists'),	onSelect: () => this.#showAlbumArtists() },
			{ id: 'track_artists',	name: t('music', 'Track artists'),	onSelect: () => this.#showTrackArtists() },
			{ id: 'albums',			name: t('music', 'Albums'),			onSelect: () => this.#showAlbums() },
			{ id: 'folders',		name: t('music', 'Folders'),		onSelect: () => this.#showFolders() },
			{ id: 'genres',			name: t('music', 'Genres'),			onSelect: () => this.#showGenres() },
			{ id: 'all_tracks',		name: t('music', 'All tracks'),		onSelect: () => this.#showAllTracks() },
			{ id: 'playlists',		name: t('music', 'Playlists'),		onSelect: () => this.#showPlaylists() },
			{ id: 'radio',			name: t('music', 'Internet radio'),	onSelect: () => this.#showRadioStations() },
			{ id: 'podcasts',		name: t('music', 'Podcasts'),		onSelect: () => this.#showPodcasts() },
		];
		this.#modeSelect = createSelect(modes, t('music', 'Select mode'), (mode) => {
			// clear the previous selections first
			this.#filterSelects.forEach((select) => select.remove());
			this.#filterSelects = [];
			this.#trackList?.remove();
			this.#trackList = null;

			mode.onSelect();
		}).appendTo(this.#selectContainer);

		this.#progressAndOrder = createProgressAndOrder(
			this.#progressInfo,
			() => this.#setShuffle(!this.#queue.getShuffle()),
			() => this.#setRepeat(!this.#queue.getRepeat())
		).hide().appendTo($container);
		this.#currentSongLabel = $('<div class="current-song-label" />').hide().appendTo($container);
		this.#controls = createControls(
			() => this.#player.play(),
			() => this.#player.pause(),
			() => this.#onPrevButton(),
			() => this.#onNextButton(),
			() => this.#scrollToCurrentTrack(),
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
					// local song
					this.#player.fromUrl(track.url, track.stream_mime);
					this.#player.play();
				} else if ('filesize' in track) {
					// podcast
					this.#player.fromExtUrl(track.url, false);
					this.#player.play();
				} else {
					// radio stream needs resolving for HLS and playlist-type URLs
					$.get(OC.generateUrl('apps/music/api/radio/{id}/streamurl', {id: track.id}), {}, (resolvedStream) => {
						this.#player.fromExtUrl(resolvedStream.url, resolvedStream.hls);
						this.#player.play();
					});
				}
			}
		}, 300);

		this.#queue.subscribe('trackChanged', (track) => {
			this.#player.pause();
			this.#debouncedPlayCurrent();

			this.#trackList?.find('.current').removeClass('current');
			this.#trackList?.find(`[data-index='${this.#queue.getCurrentIndex()}']`).addClass('current');

			this.#controls.find('.albumart').css('background-image', '').addClass('icon-loading');

			const title = trackTitle(track);
			this.#currentSongLabel.html(title.asHtml).attr('title', title.asPlain).show();

			this.#progressAndOrder.show();
			this.#controls.show();

			this.#browserMediaSession.showInfo({
				title: track.name,
				album: track.album?.name ?? track.channel?.name,
				artist: track.artist?.name,
				cover: track.art
			});
		});

		this.#queue.subscribe('playlistEnded', () => {
			this.#progressAndOrder.hide();
			this.#currentSongLabel.hide();
			this.#controls.hide();
			this.#trackList.find('.current').removeClass('current');
		});

		this.#player.on('play', () => {
			this.#controls.find('.icon-play').hide();
			this.#controls.find('.icon-pause').show();
		});

		this.#player.on('pause', () => {
			this.#controls.find('.icon-play').show();
			this.#controls.find('.icon-pause').hide();
		});

		this.#player.on('stop', () => {
			this.#queue.clearPlaylist();
		});

		this.#player.on('end', () => this.#onNextButton());

		this.#browserMediaSession.registerControls({
			play: () => this.#player.play(),
			pause: () => this.#player.pause(),
			stop: () => this.#player.stop(),
			seekBackward: () => this.#player.seekBackward(),
			seekForward: () => this.#player.seekForward(),
			previousTrack: () => this.#onPrevButton(),
			nextTrack: () => this.#onNextButton()
		});
	}

	#loadBackgroundImage($albumArt: JQuery<HTMLElement>, url: string) {
		/* Load the image first using an out-of-DOM <img> element and then use the same image
		   as the background for the element. This is needed because loading the background-image
		   doesn't fire the onload event, making it impossible to timely remove the loading icon. */
		$('<img/>').attr('src', url).on('load', function() {
			$(this).remove(); // prevent memory leaks
			$albumArt.css('background-image', `url(${url})`).removeClass('icon-loading');
		});
	}

	#showAlbumArtists() : void {
		this.#ampacheLoadContent('list', { type: 'album_artist' }, (result: any) => {
			this.#addFilterSelect(result.list, t('music', 'Select artist'), (artist) => {
				if (this.#filterSelects.length > 1) {
					this.#filterSelects.pop().remove();
				}
				this.#trackList?.remove();

				this.#ampacheLoadContent('browse', { type: 'artist', filter: artist.id }, (result: any) => {
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
		this.#ampacheLoadContent('get_indexes', { type: 'song_artist' }, (result: any) => {
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
		this.#ampacheLoadContent('get_indexes', { type: 'album' }, (result: any) => {
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

	#showFolders() : void {
		this.#ampacheLoadContent('folders', {}, (result: any) => {
			this.#addFilterSelect(
				result.folder,
				t('music', 'Select folder'),
				(folder) => {
					this.#ampacheLoadAndShowTracks('folder_songs', { filter: folder.id }, null);
				},
				(folder) => folder.name || t('music', '(library root)')
			);
		});
	}

	#showGenres() : void {
		this.#ampacheLoadContent('list', { type: 'genre' }, (result: any) => {
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
		this.#ampacheLoadContent('list', { type: 'playlist' }, (result: any) => {
			this.#addFilterSelect(
				result.list,
				t('music', 'Select playlist'),
				(playlist) => {
					this.#ampacheLoadAndShowTracks('playlist_songs', { filter: playlist.id }, null);
				}
			);
		});
	}

	#showRadioStations() : void {
		this.#ampacheLoadAndShowTracks('live_streams', {}, null);
	}

	#showPodcasts() : void {
		this.#ampacheLoadContent('list', { type: 'podcast' }, (result: any) => {
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
		const filter = createSelect(options, placeholder, onChange, fmtTitle).appendTo(this.#selectContainer);
		this.#filterSelects.push(filter);
		this.#events.trigger('filterPopulated', filter);
	}

	#ampacheLoadAndShowTracks(action: string, args: JQuery.PlainObject, parentId: string|null) {
		this.#trackList?.remove();

		const listId = this.#getSelectedListId();
		this.#ampacheLoadContent(action, args, (result: any) => {
			this.#listTracks(listId, result.song ?? result.podcast_episode ?? result.live_stream, parentId);

			// highlight the current song if the currently playing list was re-entered
			if (this.#queue.getCurrentPlaylistId() == listId) {
				this.#trackList.find(`[data-index='${this.#queue.getCurrentIndex()}']`).addClass('current');
			}

			this.#events.trigger('tracksPopulated');
		});
	}

	#ampacheLoadContent(action: string, args: JQuery.PlainObject, callback: (result: any) => void) : void {
		this.#trackListContainer.addClass('icon-loading');
		ampacheApiAction(action, args, (result: any) => {
			callback(result);
			this.#trackListContainer.removeClass('icon-loading');
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

	#onNextButton() : void {
		this.#queue.jumpToNextTrack();
	}

	#onPrevButton() : void {
		// When not playing a radio stream, jump to the beginning of the current track if it has
		// already played more than 2 secs. Jump to the beginning also in case there is no
		// previous track to jump to.
		if (this.#playingRadio()) {
			this.#queue.jumpToPrevTrack()
		} else if (this.#player.playPosition() > 2000 || !this.#queue.jumpToPrevTrack()) {
			this.#player.seek(0);
		}
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

	#playingRadio() : boolean {
		return this.#queue.getCurrentPlaylistId()?.startsWith('radio');
	}

	#getSelectedListId() : string {
		let listId = this.#modeSelect.val().toString();

		this.#filterSelects.forEach((filter) => {
			listId += '/' + filter.val();
		});

		return listId;
	}

	#selectListWithId(listId : string, onReady : () => void) : void {
		const parts = listId.split('/');

		const mode = parts.shift();
		this.#modeSelect.val(mode).trigger('change');

		const handleSelection = () => {
			if (parts.length > 0) {
				const filterVal = parts.shift();
				this.#events.once('filterPopulated', (filter: JQuery<HTMLSelectElement>) => {
					filter.val(filterVal).trigger('change');
					handleSelection();
				});
			}
			else {
				this.#events.once('tracksPopulated', onReady);
			}
		}
		handleSelection();
	}

	#scrollToCurrentTrack() : void {
		const current = this.#trackList?.find('.current');
		if (current?.length) {
			current[0].scrollIntoView({
				behavior: "smooth"
			});
		}
		else {
			this.#selectListWithId(this.#queue.getCurrentPlaylistId(), () => this.#scrollToCurrentTrack());
		}
	}
}

function createSelect(items: any[], placeholder: string|null, onChange: (selectedItem: any) => void, fmtTitle: (item: any) => string = null) : JQuery<HTMLSelectElement> {
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

	$select.on('change', () => {
		const selItem = $select.find(":selected").data('item');
		onChange(selItem);
	});

	return $select;
}

function trackTitle(track: any, parentId: string|null = null) : {asHtml: string, asPlain: string} {
	const result = { asHtml: _.escape(track.name), asPlain: track.name };
	if ('artist' in track && track.artist.id != parentId) {
		result.asHtml += ` <span class="dimmed">(${_.escape(track.artist.name)})</span>`;
		result.asPlain += ` (${track.artist.name})`
	}
	return result;
}

function createTrackList(tracks: any[], parentId: string|null) : JQuery<HTMLUListElement> {
	const $ul = $('<ul/>') as JQuery<HTMLUListElement>;
	$(tracks).each(function(index: number) {
		const title = trackTitle(this, parentId);
		// Each item stores a `data` reference to its index. This is done using the jQuery .attr() instead of .data() because
		// the latter doesn't store the reference to the DOM itself, making finding the element by the attribute impossible.
		$('<li/>').html(title.asHtml).attr('title', title.asPlain).attr('data-index', index).appendTo($ul);
	});

	return $ul;
}

function createProgressAndOrder(progress : ProgressInfo, onShuffleBtn : () => void, onRepeatBtn : () => void) : JQuery<HTMLElement>
{
	const $container = $('<div class="progress-and-order"/>');
	$('<div class="control svg toggle icon-shuffle"/>').appendTo($container).on('click', onShuffleBtn);
	progress.addToContainer($container);
	$('<div class="control svg toggle icon-repeat"/>').appendTo($container).on('click', onRepeatBtn);
	return $container;
}

function createControls(
		onPlay : () => void,
		onPause : () => void,
		onPrev : () => void,
		onNext : () => void,
		onCoverClick : () => void,
		volumeControl : VolumeControl) : JQuery<HTMLElement> {
	const $container = $('<div class="player-controls"/>');
	$('<div class="albumart"/>').appendTo($container).on('click', onCoverClick);

	const $playbackControls = $('<div class="playback-controls" dir="ltr">').appendTo($container);
	$('<div class="playback control svg icon-skip-prev"/>').appendTo($playbackControls).on('click', onPrev);
	$('<div class="playback control svg icon-play"/>').appendTo($playbackControls).on('click', onPlay);
	$('<div class="playback control svg icon-pause"/>').appendTo($playbackControls).on('click', onPause).hide();
	$('<div class="playback control svg icon-skip-next"/>').appendTo($playbackControls).on('click', onNext);

	volumeControl.addToContainer($container);
	return $container;
}

function ampacheApiAction(action: string, args: JQuery.PlainObject, callback: JQuery.jqXHR.DoneCallback) : void {
	const url = OC.generateUrl(AMPACHE_API_URL);
	args['action'] = action;

	$.get(url, args, callback).fail((error) => {
		console.error(error)
	});
}
