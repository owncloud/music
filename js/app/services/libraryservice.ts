/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright 2017 - 2023 Pauli Järvinen
 *
 */

import * as ng from 'angular';
import * as _ from 'lodash';

export interface Artist {
	id : number;
	name : string;
	sortName : string;
	albums : Album[];
}

export interface Album {
	id : number;
	name : string;
	artist : Artist;
	tracks : Track[];
}

export interface Track {
	id : number;
	title : string;
	album : Album;
	artistId : number;
	artistName : string;
	artistSortName : string;
	type : string;
	folder : Folder;
	genre : Genre;
}

export interface PlaylistEntry {
	track : Track;
}

export interface Playlist {
	id : number;
	name : string;
	tracks : PlaylistEntry[];
}

export interface Folder {
	id : number;
	name : string;
	path : string;
	parent : Folder|null;
	subfolders : Folder[];
	tracks : PlaylistEntry[]
}

export interface Genre extends Playlist {}

export interface RadioStation extends PlaylistEntry {}

export interface PodcastChannel {
	id : number;
	title : string;
	hash : string;
	episodes : PodcastEpisode[];
}

export interface PodcastEpisode {
	id : number;
	title : string;
	channel : PodcastChannel;
	type : string;
}

export interface SearchResult<T> {
	result: T[];
	truncated : boolean;
}

const DIACRITIC_REG_EXP = /[\u0300-\u036f]/g;


export class LibraryService {
	#ignoredArticles : string[] = [];
	#artists : Artist[] = null;
	#albums : Album[] = null;
	#tracksIndex : { [id: number] : Track } = {};
	#tracksInAlbumOrder : PlaylistEntry[] = null;
	#tracksInAlphaOrder : PlaylistEntry[] = null;
	#tracksInGenreOrder : PlaylistEntry[] = null;
	#randomList : Playlist = null;
	#playlists : Playlist[] = null;
	#folders : Folder[] = null;
	#genres : Genre[] = null;
	#radioStations : RadioStation[] = null;
	#podcastChannels : PodcastChannel[] = null;

	/** 
	 * Sort array according to a specified text field. The field may be specified as a dot-separated path.
	 * Note:  The exact ordering is browser-dependant and usually affected by the browser language.
	 * Note2: The array is sorted in-place instead of returning a new array.
	 */
	#sortByTextField<T>(items : T[], field : string) : void {
		let getSortProperty = _.property(field);
		let locale = OCA.Music.Utils.getLocale();

