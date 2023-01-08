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

angular.module('Music').service('libraryService', [function() {

	let ignoredArticles = [];
	let artists = null;
	let albums = null;
	let tracksIndex = {};
	let tracksInAlbumOrder = null;
	let tracksInAlphaOrder = null;
	let tracksInGenreOrder = null;
	let playlists = null;
	let folders = null;
	let genres = null;
	let radioStations = null;
	let podcastChannels = null;

	/** 
	 * Sort array according to a specified text field. The field may be specified as a dot-separated path.
	 * Note:  The exact ordering is browser-dependant and usually affected by the browser language.
	 * Note2: The array is sorted in-place instead of returning a new array.
	 */
	function sortByTextField(items, field) {
		let getSortProperty = _.property(field);
		let locale = OCA.Music.Utils.getLocale();

		items.sort(function(a, b) {
			let aProp = getSortProperty(a);
			let bProp = getSortProperty(b);

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
	function sortByPlaylistEntryTextField(items, field) {
		return sortByTextField(items, 'track.' + field);
	}

	function sortByNumericField(items, field) {
		let getSortProperty = _.property(field);
		items.sort(function(a, b) {
			return Number(getSortProperty(a)) - Number(getSortProperty(b));
		});
	}

	function sortByYearAndName(aAlbums) {
		sortByTextField(aAlbums, 'name');
		aAlbums = _.sortBy(aAlbums, 'year');
		return aAlbums;
	}

	function sortByDiskNumberAndTitle(tracks) {
		sortByTextField(tracks, 'title');
		tracks = _.sortBy(tracks, 'number');
		tracks = _.sortBy(tracks, 'disk');
		return tracks;
	}

	function createArtistSortName(name) {
		for (var article of ignoredArticles) {
			if (name.toLowerCase().startsWith(article.toLowerCase() + ' ')) {
				return name.substring(article.length + 1).trim();
			}
		}
		return name;
	}

	/**
	 * Sort the passed in collection alphabetically, and set up parent references
	 */
	function transformCollection(collection) {
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

	function moveArrayElement(array, from, to) {
		array.splice(to, 0, array.splice(from, 1)[0]);
	}

	function playlistEntry(track) {
		return (track !== null) ? { track: track } : null;
	}

	function playlistEntryFromId(trackId) {
		return playlistEntry(tracksIndex[trackId] ?? null);
	}

	function wrapRadioStation(station) {
		station.type = 'radio';
		return playlistEntry(station);
	}

	function wrapPlaylist(playlist) {
		let wrapped = $.extend({}, playlist); // clone the playlist
		wrapped.tracks = _(playlist.trackIds).map(playlistEntryFromId).reject(_.isNull).value(); // null-values are possible during scanning
		delete wrapped.trackIds;
		return wrapped;
	}

	function wrapFolder(folder) {
		let wrapped = wrapPlaylist(folder);
		wrapped.path = null; // set up later
		wrapped.expanded = (folder.parent === null); // the root folder is expanded by default
		return wrapped;
	}

	function setUpFolderPath(folder) {
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

	function getFolderTracksRecursively(folder) {
		let subFolderTracks = _(folder.subfolders).map(getFolderTracksRecursively).flatten().value();
		return [...subFolderTracks, ...folder.tracks];
	}

	function initPodcastChannel(channel) {
		_.forEach(channel.episodes, function(episode) {
			episode.channel = channel;
			episode.type = 'podcast';
		});
	}

	function createTrackContainers() {
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

	let diacriticRegExp = /[\u0300-\u036f]/g;
	/** Convert string to "folded" form suitable for fuzzy matching */
	function foldString(str) {
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
	function splitSearchQuery(query) {
		let regExQuoted = /".*?"/g;

		// Get any quoted substring. Also the quotation marks get extracted, and they are sliced off separately.
		let quoted = query.match(regExQuoted) || [];
		quoted = _.map(quoted, function(str) {
			return str.slice(1, -1);
		});

		// remove the quoted substrings and stray quotation marks, and extact the rest of the parts
		query = query.replace(regExQuoted, ' ');
		query = query.replace('"', ' ');
		let unquoted = query.match(/\S+/g) || [];

		return quoted.concat(unquoted);
	}

	function objectFieldsContainAll(object, getFieldValueFuncs, subStrings) {
		return _.every(subStrings, function(subStr) {
			return _.some(getFieldValueFuncs, function(getter) {
				let value = getter(object);
				return (value !== null && foldString(value).indexOf(subStr) !== -1);
			});
		});
	}

	function search(container, fields, query, maxResults) {
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
		setIgnoredArticles: function(articles) {
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
		setCollection: function(collection) {
			artists = transformCollection(collection);
			albums = _(artists).map('albums').flatten().value();
			createTrackContainers();
		},
		setPlaylists: function(lists) {
			playlists = _.map(lists, wrapPlaylist);
		},
		setFolders: function(folderData) {
			if (!folderData) {
				folders = null;
			} else {
				folders = _.map(folderData, wrapFolder);
				sortByTextField(folders, 'name');
				// the tracks within each folder are sorted by the file name by the back-end

				// create temporary look-up-table for the folders to speed up setting up the parent references
				let foldersLut = {};
				_.forEach(folders, function(folder) {
					foldersLut[folder.id] = folder;
				});

				_.forEach(folders, function(folder) {
					// substitute parent id with a reference to the parent folder
					folder.parent = foldersLut[folder.parent] ?? null;
					// set parent folder references for the contained tracks
					_.forEach(folder.tracks, function(trackEntry) {
						trackEntry.track.folder = folder;
					});
					// init subfolder array
					folder.subfolders = [];
				});

				_.forEach(folders, function(folder) {
					// compile the full path for each folder by following the parent references
					setUpFolderPath(folder);
					// set the subfolder references
					if (folder.parent !== null) {
						folder.parent.subfolders.push(folder);
					}
				});
			}
		},
		setGenres: function(genreData) {
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
		setRadioStations: function(radioStationsData) {
			radioStations = _.map(radioStationsData, wrapRadioStation);
			this.sortRadioStations();
		},
		sortRadioStations: function() {
			sortByPlaylistEntryTextField(radioStations, 'stream_url');
			sortByPlaylistEntryTextField(radioStations, 'name');
		},
		addRadioStation: function(radioStationData) {
			this.addRadioStations([radioStationData]);
		},
		addRadioStations: function(radioStationsData) {
			radioStations = radioStations.concat(_.map(radioStationsData, wrapRadioStation));
			sortByPlaylistEntryTextField(radioStations, 'stream_url');
			sortByPlaylistEntryTextField(radioStations, 'name');
		},
		removeRadioStation: function(stationId) {
			let idx = _.findIndex(radioStations, entry => entry.track.id == stationId);
			radioStations.splice(idx, 1);
			return idx;
		},
		setPodcasts: function(podcastsData) {
			sortByTextField(podcastsData, 'title');
			// set the parent references for each episode 
			_.forEach(podcastsData, initPodcastChannel);
			podcastChannels = podcastsData;
		},
		addPodcastChannel: function(channel) {
			initPodcastChannel(channel);
			podcastChannels.push(channel);
			sortByTextField(podcastChannels, 'title');
		},
		replacePodcastChannel: function(channel) {
			initPodcastChannel(channel);
			let idx = _.findIndex(podcastChannels, { id: channel.id });
			podcastChannels[idx] = channel;
		},
		removePodcastChannel: function(channel) {
			let idx = _.findIndex(podcastChannels, { id: channel.id });
			podcastChannels.splice(idx, 1);
		},
		addPlaylist: function(playlist) {
			playlists.push(wrapPlaylist(playlist));
		},
		removePlaylist: function(playlist) {
			playlists.splice(playlists.indexOf(playlist), 1);
		},
		replacePlaylist: function(playlist) {
			let idx = _.findIndex(playlists, { id: playlist.id });
			playlists[idx] = wrapPlaylist(playlist);
		},
		addToPlaylist: function(playlistId, trackId) {
			let playlist = this.getPlaylist(playlistId);
			playlist.tracks.push(playlistEntryFromId(trackId));
		},
		removeFromPlaylist: function(playlistId, indexToRemove) {
			let playlist = this.getPlaylist(playlistId);
			playlist.tracks.splice(indexToRemove, 1);
		},
		reorderPlaylist: function(playlistId, srcIndex, dstIndex) {
			let playlist = this.getPlaylist(playlistId);
			moveArrayElement(playlist.tracks, srcIndex, dstIndex);
		},
		sortPlaylist: function(playlistId, byProperty) {
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
		removeDuplicatesFromPlaylist: function(playlistId) {
			let playlist = this.getPlaylist(playlistId);
			let foundIds = {};
			let indicesToRemove = [];

			// find the indices containing duplicates
			for (var i = 0; i < playlist.tracks.length; ++i) {
				let id = playlist.tracks[i].track.id;
				if (id in foundIds) {
					indicesToRemove.push(i);
				} else {
					foundIds[id] = 1;
				}
			}

			// remove (and return) the duplicates
			return _.pullAt(playlist.tracks, indicesToRemove);
		},
		getArtist: function(id) {
			let artist = _.find(artists, { id: Number(id) });
			if (!artist) {
				// there's no such album artist, try to find a matching track artist (who has no albums)
				let track = _.find(tracksIndex, { artistId: Number(id)} );
				if (track) {
					artist = {
							id: track.artistId,
							name: track.artistName,
							albums: []
					};
				}
			}
			return artist;
		},
		getAllArtists: function() {
			return artists;
		},
		getAlbum: function(id) {
			return _.find(albums, { id: Number(id) });
		},
		getAlbumCount: function() {
			return albums?.length ?? 0;
		},
		getTrack: function(id) {
			return tracksIndex[id];
		},
		getTracksInAlphaOrder: function() {
			return tracksInAlphaOrder;
		},
		getTracksInAlbumOrder: function() {
			return tracksInAlbumOrder;
		},
		getTracksInFolderOrder: function(treeMode) {
			return treeMode
				? getFolderTracksRecursively(this.getRootFolder())
				: _(folders).map('tracks').flatten().value();
		},
		getTracksInGenreOrder: function() {
			return tracksInGenreOrder;
		},
		getTrackCount: function() {
			return tracksInAlphaOrder?.length ?? 0;
		},
		getPlaylist: function(id) {
			return _.find(playlists, { id: Number(id) });
		},
		getAllPlaylists: function() {
			return playlists;
		},
		getFolder: function(id) {
			return _.find(folders, { id: Number(id) });
		},
		getFolderTracks: function(folder, recursively) {
			return recursively ? getFolderTracksRecursively(folder) : folder.tracks;
		},
		getAllFoldersWithTracks: function() {
			return _.filter(folders, (folder) => folder.tracks.length > 0);
		},
		getRootFolder: function() {
			return _.find(folders, { parent: null });
		},
		getGenre: function(id) {
			return _.find(genres, { id: Number(id) });
		},
		getAllGenres: function() {
			return genres;
		},
		getRadioStation: function(id) {
			return _.find(radioStations, ['track.id', Number(id)])?.track;
		},
		getAllRadioStations: function() {
			return radioStations;
		},
		getPodcastEpisode: function(id) {
			return _(podcastChannels).map('episodes').flatten().find({ id: Number(id) });
		},
		getAllPodcastEpisodes: function() {
			return _(podcastChannels).map('episodes').flatten().value();
		},
		getPodcastChannel: function(id) {
			return _.find(podcastChannels, { id: Number(id) });
		},
		getAllPodcastChannels: function() {
			return podcastChannels;
		},
		getPodcastChannelsCount: function() {
			return podcastChannels?.length ?? 0;
		},
		findTracksByArtist: function(artistId) {
			return _.filter(tracksIndex, {artistId: Number(artistId)});
		},
		collectionLoaded: function() {
			return artists !== null;
		},
		playlistsLoaded: function() {
			return playlists !== null;
		},
		foldersLoaded: function() {
			return folders !== null;
		},
		genresLoaded: function() {
			return genres !== null;
		},
		radioStationsLoaded: function() {
			return radioStations !== null;
		},
		podcastsLoaded: function() {
			return podcastChannels !== null;
		},
		searchTracks: function(query, maxResults = Infinity) {
			return search(tracksIndex, ['title', 'artistName'], query, maxResults);
		},
		searchTracksInAlbums: function(query, maxResults = Infinity) {
			return search(
					tracksIndex,
					['title', 'artistName', 'album.name', 'album.year', 'album.artist.name'],
					query,
					maxResults);
		},
		searchTracksInFolders: function(query, maxResults = Infinity) {
			return search(
					tracksIndex,
					['title', 'artistName', 'folder.path'],
					query,
					maxResults);
		},
		searchTracksInGenres: function(query, maxResults = Infinity) {
			return search(
					tracksIndex,
					['title', 'artistName', 'genre.name'],
					query,
					maxResults);
		},
		searchTracksInPlaylist: function(playlistId, query, maxResults = Infinity) {
			let list = this.getPlaylist(playlistId) || [];
			list = _.map(list.tracks, 'track');
			list = _.uniq(list);
			return search(list, ['title', 'artistName'], query, maxResults);
		},
		searchRadioStations: function(query, maxResults = Infinity) {
			let stations = _.map(radioStations, 'track');
			return search(stations, ['name', 'stream_url'], query, maxResults);
		},
		searchPodcasts: function(query, maxResults = Infinity) {
			let episodes = _(podcastChannels).map('episodes').flatten().value();
			return search(episodes, ['title', 'channel.title'], query, maxResults);
		},
	};
}]);
