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
	var playlists = null;
	var folders = null;

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

	function sortByYearNameAndDisc(aAlbums) {
		aAlbums = _.sortBy(aAlbums, 'disk');
		sortByTextField(aAlbums, 'name');
		aAlbums = _.sortBy(aAlbums, 'year');
		return aAlbums;
	}

	function sortByNumberAndTitle(tracks) {
		sortByTextField(tracks, 'title');
		tracks = _.sortBy(tracks, 'number');
		return tracks;
	}

	/**
	 * Sort the passed in collection alphabetically, and set up parent references
	 */
	function transformCollection(collection) {
		sortByTextField(collection, 'name');
		_.forEach(collection, function(artist) {
			artist.albums = sortByYearNameAndDisc(artist.albums);
			_.forEach(artist.albums, function(album) {
				album.artist = artist;
				album.tracks = sortByNumberAndTitle(album.tracks);
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

	/** Convert string to "folded" form suitable for fuzzy matching */
	function foldString(str) {
		if (str !== null) {
			str = str.toLocaleLowerCase();

			// Skip the normalization if the browser is ancient and doesn't support it
			if ('normalize' in String.prototype) {
				str = str.normalize('NFD').replace(/[\u0300-\u036f]/g, "");
			}
		}

		return str;
	}

	/** Split search query to array by whitespace.
	 *  In case the query is surrounded by quatation marks ", the query is treated as one entity,
	 *  including the whitespace. In this case, the quotation marks are removed by this function.
	 */
	function splitSearchQuery(query) {
		if (query.length > 2 && OC_Music_Utils.startsWith(query, '"') && OC_Music_Utils.endsWith(query, '"')) {
			return [query.slice(1, -1)];
		} else {
			return query.match(/\S+/g) || [];
		}
	}

	function objectFieldsContainAll(object, fields, subStrings) {
		return _.every(subStrings, function(subStr) {
			return _.some(fields, function(field) {
				var value = object[field];
				return (value !== null && foldString(value).indexOf(subStr) !== -1);
			});
		});
	}

	function search(container, fields, query) {
		query = foldString(query);
		// In case the query contains many words separated with whitespace, each part
		// has to be found but the whitespace is disregarded.
		var queryParts = splitSearchQuery(query);

		// @a fields may be an array or an idividual string
		if (!Array.isArray(fields)) {
			fields = [fields];
		}

		return _.filter(container, function(item) {
			return objectFieldsContainAll(item, fields, queryParts);
		});
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
		searchTracks: function(query, maxResults/*optional*/) {
			return OC_Music_Utils.limitedUnion(
				maxResults,
				search(tracksIndex, ['title', 'artistName'], query)
			);
		},
		searchAlbums: function(query, maxResults/*optional*/) {
			return OC_Music_Utils.limitedUnion(
				maxResults,
				search(albums, ['name', 'year'], query)
			);
		},
		searchArtists: function(query, maxResults/*optional*/) {
			return OC_Music_Utils.limitedUnion(
				maxResults,
				search(artists, 'name', query)
			);
		},
		searchFolders: function(query, maxResults/*optional*/) {
			return OC_Music_Utils.limitedUnion(
				maxResults,
				search(folders, 'path', query)
			);
		},
		searchTracksInPlaylist: function(playlistId, query, maxResults/*optional*/) {
			var list = this.getPlaylist(playlistId) || [];
			list = _.pluck(list.tracks, 'track');
			list = _.uniq(list);
			return OC_Music_Utils.limitedUnion(
				maxResults,
				search(list, ['title', 'artistName'], query)
			);
		},
	};
}]);
