/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright 2017 - 2020 Pauli Järvinen
 *
 */

angular.module('Music').service('libraryService', ['$rootScope', function($rootScope) {

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

	/** 
	 * Sort array according to a specified text field.
	 * Note:  The exact ordering is browser-dependant and usually affected by the browser language.
	 * Note2: The array is sorted in-place instead of returning a new array.
	 */
	function sortByTextField(items, field) {
		items.sort(function(a, b) {
			return a[field].localeCompare(b[field]);
		});
	}

	/**
	 * Like sortByTextField but to be used with arrays of playlist entries where
	 * field is within outer field "track".
	 */
	function sortByPlaylistEntryField(items, field) {
		items.sort(function(a, b) {
			return a.track[field].localeCompare(b.track[field]);
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
		return {
			id: playlist.id,
			name: playlist.name,
			tracks: _.map(playlist.trackIds, playlistEntryFromId)
		};
	}

	function wrapFolder(folder) {
		var wrapped = wrapPlaylist(folder);
		wrapped.path = folder.path;
		return wrapped;
	}

	function createTrackContainers() {
		// album order "playlist"
		var tracks = _.flatten(_.pluck(albums, 'tracks'));
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
				str = str.normalize('NFD').replace(diacriticRegExp, "");
			}
		}

		return str;
	}

	/** Split search query to array by whitespace.
	 *  As an exception, quoted substrings are kept as one entity. The quotation marks are removed.
	 */
	function splitSearchQuery(query) {
		var regExQuoted = /\".*?\"/g;

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

	function fieldPathToGetterFunc(path) {
		// On the newest underscore.js, this could be achieved with 
		// return _.property(path.split('.'));
		// but the cloud core may ship so old underscore.js that property method doesn't support nesting.
		// The following is a modified copy from the up-to-date sources of underscore.js.
		path = path.split('.');
		return function(obj) {
			for (var i = 0, length = path.length; i < length; i++) {
				if (obj === null) {
					return null;
				}
				obj = obj[path[i]];
			}
			return length ? obj : null;
		};
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
		var fieldGetterFuncs = _.map(fields, fieldPathToGetterFunc);

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
			albums = _.flatten(_.pluck(artists, 'albums'));
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
				_.forEach(folders, function(folder) {
					sortByPlaylistEntryField(folder.tracks, 'title');
					sortByPlaylistEntryField(folder.tracks, 'artistName');

					_.forEach(folder.tracks, function(trackEntry) {
						trackEntry.track.folder = folder;
					});
				});
				tracksInFolderOrder = _.flatten(_.pluck(folders, 'tracks'));
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
					sortByPlaylistEntryField(genre.tracks, 'title');
					sortByPlaylistEntryField(genre.tracks, 'artistName');

					_.forEach(genre.tracks, function(trackEntry) {
						trackEntry.track.genre = genre;
					});
				});

				tracksInGenreOrder = _.flatten(_.pluck(genres, 'tracks'));
			}
		},
		addPlaylist: function(playlist) {
			playlists.push(wrapPlaylist(playlist));
		},
		removePlaylist: function(playlist) {
			playlists.splice(playlists.indexOf(playlist), 1);
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
		getArtist: function(id) {
			return _.findWhere(artists, { id: Number(id) });
		},
		getAllArtists: function() {
			return artists;
		},
		getAlbum: function(id) {
			return _.findWhere(albums, { id: Number(id) });
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
			return _.findWhere(playlists, { id: Number(id) });
		},
		getAllPlaylists: function() {
			return playlists;
		},
		getFolder: function(id) {
			return _.findWhere(folders, { id: Number(id) });
		},
		getAllFolders: function() {
			return folders;
		},
		getGenre: function(id) {
			return _.findWhere(genres, { id: Number(id) });
		},
		getAllGenres: function() {
			return genres;
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
			list = _.pluck(list.tracks, 'track');
			list = _.uniq(list);
			return search(list, ['title', 'artistName'], query, maxResults);
		},
	};
}]);
