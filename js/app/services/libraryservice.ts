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

interface Artist {
	id : number;
	name : string;
	sortName : string;
	albums : Album[];
}

interface Album {
	id : number;
	name : string;
	artist : Artist;
	tracks : Track[];
}

interface Track {
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

interface PlaylistEntry {
	track : Track;
}

interface Playlist {
	id : number;
	name : string;
	tracks : PlaylistEntry[];
}

interface Folder {
	id : number;
	name : string;
	path : string;
	parent : Folder|null;
	subfolders : Folder[];
	tracks : PlaylistEntry[]
}

interface Genre extends Playlist {}

interface RadioStation extends PlaylistEntry {}

interface PodcastChannel {
	id : number;
	title : string;
	episodes : PodcastEpisode[];
}

interface PodcastEpisode {
	id : number;
	title : string;
	channel : PodcastChannel;
	type : string;
}

interface SearchResult<T> {
	result: T[];
	truncated : boolean;
}

ng.module('Music').service('libraryService', [function() {

	let ignoredArticles : string[] = [];
	let artists : Artist[] = null;
	let albums : Album[] = null;
	let tracksIndex : { [id: number] : Track } = {};
	let tracksInAlbumOrder : PlaylistEntry[] = null;
	let tracksInAlphaOrder : PlaylistEntry[] = null;
	let tracksInGenreOrder : PlaylistEntry[] = null;
	let playlists : Playlist[] = null;
	let folders : Folder[] = null;
	let genres : Genre[] = null;
	let radioStations : RadioStation[] = null;
	let podcastChannels : PodcastChannel[] = null;

	/** 
	 * Sort array according to a specified text field. The field may be specified as a dot-separated path.
	 * Note:  The exact ordering is browser-dependant and usually affected by the browser language.
	 * Note2: The array is sorted in-place instead of returning a new array.
	 */
	function sortByTextField<T>(items : T[], field : string) : void {
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
	function sortByPlaylistEntryTextField(items : PlaylistEntry[], field : string) : void {
		sortByTextField(items, 'track.' + field);
	}

	function sortByNumericField<T>(items : T[], field : string) : void {
		let getSortProperty = _.property(field);
		items.sort((a : T, b : T) => {
			return Number(getSortProperty(a)) - Number(getSortProperty(b));
		});
	}

	function sortByYearAndName(aAlbums : Album[]) : Album[] {
		sortByTextField(aAlbums, 'name');
		aAlbums = _.sortBy(aAlbums, 'year');
		return aAlbums;
	}

	function sortByDiskNumberAndTitle(tracks : Track[]) : Track[] {
		sortByTextField(tracks, 'title');
		tracks = _.sortBy(tracks, 'number');
		tracks = _.sortBy(tracks, 'disk');
		return tracks;
	}

	function createArtistSortName(name : string) : string {
		for (let article of ignoredArticles) {
			if (name.toLowerCase().startsWith(article.toLowerCase() + ' ')) {
				return name.substring(article.length + 1).trim();
			}
		}
		return name;
	}

	/**
	 * Sort the passed in collection alphabetically, and set up parent references
	 */
	function transformCollection(collection : any[]) : Artist[] {
		_.forEach(collection, function(artist) {
			artist.sortName = createArtistSortName(artist.name);
			artist.albums = sortByYearAndName(artist.albums);
			_.forEach(artist.albums, function(album) {
				album.artist = artist;
				album.tracks = sortByDiskNumberAndTitle(album.tracks);
				_.forEach(album.tracks, function(track) {
					track.artistSortName = createArtistSortName(track.artistName);
					track.album = album;
				});
			});
		});
		sortByTextField(collection, 'sortName');
		return collection;
	}

	function moveArrayElement(array : any[], from : number, to : number) : void {
		array.splice(to, 0, array.splice(from, 1)[0]);
	}

	function playlistEntry(track : Track) : PlaylistEntry {
		return (track !== null) ? { track: track } : null;
	}

	function playlistEntryFromId(trackId : number) : PlaylistEntry {
		return playlistEntry(tracksIndex[trackId] ?? null);
	}

	function wrapRadioStation(station : any) : RadioStation {
		station.type = 'radio';
		return playlistEntry(station);
	}

	function wrapPlaylist(playlist : any) : Playlist {
		let wrapped = $.extend({}, playlist); // clone the playlist
		wrapped.tracks = _(playlist.trackIds).map(playlistEntryFromId).reject(_.isNull).value(); // null-values are possible during scanning
		delete wrapped.trackIds;
		return wrapped;
	}

	// Return values is a kind of "proto folder" as it still has the `parent` field as ID instead of a reference
	function wrapFolder(folder : any) : any {
		let wrapped = <any>wrapPlaylist(folder);
		wrapped.path = null; // set up later
		wrapped.expanded = (folder.parent === null); // the root folder is expanded by default
		return wrapped;
	}

	function setUpFolderPath(folder : Folder) : void {
		// nothing to do if the path has been already set up
		if (folder.path === null) {
			if (folder.parent === null) {
				folder.path = '';
			} else {
				setUpFolderPath(folder.parent);
				folder.path = folder.parent.path + '/' + folder.name;
			}
		}
	}

	function getFolderTracksRecursively(folder : Folder) : PlaylistEntry[] {
		let subFolderTracks = _(folder.subfolders).map(getFolderTracksRecursively).flatten().value();
		return [...subFolderTracks, ...folder.tracks];
	}

	function initPodcastChannel(channel : PodcastChannel) : void {
		_.forEach(channel.episodes, function(episode) {
			episode.channel = channel;
			episode.type = 'podcast';
		});
	}

	function createTrackContainers() : void {
		// album order "playlist"
		let tracks = _.flatten(_.map(albums, 'tracks'));
		tracksInAlbumOrder = _.map(tracks, playlistEntry);

		// alphabetic order "playlist"
		sortByTextField(tracks, 'title');
		sortByTextField(tracks, 'artistSortName');
		tracksInAlphaOrder = _.map(tracks, playlistEntry);

		// tracks index
		_.forEach(tracks, function(track) {
			track.type = 'song';
			tracksIndex[track.id] = track;
		});
	}

	const diacriticRegExp = /[\u0300-\u036f]/g;
	/** Convert string to "folded" form suitable for fuzzy matching */
	function foldString(str : string) : string {
		if (str) {
			str = str.toLocaleLowerCase();

			// Skip the normalization if the browser is ancient and doesn't support it
			if ('normalize' in String.prototype) {
				str = str.normalize('NFD').replace(diacriticRegExp, '');
			}
		}

		return str;
	}

	/** Split search query to array by whitespace.
	 *  As an exception, quoted substrings are kept as one entity. The quotation marks are removed.
	 */
	function splitSearchQuery(query : string) : string[] {
		const regExQuoted = /".*?"/g;

		// Get any quoted substring. Also the quotation marks get extracted, and they are sliced off separately.
		let quoted = query.match(regExQuoted) || <string[]>[];
		quoted = _.map(quoted, function(str) {
			return str.slice(1, -1);
		});

		// remove the quoted substrings and stray quotation marks, and extact the rest of the parts
		query = query.replace(regExQuoted, ' ');
		query = query.replace('"', ' ');
		let unquoted = query.match(/\S+/g) || [];

		return quoted.concat(unquoted);
	}

	function objectFieldsContainAll(object : any, getFieldValueFuncs : CallableFunction[], subStrings : string[]) : boolean {
		return _.every(subStrings, function(subStr) {
			return _.some(getFieldValueFuncs, function(getter) {
				let value = getter(object);
				return (value !== null && foldString(value).indexOf(subStr) !== -1);
			});
		});
	}

	function search<T>(container : T[]|{[id: number] : T}, fields : string[]|string, query : string, maxResults : number) : SearchResult<T> {
		query = foldString(query);
		// In case the query contains many words separated with whitespace, each part
		// has to be found but the whitespace is disregarded.
		let queryParts = splitSearchQuery(query);

		// @a fields may be an array or an idividual string
		if (!Array.isArray(fields)) {
			fields = [fields];
		}

		// Field may be given as a '.'-separated path;
		// convert the fields to corresponding getter functions.
		let fieldGetterFuncs = _.map(fields, _.property);

		let matchCount = 0;
		let maxLimitReached = false;
		let matches = _.filter(container, function(item) {
			let matched = !maxLimitReached && objectFieldsContainAll(item, fieldGetterFuncs, queryParts);
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

	return {
		setIgnoredArticles(articles : string[]) : void {
			ignoredArticles = articles;
			if (artists) {
				// reorder the existing library if there is one
				_.forEach(artists, function(artist) {
					artist.sortName = createArtistSortName(artist.name);
				});
				sortByTextField(artists, 'sortName');

				_.forEach(tracksInAlphaOrder, function(entry) {
					entry.track.artistSortName = createArtistSortName(entry.track.artistName);
				});
				sortByPlaylistEntryTextField(tracksInAlphaOrder, 'artistSortName');

				_.forEach(genres, function(genre) {
					sortByPlaylistEntryTextField(genre.tracks, 'artistSortName');
				});
				tracksInGenreOrder = _(genres).map('tracks').flatten().value();
			}
		},
		setCollection(collection : any[]) : void {
			artists = transformCollection(collection);
			albums = _(artists).map('albums').flatten().value();
			createTrackContainers();
		},
		setPlaylists(lists : any[]) : void {
			playlists = _.map(lists, wrapPlaylist);
		},
		setFolders(folderData : any[]|null) : void {
			if (!folderData) {
				folders = null;
			} else {
				let protoFolders = _.map(folderData, wrapFolder);
				sortByTextField(protoFolders, 'name');
				// the tracks within each folder are sorted by the file name by the back-end

				// create temporary look-up-table for the folders to speed up setting up the parent references
				let foldersLut : {[id: number] : any} = {};
				_.forEach(protoFolders, function(folder) {
					foldersLut[folder.id] = folder;
				});

				_.forEach(protoFolders, function(folder) {
					// substitute parent id with a reference to the parent folder
					folder.parent = foldersLut[folder.parent] ?? null;
					// set parent folder references for the contained tracks
					_.forEach(folder.tracks, function(trackEntry) {
						trackEntry.track.folder = folder;
					});
					// init subfolder array
					folder.subfolders = [];
				});

				_.forEach(protoFolders, function(folder) {
					// compile the full path for each folder by following the parent references
					setUpFolderPath(folder);
					// set the subfolder references
					if (folder.parent !== null) {
						folder.parent.subfolders.push(folder);
					}
				});

				folders = protoFolders;
			}
		},
		setGenres(genreData : any[]|null) : void {
			if (!genreData) {
				genres = null;
				tracksInGenreOrder = null;
			} else {
				genres = _.map(genreData, wrapPlaylist);
				sortByTextField(genres, 'name');
				// if the first item after sorting is the unknown genre (empty string),
				// then move it to the end of the list
				if (genres.length > 0 && genres[0].name === '') {
					genres.push(genres.shift());
				}

				_.forEach(genres, function(genre) {
					sortByPlaylistEntryTextField(genre.tracks, 'title');
					sortByPlaylistEntryTextField(genre.tracks, 'artistSortName');

					_.forEach(genre.tracks, function(trackEntry) {
						trackEntry.track.genre = genre;
					});
				});

				tracksInGenreOrder = _(genres).map('tracks').flatten().value();
			}
		},
		setRadioStations(radioStationsData : any[]) : void {
			radioStations = _.map(radioStationsData, wrapRadioStation);
			this.sortRadioStations();
		},
		sortRadioStations() : void {
			sortByPlaylistEntryTextField(radioStations, 'stream_url');
			sortByPlaylistEntryTextField(radioStations, 'name');
		},
		addRadioStation(radioStationData : any) : void {
			this.addRadioStations([radioStationData]);
		},
		addRadioStations(radioStationsData : any) : void {
			radioStations = radioStations.concat(_.map(radioStationsData, wrapRadioStation));
			sortByPlaylistEntryTextField(radioStations, 'stream_url');
			sortByPlaylistEntryTextField(radioStations, 'name');
		},
		removeRadioStation(stationId : number) : number {
			let idx = _.findIndex(radioStations, entry => entry.track.id == stationId);
			radioStations.splice(idx, 1);
			return idx;
		},
		setPodcasts(podcastsData : any[]) : void {
			sortByTextField(podcastsData, 'title');
			// set the parent references for each episode 
			_.forEach(podcastsData, initPodcastChannel);
			podcastChannels = podcastsData;
		},
		addPodcastChannel(channel : PodcastChannel) : void {
			initPodcastChannel(channel);
			podcastChannels.push(channel);
			sortByTextField(podcastChannels, 'title');
		},
		replacePodcastChannel(channel : PodcastChannel) {
			initPodcastChannel(channel);
			let idx = _.findIndex(podcastChannels, { id: channel.id });
			podcastChannels[idx] = channel;
		},
		removePodcastChannel(channel : PodcastChannel) {
			let idx = _.findIndex(podcastChannels, { id: channel.id });
			podcastChannels.splice(idx, 1);
		},
		addPlaylist(playlist : any) : void {
			playlists.push(wrapPlaylist(playlist));
		},
		removePlaylist(playlist : any) : void {
			playlists.splice(playlists.indexOf(playlist), 1);
		},
		replacePlaylist(playlist : any) : void {
			let idx = _.findIndex(playlists, { id: playlist.id });
			playlists[idx] = wrapPlaylist(playlist);
		},
		addToPlaylist(playlistId : number, trackId : number) : void {
			let playlist = this.getPlaylist(playlistId);
			playlist.tracks.push(playlistEntryFromId(trackId));
		},
		removeFromPlaylist(playlistId : number, indexToRemove : number) : void {
			let playlist = this.getPlaylist(playlistId);
			playlist.tracks.splice(indexToRemove, 1);
		},
		reorderPlaylist(playlistId : number, srcIndex : number, dstIndex : number) : void {
			let playlist = this.getPlaylist(playlistId);
			moveArrayElement(playlist.tracks, srcIndex, dstIndex);
		},
		sortPlaylist(playlistId : number, byProperty : string) : void {
			let playlist = this.getPlaylist(playlistId);
			switch (byProperty) {
			case 'track':
				sortByTextField(playlist.tracks, 'track.title');
				break;
			case 'album':
				sortByTextField(playlist.tracks, 'track.title');
				sortByNumericField(playlist.tracks, 'track.number');
				sortByNumericField(playlist.tracks, 'track.disk');
				sortByTextField(playlist.tracks, 'track.album.name');
				break;
			case 'artist':
				sortByTextField(playlist.tracks, 'track.title');
				sortByTextField(playlist.tracks, 'track.artistSortName');
				break;
			default:
				console.error('Unexpected playlist sort property ' + byProperty);
				break;
			}
		},
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
		},
		getArtist(id : number) : Artist {
			let artist = _.find(artists, { id: Number(id) });
			if (!artist) {
				// there's no such album artist, try to find a matching track artist (who has no albums)
				let track = _.find(tracksIndex, { artistId: Number(id)} );
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
		},
		getAllArtists() : Artist[] {
			return artists;
		},
		getAlbum(id : number) : Album {
			return _.find(albums, { id: Number(id) });
		},
		getAlbumCount() : number {
			return albums?.length ?? 0;
		},
		getTrack(id : number) : Track {
			return tracksIndex[id];
		},
		getTracksInAlphaOrder() : PlaylistEntry[] {
			return tracksInAlphaOrder;
		},
		getTracksInAlbumOrder() : PlaylistEntry[] {
			return tracksInAlbumOrder;
		},
		getTracksInFolderOrder(treeMode : boolean) : PlaylistEntry[] {
			return treeMode
				? getFolderTracksRecursively(this.getRootFolder())
				: _(folders).map('tracks').flatten().value();
		},
		getTracksInGenreOrder() : PlaylistEntry[] {
			return tracksInGenreOrder;
		},
		getTrackCount() : number {
			return tracksInAlphaOrder?.length ?? 0;
		},
		getPlaylist(id : number) : Playlist {
			return _.find(playlists, { id: Number(id) });
		},
		getAllPlaylists() : Playlist[] {
			return playlists;
		},
		getFolder(id : number) : Folder {
			return _.find(folders, { id: Number(id) });
		},
		getFolderTracks(folder : Folder, recursively : boolean) : PlaylistEntry[] {
			return recursively ? getFolderTracksRecursively(folder) : folder.tracks;
		},
		getAllFoldersWithTracks() : Folder[] {
			return _.filter(folders, (folder) => folder.tracks.length > 0);
		},
		getRootFolder() : Folder {
			return _.find(folders, { parent: null });
		},
		getGenre(id : number) : Genre {
			return _.find(genres, { id: Number(id) });
		},
		getAllGenres() : Genre[] {
			return genres;
		},
		getRadioStation(id : number) : Track {
			return _.find(radioStations, ['track.id', Number(id)])?.track;
		},
		getAllRadioStations() : RadioStation[] {
			return radioStations;
		},
		getPodcastEpisode(id : number) : PodcastEpisode {
			return _(podcastChannels).map('episodes').flatten().find({ id: Number(id) });
		},
		getAllPodcastEpisodes() : PodcastEpisode[] {
			return _(podcastChannels).map('episodes').flatten().value();
		},
		getPodcastChannel(id : number) : PodcastChannel {
			return _.find(podcastChannels, { id: Number(id) });
		},
		getAllPodcastChannels() : PodcastChannel[] {
			return podcastChannels;
		},
		getPodcastChannelsCount() : number {
			return podcastChannels?.length ?? 0;
		},
		findTracksByArtist(artistId : number) : {[id: number] : Track} {
			return _.filter(tracksIndex, {artistId: Number(artistId)});
		},
		collectionLoaded() : boolean {
			return artists !== null;
		},
		playlistsLoaded() : boolean {
			return playlists !== null;
		},
		foldersLoaded() : boolean {
			return folders !== null;
		},
		genresLoaded() : boolean {
			return genres !== null;
		},
		radioStationsLoaded() : boolean {
			return radioStations !== null;
		},
		podcastsLoaded() : boolean {
			return podcastChannels !== null;
		},
		searchTracks(query : string, maxResults = Infinity) : SearchResult<Track> {
			return search(tracksIndex, ['title', 'artistName'], query, maxResults);
		},
		searchTracksInAlbums(query : string, maxResults = Infinity) : SearchResult<Track> {
			return search(
					tracksIndex,
					['title', 'artistName', 'album.name', 'album.year', 'album.artist.name'],
					query,
					maxResults);
		},
		searchTracksInFolders(query : string, maxResults = Infinity) : SearchResult<Track> {
			return search(
					tracksIndex,
					['title', 'artistName', 'folder.path'],
					query,
					maxResults);
		},
		searchTracksInGenres(query : string, maxResults = Infinity) : SearchResult<Track> {
			return search(
					tracksIndex,
					['title', 'artistName', 'genre.name'],
					query,
					maxResults);
		},
		searchTracksInPlaylist(playlistId : number, query : string, maxResults = Infinity) : SearchResult<PlaylistEntry> {
			let list = this.getPlaylist(playlistId) || [];
			list = _.map(list.tracks, 'track');
			list = _.uniq(list);
			return search(list, ['title', 'artistName'], query, maxResults);
		},
		searchRadioStations(query : string, maxResults = Infinity) : SearchResult<Track> {
			let stations = _.map(radioStations, 'track');
			return search(stations, ['name', 'stream_url'], query, maxResults);
		},
		searchPodcasts(query : string, maxResults = Infinity) : SearchResult<PodcastEpisode> {
			let episodes = _(podcastChannels).map('episodes').flatten().value();
			return search(episodes, ['title', 'channel.title'], query, maxResults);
		},
	};
}]);
