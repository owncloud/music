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
	var allTracks = null;
	var playlists = null;

	// index tracks in a collection (which has tree-like structure artists > albums > tracks)
	function createTracksIndex(albums) {
		var tracksDict = {};
		var tracks = _.flatten(_.pluck(albums, 'tracks'));
		_.forEach(tracks, function(track) {
			tracksDict[track.id] = track;
		});

		return tracksDict;
	}

	function sortByName(items) {
		return _.sortBy(items, function(i) { return i.name.toLowerCase(); });
	}

	function sortByYearNameAndDisc(albums) {
		albums = _.sortBy(albums, "disk");
		albums = sortByName(albums);
		albums = _.sortBy(albums, "year");
		return albums;
	}

	function sortByNumberAndTitle(tracks) {
		tracks = _.sortBy(tracks, function(t) { return t.title.toLowerCase(); });
		tracks = _.sortBy(tracks, "number");
		return tracks;
	}

	function sortCollection(collection) {
		collection = sortByName(collection);
		_.forEach(collection, function(artist) {
			artist.albums = sortByYearNameAndDisc(artist.albums);
			_.forEach(artist.albums, function(album) {
				album.tracks = sortByNumberAndTitle(album.tracks);
			});
		});
		return collection;
	}

	return {
		setCollection: function(collection) {
			artists = sortCollection(collection);
			albums = _.flatten(_.pluck(artists, 'albums'));
			allTracks = createTracksIndex(albums);
		},
		setPlaylists: function(lists) {
			playlists = lists;
		},
		updatePlaylist: function(list) {
			playlist = this.getPlaylist(list.id);
			if (playlist) {
				playlist.name = list.name;
				playlist.trackIds = list.trackIds;
			}
		},
		addPlaylist: function(playlist) {
			playlists.push(playlist);
		},
		removePlaylist: function(playlist) {
			playlists.splice(playlists.indexOf(playlist), 1);
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
		getTrack: function(id) {
			return allTracks[id];
		},
		getAllTracks: function() {
			return allTracks;
		},
		getTrackCount: function() {
			return allTracks ? Object.keys(allTracks).length : 0;
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
