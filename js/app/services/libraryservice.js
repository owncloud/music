/**
 * ownCloud - Music app
 *
 * @author Pauli Järvinen
 * @copyright 2017 Pauli Järvinen <pauli.jarvinen@gmail.com>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

angular.module('Music').service('libraryService', ['$rootScope', function($rootScope) {

	var artists = null;
	var albums = null;
	var tracksIndex = {};
	var tracksInAlbumOrder = null;
	var tracksInAlphaOrder = null;
	var playlists = null;

	function sortCaseInsensitive(items, field) {
		return _.sortBy(items, function(i) { return i[field].toLowerCase(); });
	}

	function sortByYearNameAndDisc(albums) {
		albums = _.sortBy(albums, 'disk');
		albums = sortCaseInsensitive(albums, 'name');
		albums = _.sortBy(albums, 'year');
		return albums;
	}

	function sortByNumberAndTitle(tracks) {
		tracks = sortCaseInsensitive(tracks, 'title');
		tracks = _.sortBy(tracks, 'number');
		return tracks;
	}

	function sortCollection(collection) {
		collection = sortCaseInsensitive(collection, 'name');
		_.forEach(collection, function(artist) {
			artist.albums = sortByYearNameAndDisc(artist.albums);
			_.forEach(artist.albums, function(album) {
				album.tracks = sortByNumberAndTitle(album.tracks);
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

	function createTrackContainers() {
		// album order "playlist"
		var tracks = _.flatten(_.pluck(albums, 'tracks'));
		tracksInAlbumOrder = _.map(tracks, playlistEntry);

		// alphabetic order "playlist"
		tracks = sortCaseInsensitive(tracks, 'title');
		tracks = sortCaseInsensitive(tracks, 'artistName');
		tracksInAlphaOrder = _.map(tracks, playlistEntry);

		// tracks index
		_.forEach(tracks, function(track) {
			tracksIndex[track.id] = track;
		});
	}

	return {
		setCollection: function(collection) {
			artists = sortCollection(collection);
			albums = _.flatten(_.pluck(artists, 'albums'));
			createTrackContainers();
		},
		setPlaylists: function(lists) {
			playlists = _.map(lists, wrapPlaylist);
		},
		addPlaylist: function(playlist) {
			playlists.push(wrapPlaylist(playlist));
		},
		removePlaylist: function(playlist) {
			playlists.splice(playlists.indexOf(playlist), 1);
		},
		addToPlaylist: function(playlistId, trackId) {
			playlist = this.getPlaylist(playlistId);
			playlist.tracks.push(playlistEntryFromId(trackId));
		},
		removeFromPlaylist: function(playlistId, indexToRemove) {
			playlist = this.getPlaylist(playlistId);
			playlist.tracks.splice(indexToRemove, 1);
		},
		reorderPlaylist: function(playlistId, srcIndex, dstIndex) {
			playlist = this.getPlaylist(playlistId);
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
		getTrackCount: function() {
			return tracksInAlphaOrder ? tracksInAlphaOrder.length : 0;
		},
		getPlaylist: function(id) {
			return _.findWhere(playlists, { id: Number(id) });
		},
		getAllPlaylists: function() {
			return playlists;
		},
		findAlbumOfTrack: function(trackId) {
			return _.find(albums, function(album) {
				return _.findWhere(album.tracks, {id : Number(trackId)});
			});
		},
		collectionLoaded: function() {
			return artists !== null;
		},
		playlistsLoaded: function() {
			return playlists !== null;
		}
	};
}]);
