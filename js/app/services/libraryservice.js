/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright 2017 - 2021 Pauli Järvinen
 *
 */

angular.module('Music').service('libraryService', [function() {

	var artists = null;
	var albums = null;
	var tracksIndex = {};
	var tracksInAlbumOrder = null;
	var tracksInAlphaOrder = null;
	var tracksInFolderOrder = null;
	var tracksInGenreOrder = null;
	var playlists = null;
	var folders = null;
	var genres = null;
	var radioStations = null;

	/** 
	 * Sort array according to a specified text field. The field may be specified as a dot-separated path.
	 * Note:  The exact ordering is browser-dependant and usually affected by the browser language.
	 * Note2: The array is sorted in-place instead of returning a new array.
	 */
	function sortByTextField(items, field) {
		var getSortProperty = _.property(field);
		items.sort(function(a, b) {
			return getSortProperty(a).localeCompare(getSortProperty(b));
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
		var getSortProperty = _.property(field);
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

	/**
	 * Sort the passed in collection alphabetically, and set up parent references
	 */
	function transformCollection(collection) {
		sortByTextField(collection, 'name');
		_.forEach(collection, function(artist) {
			artist.albums = sortByYearAndName(artist.albums);
			_.forEach(artist.albums, function(album) {
				album.artist = artist;
				album.tracks = sortByDiskNumberAndTitle(album.tracks);
				_.forEach(album.tracks, function(track) {
					track.album = album;
				});
			});
		});
		return collection;
	}

	function moveArrayElement(array, from, to) {
		array.splice(to, 0, array.splice(from, 1)[0]);
	}

	function playlistEntry(track) {
		return { track: track };
	}

	function playlistEntryFromId(trackId) {
		return playlistEntry(tracksIndex[trackId]);
	}

	function wrapPlaylist(playlist) {
		var wrapped = $.extend({}, playlist); // clone the playlist
		wrapped.tracks = _.map(playlist.trackIds, playlistEntryFromId);
		delete wrapped.trackIds;
		return wrapped;
	}

	function wrapFolder(folder) {
		var wrapped = wrapPlaylist(folder);
		wrapped.path = folder.path;
		return wrapped;
	}

	function createTrackContainers() {
		// album order "playlist"
		var tracks = _.flatten(_.map(albums, 'tracks'));
		tracksInAlbumOrder = _.map(tracks, playlistEntry);

		// alphabetic order "playlist"
		sortByTextField(tracks, 'title');
		sortByTextField(tracks, 'artistName');
		tracksInAlphaOrder = _.map(tracks, playlistEntry);

		// tracks index
		_.forEach(tracks, function(track) {
			tracksIndex[track.id] = track;
		});
	}

	var diacriticRegExp = /[\u0300-\u036f]/g;
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
		var regExQuoted = /".*?"/g;

		// Get any quoted substring. Also the quotation marks get extracted, and they are sliced off separately.
		var quoted = query.match(regExQuoted) || [];
		quoted = _.map(quoted, function(str) {
			return str.slice(1, -1);
		});

		// remove the quoted substrings and stray quotation marks, and extact the rest of the parts
		query = query.replace(regExQuoted, ' ');
		query = query.replace('"', ' ');
		var unquoted = query.match(/\S+/g) || [];

		return quoted.concat(unquoted);
	}

	function objectFieldsContainAll(object, getFieldValueFuncs, subStrings) {
		return _.every(subStrings, function(subStr) {
			return _.some(getFieldValueFuncs, function(getter) {
				var value = getter(object);
				return (value !== null && foldString(value).indexOf(subStr) !== -1);
			});
		});
	}

	function search(container, fields, query, maxResults/*optional*/) {
		maxResults = maxResults || Infinity;

		query = foldString(query);
		// In case the query contains many words separated with whitespace, each part
		// has to be found but the whitespace is disregarded.
		var queryParts = splitSearchQuery(query);

		// @a fields may be an array or an idividual string
		if (!Array.isArray(fields)) {
			fields = [fields];
		}

		// Field may be given as a '.'-separated path;
		// convert the fields to corresponding getter functions.
		var fieldGetterFuncs = _.map(fields, _.property);

		var matchCount = 0;
		var maxLimitReached = false;
		var matches = _.filter(container, function(item) {
			var matched = !maxLimitReached && objectFieldsContainAll(item, fieldGetterFuncs, queryParts);
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
				tracksInFolderOrder = null;
			} else {
				folders = _.map(folderData, wrapFolder);
				sortByTextField(folders, 'name');
				// the tracks within each folder are sorted by the file name by the back-end 
				_.forEach(folders, function(folder) {
					_.forEach(folder.tracks, function(trackEntry) {
						trackEntry.track.folder = folder;
					});
				});
				tracksInFolderOrder = _(folders).map('tracks').flatten().value();
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
					sortByPlaylistEntryTextField(genre.tracks, 'artistName');

					_.forEach(genre.tracks, function(trackEntry) {
						trackEntry.track.genre = genre;
					});
				});

				tracksInGenreOrder = _(genres).map('tracks').flatten().value();
			}
		},
		setRadioStations: function(radioStationsData) {
			radioStations = _.map(radioStationsData, playlistEntry);
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
			radioStations = radioStations.concat(_.map(radioStationsData, playlistEntry));
			sortByPlaylistEntryTextField(radioStations, 'stream_url');
			sortByPlaylistEntryTextField(radioStations, 'name');
		},
		removeRadioStation: function(stationId) {
			var idx = _.findIndex(radioStations, entry => entry.track.id == stationId);
			radioStations.splice(idx, 1);
			return idx;
		},
		addPlaylist: function(playlist) {
			playlists.push(wrapPlaylist(playlist));
		},
		removePlaylist: function(playlist) {
			playlists.splice(playlists.indexOf(playlist), 1);
		},
		replacePlaylist: function(playlist) {
			var idx = _.findIndex(playlists, { id: playlist.id });
			playlists[idx] = wrapPlaylist(playlist);
		},
		addToPlaylist: function(playlistId, trackId) {
			var playlist = this.getPlaylist(playlistId);
			playlist.tracks.push(playlistEntryFromId(trackId));
		},
		removeFromPlaylist: function(playlistId, indexToRemove) {
			var playlist = this.getPlaylist(playlistId);
			playlist.tracks.splice(indexToRemove, 1);
		},
		reorderPlaylist: function(playlistId, srcIndex, dstIndex) {
			var playlist = this.getPlaylist(playlistId);
			moveArrayElement(playlist.tracks, srcIndex, dstIndex);
		},
		sortPlaylist: function(playlistId, byProperty) {
			var playlist = this.getPlaylist(playlistId);
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
				sortByTextField(playlist.tracks, 'track.artistName');
				break;
			default:
				console.error('Unexpected playlist sort property ' + byProperty);
				break;
			}
		},
		getArtist: function(id) {
			var artist = _.find(artists, { id: Number(id) });
			if (!artist) {
				// there's no such album artist, try to find a matching track artist (who has no albums)
				var track = _.find(tracksIndex, { artistId: Number(id)} );
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
			return albums ? albums.length : 0;
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
		getTracksInFolderOrder: function() {
			return tracksInFolderOrder;
		},
		getTracksInGenreOrder: function() {
			return tracksInGenreOrder;
		},
		getTrackCount: function() {
			return tracksInAlphaOrder ? tracksInAlphaOrder.length : 0;
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
		getAllFolders: function() {
			return folders;
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
		searchTracks: function(query, maxResults/*optional*/) {
			return search(tracksIndex, ['title', 'artistName'], query, maxResults);
		},
		searchTracksInAlbums: function(query, maxResults/*optional*/) {
			return search(
					tracksIndex,
					['title', 'artistName', 'album.name', 'album.year', 'album.artist.name'],
					query,
					maxResults);
		},
		searchTracksInFolders: function(query, maxResults/*optional*/) {
			return search(
					tracksIndex,
					['title', 'artistName', 'folder.path'],
					query,
					maxResults);
		},
		searchTracksInGenres: function(query, maxResults/*optional*/) {
			return search(
					tracksIndex,
					['title', 'artistName', 'genre.name'],
					query,
					maxResults);
		},
		searchTracksInPlaylist: function(playlistId, query, maxResults/*optional*/) {
			var list = this.getPlaylist(playlistId) || [];
			list = _.map(list.tracks, 'track');
			list = _.uniq(list);
			return search(list, ['title', 'artistName'], query, maxResults);
		},
		searchRadioStations: function(query, maxResults/*optional*/) {
			var stations = _.map(radioStations, 'track');
			return search(stations, ['name', 'stream_url'], query, maxResults);
		},
	};
}]);