		items.sort((a : T, b : T) => {
			let aProp : any = getSortProperty(a);
			let bProp : any = getSortProperty(b);

			if (aProp === null) {
				return -1;
			} else if (bProp === null) {
				return 1;
			} else {
				return aProp.localeCompare(bProp, locale);
			}
		});
	}

	/**
	 * Like sortByTextField but to be used with arrays of playlist entries where
	 * field is within outer field "track".
	 */
	#sortByPlaylistEntryTextField(items : PlaylistEntry[], field : string) : void {
		this.#sortByTextField(items, 'track.' + field);
	}

	#sortByNumericField<T>(items : T[], field : string) : void {
		let getSortProperty = _.property(field);
		items.sort((a : T, b : T) => {
			return Number(getSortProperty(a)) - Number(getSortProperty(b));
		});
	}

	#sortByYearAndName(aAlbums : Album[]) : Album[] {
		this.#sortByTextField(aAlbums, 'name');
		aAlbums = _.sortBy(aAlbums, 'year');
		return aAlbums;
	}

	#sortByDiskNumberAndTitle(tracks : Track[]) : Track[] {
		this.#sortByTextField(tracks, 'title');
		tracks = _.sortBy(tracks, 'number');
		tracks = _.sortBy(tracks, 'disk');
		return tracks;
	}

	#createArtistSortName(name : string) : string {
		for (let article of this.#ignoredArticles) {
			if (name.toLowerCase().startsWith(article.toLowerCase() + ' ')) {
				return name.substring(article.length + 1).trim();
			}
		}
		return name;
	}

	/**
	 * Sort the passed in collection alphabetically, and set up parent references
	 */
	#transformCollection(collection : any[]) : Artist[] {
		_.forEach(collection, (artist) => {
			artist.sortName = this.#createArtistSortName(artist.name);
			artist.albums = this.#sortByYearAndName(artist.albums);
			_.forEach(artist.albums, (album) => {
				album.artist = artist;
				album.tracks = this.#sortByDiskNumberAndTitle(album.tracks);
				_.forEach(album.tracks, (track) => {
					track.artistSortName = this.#createArtistSortName(track.artistName);
					track.album = album;
				});
			});
		});
		this.#sortByTextField(collection, 'sortName');
		return collection;
	}

	#moveArrayElement(array : any[], from : number, to : number) : void {
		array.splice(to, 0, array.splice(from, 1)[0]);
	}

	#playlistEntry(track : Track) : PlaylistEntry {
		return (track !== null) ? { track: track } : null;
	}

	#playlistEntryFromId(trackId : number) : PlaylistEntry {
		return this.#playlistEntry(this.#tracksIndex[trackId] ?? null);
	}

	#wrapRadioStation(station : any) : RadioStation {
		station.type = 'radio';
		return this.#playlistEntry(station);
	}

	#wrapPlaylist(playlist : any) : Playlist {
		let wrapped = $.extend({}, playlist); // clone the playlist
		wrapped.tracks = _(playlist.trackIds).map((id) => this.#playlistEntryFromId(id)).reject(_.isNull).value(); // null-values are possible during scanning
		delete wrapped.trackIds;
		return wrapped;
	}

	// Return values is a kind of "proto folder" as it still has the `parent` field as ID instead of a reference
	#wrapFolder(folder : any) : any {
		let wrapped = <any>this.#wrapPlaylist(folder);
		wrapped.path = null; // set up later
		wrapped.expanded = (folder.parent === null); // the root folder is expanded by default
		return wrapped;
	}

	#setUpFolderPath(folder : Folder) : void {
		// nothing to do if the path has been already set up
		if (folder.path === null) {
			if (folder.parent === null) {
				folder.path = '';
			} else {
				this.#setUpFolderPath(folder.parent);
				folder.path = folder.parent.path + '/' + folder.name;
			}
		}
	}

	#getFolderTracksRecursively(folder : Folder) : PlaylistEntry[] {
		let subFolderTracks = _(folder.subfolders).map((folder) => this.#getFolderTracksRecursively(folder)).flatten().value();
		return [...subFolderTracks, ...folder.tracks];
	}

	#initPodcastChannel(channel : PodcastChannel) : void {
		_.forEach(channel.episodes, (episode) => {
			episode.channel = channel;
			episode.type = 'podcast';
		});
	}

	#createTrackContainers() : void {
		// album order "playlist"
		let tracks = _.flatten(_.map(this.#albums, 'tracks'));
		this.#tracksInAlbumOrder = _.map(tracks, this.#playlistEntry);

		// alphabetic order "playlist"
		this.#sortByTextField(tracks, 'title');
		this.#sortByTextField(tracks, 'artistSortName');
		this.#tracksInAlphaOrder = _.map(tracks, this.#playlistEntry);

		// tracks index
		_.forEach(tracks, (track) => {
			track.type = 'song';
			this.#tracksIndex[track.id] = track;
		});
	}

	/** Convert string to "folded" form suitable for fuzzy matching */
	#foldString(str : string) : string {
		if (str) {
			str = str.toLocaleLowerCase();

			// Skip the normalization if the browser is ancient and doesn't support it
			if ('normalize' in String.prototype) {
				str = str.normalize('NFD').replace(DIACRITIC_REG_EXP, '');
			}
		}

		return str;
	}

	/** Split search query to array by whitespace.
	 *  As an exception, quoted substrings are kept as one entity. The quotation marks are removed.
	 */
	#splitSearchQuery(query : string) : string[] {
		const regExQuoted = /".*?"/g;

		// Get any quoted substring. Also the quotation marks get extracted, and they are sliced off separately.
		let quoted = query.match(regExQuoted) || <string[]>[];
		quoted = _.map(quoted, (str) => str.slice(1, -1));

		// remove the quoted substrings and stray quotation marks, and extact the rest of the parts
		query = query.replace(regExQuoted, ' ');
		query = query.replace('"', ' ');
		let unquoted = query.match(/\S+/g) || [];

		return quoted.concat(unquoted);
	}

	#objectFieldsContainAll(object : any, getFieldValueFuncs : CallableFunction[], subStrings : string[]) : boolean {
		return _.every(subStrings, (subStr) => {
			return _.some(getFieldValueFuncs, (getter) => {
				let value = getter(object);
				return (value !== null && this.#foldString(value).indexOf(subStr) !== -1);
			});
		});
	}

	#search<T>(container : T[]|{[id: number] : T}, fields : string[]|string, query : string, maxResults : number) : SearchResult<T> {
		query = this.#foldString(query);
		// In case the query contains many words separated with whitespace, each part
		// has to be found but the whitespace is disregarded.
		let queryParts = this.#splitSearchQuery(query);

		// @a fields may be an array or an idividual string
		if (!Array.isArray(fields)) {
			fields = [fields];
		}

		// Field may be given as a '.'-separated path;
		// convert the fields to corresponding getter functions.
		let fieldGetterFuncs = _.map(fields, _.property);

		let matchCount = 0;
		let maxLimitReached = false;
		let matches = _.filter(container, (item) => {
			let matched = !maxLimitReached && this.#objectFieldsContainAll(item, fieldGetterFuncs, queryParts);
			if (matched && matchCount++ == maxResults) {
				maxLimitReached = true;
				matched = false;
			}
			return matched;
		});

		return {
			result: matches,
			truncated: maxLimitReached
		};
	}

	// PUBLIC INTERFACE
	setIgnoredArticles(articles : string[]) : void {
		this.#ignoredArticles = articles;
		if (this.#artists) {
			// reorder the existing library if there is one
			_.forEach(this.#artists, (artist) => {
				artist.sortName = this.#createArtistSortName(artist.name);
			});
			this.#sortByTextField(this.#artists, 'sortName');

			_.forEach(this.#tracksInAlphaOrder, (entry) => {
				entry.track.artistSortName = this.#createArtistSortName(entry.track.artistName);
			});
			this.#sortByPlaylistEntryTextField(this.#tracksInAlphaOrder, 'artistSortName');

			_.forEach(this.#genres, (genre) => {
				this.#sortByPlaylistEntryTextField(genre.tracks, 'artistSortName');
			});
			this.#tracksInGenreOrder = _(this.#genres).map('tracks').flatten().value();
		}
	}
	setCollection(collection : any[]) : void {
		this.#artists = this.#transformCollection(collection);
		this.#albums = _(this.#artists).map('albums').flatten().value();
		this.#createTrackContainers();
	}
	setPlaylists(lists : any[]) : void {
		this.#playlists = _.map(lists, (list) => this.#wrapPlaylist(list));
	}
	setRandomList(list : any) : void {
		if (!list) {
			this.#randomList = null;
		} else {
			this.#randomList = this.#wrapPlaylist(list);
		}
	}
	setFolders(folderData : any[]|null) : void {
		if (!folderData) {
			this.#folders = null;
		} else {
			let protoFolders = _.map(folderData, (folder) => this.#wrapFolder(folder));
			this.#sortByTextField(protoFolders, 'name');
			// the tracks within each folder are sorted by the file name by the back-end

			// create temporary look-up-table for the folders to speed up setting up the parent references
			let foldersLut : {[id: number] : any} = {};
			_.forEach(protoFolders, (folder) => {
				foldersLut[folder.id] = folder;
			});

			_.forEach(protoFolders, (folder) => {
				// substitute parent id with a reference to the parent folder
				folder.parent = foldersLut[folder.parent] ?? null;
				// set parent folder references for the contained tracks
				_.forEach(folder.tracks, (trackEntry) => {
					trackEntry.track.folder = folder;
				});
				// init subfolder array
				folder.subfolders = [];
			});

			_.forEach(protoFolders, (folder) => {
				// compile the full path for each folder by following the parent references
				this.#setUpFolderPath(folder);
				// set the subfolder references
				if (folder.parent !== null) {
					folder.parent.subfolders.push(folder);
				}
			});

			this.#folders = protoFolders;
		}
	}
	setGenres(genreData : any[]|null) : void {
		if (!genreData) {
			this.#genres = null;
			this.#tracksInGenreOrder = null;
		} else {
			this.#genres = _.map(genreData, (genre) => this.#wrapPlaylist(genre));
			this.#sortByTextField(this.#genres, 'name');
			// if the first item after sorting is the unknown genre (empty string),
			// then move it to the end of the list
			if (this.#genres.length > 0 && this.#genres[0].name === '') {
				this.#genres.push(this.#genres.shift());
			}

			_.forEach(this.#genres, (genre) => {
				this.#sortByPlaylistEntryTextField(genre.tracks, 'title');
				this.#sortByPlaylistEntryTextField(genre.tracks, 'artistSortName');

				_.forEach(genre.tracks, (trackEntry) => {
					trackEntry.track.genre = genre;
				});
			});

			this.#tracksInGenreOrder = _(this.#genres).map('tracks').flatten().value();
		}
	}
	setRadioStations(radioStationsData : any[]) : void {
		this.#radioStations = _.map(radioStationsData, (station) => this.#wrapRadioStation(station));
		this.sortRadioStations();
	}
	sortRadioStations() : void {
		this.#sortByPlaylistEntryTextField(this.#radioStations, 'stream_url');
		this.#sortByPlaylistEntryTextField(this.#radioStations, 'name');
	}
	addRadioStation(radioStationData : any) : void {
		this.addRadioStations([radioStationData]);
	}
	addRadioStations(radioStationsData : any) : void {
		let newStations = _.map(radioStationsData, (station) => this.#wrapRadioStation(station))
		this.#radioStations = this.#radioStations.concat(newStations);
		this.sortRadioStations();
	}
	removeRadioStation(stationId : number) : number {
		let idx = _.findIndex(this.#radioStations, entry => entry.track.id == stationId);
		this.#radioStations.splice(idx, 1);
		return idx;
	}
	setPodcasts(podcastsData : any[]) : void {
		this.#sortByTextField(podcastsData, 'title');
		// set the parent references for each episode 
		_.forEach(podcastsData, this.#initPodcastChannel);
		this.#podcastChannels = podcastsData;
	}
	addPodcastChannel(channel : PodcastChannel) : void {
		this.#initPodcastChannel(channel);
		this.#podcastChannels.push(channel);
		this.#sortByTextField(this.#podcastChannels, 'title');
	}
	replacePodcastChannel(channel : PodcastChannel) {
		this.#initPodcastChannel(channel);
		let idx = _.findIndex(this.#podcastChannels, { id: channel.id });
		this.#podcastChannels[idx] = channel;
	}
	removePodcastChannel(channel : PodcastChannel) {
		let idx = _.findIndex(this.#podcastChannels, { id: channel.id });
		this.#podcastChannels.splice(idx, 1);
	}
	addPlaylist(playlist : any) : void {
		this.#playlists.push(this.#wrapPlaylist(playlist));
	}
	removePlaylist(playlist : any) : void {
		this.#playlists.splice(this.#playlists.indexOf(playlist), 1);
	}
	replacePlaylist(playlist : any) : void {
		let idx = _.findIndex(this.#playlists, { id: playlist.id });
		this.#playlists[idx] = this.#wrapPlaylist(playlist);
	}
	addToPlaylist(playlistId : number, trackId : number) : void {
		let playlist = this.getPlaylist(playlistId);
		playlist.tracks.push(this.#playlistEntryFromId(trackId));
	}
	removeFromPlaylist(playlistId : number, indexToRemove : number) : void {
		let playlist = this.getPlaylist(playlistId);
		playlist.tracks.splice(indexToRemove, 1);
	}
	reorderPlaylist(playlistId : number, srcIndex : number, dstIndex : number) : void {
		let playlist = this.getPlaylist(playlistId);
		this.#moveArrayElement(playlist.tracks, srcIndex, dstIndex);
	}
	sortPlaylist(playlistId : number, byProperty : string) : void {
		let playlist = this.getPlaylist(playlistId);
		switch (byProperty) {
		case 'track':
			this.#sortByTextField(playlist.tracks, 'track.title');
			break;
		case 'album':
			this.#sortByTextField(playlist.tracks, 'track.title');
			this.#sortByNumericField(playlist.tracks, 'track.number');
			this.#sortByNumericField(playlist.tracks, 'track.disk');
			this.#sortByTextField(playlist.tracks, 'track.album.name');
			break;
		case 'artist':
			this.#sortByTextField(playlist.tracks, 'track.title');
			this.#sortByTextField(playlist.tracks, 'track.artistSortName');
			break;
		default:
			console.error('Unexpected playlist sort property ' + byProperty);
			break;
		}
	}
	removeDuplicatesFromPlaylist(playlistId : number) : PlaylistEntry[] {
		let playlist = this.getPlaylist(playlistId);
		let foundIds : {[id: number] : boolean} = {};
		let indicesToRemove = [];

		// find the indices containing duplicates
		for (let i = 0; i < playlist.tracks.length; ++i) {
			let id = playlist.tracks[i].track.id;
			if (id in foundIds) {
				indicesToRemove.push(i);
			} else {
				foundIds[id] = true;
			}
		}

		// remove (and return) the duplicates
		return _.pullAt(playlist.tracks, indicesToRemove);
	}
	getArtist(id : number) : Artist {
		let artist = _.find(this.#artists, { id: Number(id) });
		if (!artist) {
			// there's no such album artist, try to find a matching track artist (who has no albums)
			let track = _.find(this.#tracksIndex, { artistId: Number(id)} );
			if (track) {
				artist = {
						id: track.artistId,
						name: track.artistName,
						sortName: track.artistSortName,
						albums: []
				};
			}
		}
		return artist;
	}
	getAllArtists() : Artist[] {
		return this.#artists;
	}
	getAlbum(id : number) : Album {
		return _.find(this.#albums, { id: Number(id) });
	}
	getAlbumCount() : number {
		return this.#albums?.length ?? 0;
	}
	getTrack(id : number) : Track {
		return this.#tracksIndex[id];
	}
	getTracksInAlphaOrder() : PlaylistEntry[] {
		return this.#tracksInAlphaOrder;
	}
	getTracksInAlbumOrder() : PlaylistEntry[] {
		return this.#tracksInAlbumOrder;
	}
	getTracksInFolderOrder(treeMode : boolean) : PlaylistEntry[] {
		return treeMode
			? this.#getFolderTracksRecursively(this.getRootFolder())
			: _(this.#folders).map('tracks').flatten().value();
	}
	getTracksInGenreOrder() : PlaylistEntry[] {
		return this.#tracksInGenreOrder;
	}
	getTrackCount() : number {
		return this.#tracksInAlphaOrder?.length ?? 0;
	}
	getRandomTrackCount() : number {
		return this.#randomList?.tracks?.length ?? 0;
	}
	getRandomList() : Playlist {
		return this.#randomList;
	}
	getPlaylist(id : number) : Playlist {
		return _.find(this.#playlists, { id: Number(id) });
	}
	getAllPlaylists() : Playlist[] {
		return this.#playlists;
	}
	getFolder(id : number) : Folder {
		return _.find(this.#folders, { id: Number(id) });
	}
	getFolderTracks(folder : Folder, recursively : boolean) : PlaylistEntry[] {
		return recursively ? this.#getFolderTracksRecursively(folder) : folder.tracks;
	}
	getAllFoldersWithTracks() : Folder[] {
		return _.filter(this.#folders, (folder) => folder.tracks.length > 0);
	}
	getRootFolder() : Folder {
		return _.find(this.#folders, { parent: null });
	}
	getGenre(id : number) : Genre {
		return _.find(this.#genres, { id: Number(id) });
	}
	getAllGenres() : Genre[] {
		return this.#genres;
	}
	getRadioStation(id : number) : Track {
		return _.find(this.#radioStations, ['track.id', Number(id)])?.track;
	}
	getAllRadioStations() : RadioStation[] {
		return this.#radioStations;
	}
	getPodcastEpisode(id : number) : PodcastEpisode {
		return _(this.#podcastChannels).map('episodes').flatten().find({ id: Number(id) });
	}
	getAllPodcastEpisodes() : PodcastEpisode[] {
		return _(this.#podcastChannels).map('episodes').flatten().value();
	}
	getPodcastChannel(id : number) : PodcastChannel {
		return _.find(this.#podcastChannels, { id: Number(id) });
	}
	getAllPodcastChannels() : PodcastChannel[] {
		return this.#podcastChannels;
	}
	getPodcastChannelsCount() : number {
		return this.#podcastChannels?.length ?? 0;
	}
	findTracksByArtist(artistId : number) : {[id: number] : Track} {
		return _.filter(this.#tracksIndex, {artistId: Number(artistId)});
	}
	collectionLoaded() : boolean {
		return this.#artists !== null;
	}
	playlistsLoaded() : boolean {
		return this.#playlists !== null;
	}
	foldersLoaded() : boolean {
		return this.#folders !== null;
	}
	genresLoaded() : boolean {
		return this.#genres !== null;
	}
	radioStationsLoaded() : boolean {
		return this.#radioStations !== null;
	}
	podcastsLoaded() : boolean {
		return this.#podcastChannels !== null;
	}
	searchTracks(query : string, maxResults = Infinity) : SearchResult<Track> {
		return this.#search(this.#tracksIndex, ['title', 'artistName'], query, maxResults);
	}
	searchTracksInAlbums(query : string, maxResults = Infinity) : SearchResult<Track> {
		return this.#search(
				this.#tracksIndex,
				['title', 'artistName', 'album.name', 'album.year', 'album.artist.name'],
				query,
				maxResults);
	}
	searchTracksInFolders(query : string, maxResults = Infinity) : SearchResult<Track> {
		return this.#search(
				this.#tracksIndex,
				['title', 'artistName', 'folder.path'],
				query,
				maxResults);
	}
	searchTracksInGenres(query : string, maxResults = Infinity) : SearchResult<Track> {
		return this.#search(
				this.#tracksIndex,
				['title', 'artistName', 'genre.name'],
				query,
				maxResults);
	}
	searchTracksInPlaylist(playlistId : number, query : string, maxResults = Infinity) : SearchResult<Track> {
		let entries = this.getPlaylist(playlistId)?.tracks || [];
		let tracks = _.map(entries, 'track');
		tracks = _.uniq(tracks);
		return this.#search(tracks, ['title', 'artistName'], query, maxResults);
	}
	searchRadioStations(query : string, maxResults = Infinity) : SearchResult<Track> {
		let stations = _.map(this.#radioStations, 'track');
		return this.#search(stations, ['name', 'stream_url'], query, maxResults);
	}
	searchPodcasts(query : string, maxResults = Infinity) : SearchResult<PodcastEpisode> {
		let episodes = _(this.#podcastChannels).map('episodes').flatten().value();
		return this.#search(episodes, ['title', 'channel.title'], query, maxResults);
	}
}

ng.module('Music').service('libraryService', [LibraryService]);
