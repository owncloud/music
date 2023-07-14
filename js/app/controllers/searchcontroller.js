/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2020, 2021
 */

/**
 * This controller implements the search/filtering logic for all views. The details
 * of the search still vary per-view.
 * 
 * When the search query is written to the searchbox, the core automatically sends the query
 * to the backend. However, we disregard any results from the back-end and conduct the search
 * on our own, on the front-end.
 */
angular.module('Music').controller('SearchController', [
'$scope', '$rootScope', 'libraryService', '$timeout', '$document', 'gettextCatalog',
function ($scope, $rootScope, libraryService, $timeout, $document, gettextCatalog) {

	const MAX_MATCHES = 5000;
	const MAX_MATCHES_IN_PLAYLIST = 1000;
	const MAX_FOLDER_MATCHES_IN_TREE_LAYOUT = 50;

	let searchform = $('.searchbox');
	let searchbox = $('#searchbox');
	let treeFolderMatches = {};

	if (searchbox.length === 0) { // NC 20+
		$.initialize('.unified-search__form', function(_index, elem) {
			searchform = $(elem);
			searchbox = searchform.find('input');
			init();
		});
	}
	else { // NC < 20 or OC
		init();
		// the keyboard shortcut has to be registered manually, on NC20 this is handled by the core
		registerCtrlF();
	}

	function init() {
		$scope.queryString = searchbox.val().trim();

		/** Conduct the search when there is a pause in typing in text */
		let checkQueryChange = _.debounce(function() {
			if ($scope.queryString != searchbox.val().trim()) {
				onEnterSearchString();
			}
		}, 250);
		searchbox.bind('propertychange change keyup input paste', checkQueryChange);

		/** Handle clearing the searchbox. This has to be registered to the parent form
		 *  of the #searchbox element.
		 */
		searchform.on('reset', function() {
			$scope.queryString = '';
			$scope.$apply(startProgress);
			$timeout(clearSearch);
		});

		/** Run search when enter pressed within the searchbox */
		searchbox.bind('keydown', function (event) {
			if (event.which === 13) {
				onEnterSearchString();
			}
		});
	}

	function registerCtrlF() {
		/** Catch ctrl+f except when the Settings view is active */
		$document.bind('keydown', function(e) {
			if ($rootScope.currentView !== '#/settings' && e.ctrlKey && e.key === 'f') {
				searchbox.focus();
				return false;
			}
			return true;
		});
	}

	/** Search query is considered to be empty if it contains only whitespace and/or quotes (") */
	function queryIsEmpty() {
		return ($scope.queryString.length === 0 || $scope.queryString.match(/[^\s"]/) === null);
	}

	function onEnterSearchString() {
		$scope.queryString = searchbox.val().trim();

		$scope.$apply(startProgress);

		$timeout(function() {
			if (!queryIsEmpty()) {
				runSearch($scope.queryString);
			} else {
				clearSearch();
			}
		});
	}

	function startProgress() {
		$rootScope.searchInProgress = true;
		$scope.$parent.scrollToTop();
	}

	function endProgress() {
		$rootScope.searchInProgress = false;
	}

	function runSearch(query) {
		cleanUpPrevMatches();

		let matchingTracks = null;
		let view = $rootScope.currentView;

		if (view == '#') {
			matchingTracks = searchInAlbumsView(query);
		} else if (view == '#/folders') {
			matchingTracks = searchInFoldersView(query);
		} else if (view == '#/genres') {
			matchingTracks = searchInGenresView(query);
		} else if (view == '#/alltracks') {
			matchingTracks = searchInAllTracksView(query);
		} else if (view == '#/radio') {
			matchingTracks = searchInRadioView(query);
		} else if (view == '#/podcasts') {
			matchingTracks = searchInPodcastsView(query);
		} else if (view == '#/smartlist') {
			matchingTracks = searchInSmartistView(query);
		} else if (view.startsWith('#/playlist/')) {
			matchingTracks = searchInPlaylistView(view.slice('#/playlist/'.length), query);
		} else {
			OC.Notification.showTemporary(gettextCatalog.getString('Search not available in this view'));
			endProgress();
			return;
		}

		$scope.searchResultsOmitted = matchingTracks.truncated;
		$scope.noSearchResults = (matchingTracks.result.length === 0);

		// inform the track-list directive about changed search matches
		$rootScope.$emit('searchMatchedTracks', matchingTracks.result);

		$('#app-view').addClass('searchmode');
		$rootScope.searchMode = true;
		$rootScope.$emit('inViewObserver_visibilityEvent', true);

		endProgress();
	}

	function searchInAlbumsView(query) {
		let matches = libraryService.searchTracksInAlbums(query, MAX_MATCHES);

		// mark track matches and collet the unique parent albums and artists
		let artists = {};
		let albums = {};
		_(matches.result).each(function(track) {
			$('#track-' + track.id).addClass('matched');
			albums[track.album.id] = 1;
			artists[track.album.artist.id] = 1;
		});

		// mark parent artists of the matches
		_(artists).each(function(_value, artistId) {
			$('#artist-' + artistId).addClass('matched');
		});

		// mark parent albums of the matches
		_(albums).each(function(_value, albumId) {
			$('#album-' + albumId).addClass('matched');
		});

		return matches;
	}

	function searchInFoldersView(query) {
		let matches = libraryService.searchTracksInFolders(query, MAX_MATCHES);

		// mark track matches and collect the unique parent folders
		let folders = {};
		_(matches.result).each(function(track) {
			$('#track-' + track.id).addClass('matched');
			folders[track.folder.id] = track.folder;
		});

		// if tree layout is used, then we need to mark the matches in the model and collect all the ancestor folders, too
		if (!$scope.foldersFlatLayout) {
			// for performance reasons, allow showing matches only from a limited number of tree folders
			if (_.size(folders) > MAX_FOLDER_MATCHES_IN_TREE_LAYOUT) {
				folders = _.pick(folders, _.slice(_.keys(folders), 0, MAX_FOLDER_MATCHES_IN_TREE_LAYOUT));
				matches.truncated = true;
			}

			let parents = {};
			_(folders).each(function(folder) {
				folder.matched = true;
			});
			_(folders).each(function(folder) {
				let parent = folder.parent;
				while (parent !== null && !parent.matched) {
					parent.matched = true;
					parents[parent.id] = parent;
					parent = parent.parent;
				}
			});
			_.assign(folders, parents);
			treeFolderMatches = folders; // store for later clean-up
		}
		// in the flat layout, the parent folders are marked using a css class
		else {
			_(folders).each(function(_folder, folderId) {
				$('#folder-' + folderId).addClass('matched');
			});
		}

		return matches;
	}

	function searchInGenresView(query) {
		let matches = libraryService.searchTracksInGenres(query, MAX_MATCHES);

		// mark track matches and collect the unique parent genres
		let genres = {};
		_(matches.result).each(function(track) {
			$('#track-' + track.id).addClass('matched');
			genres[track.genre.id] = 1;
		});

		// mark parent folders of the matches
		_(genres).each(function(_value, genreId) {
			$('#genre-' + genreId).addClass('matched');
		});

		return matches;
	}

	function searchInAllTracksView(query) {
		let matches = libraryService.searchTracks(query, MAX_MATCHES);

		// mark matching tracks and collect unique parent buckets
		let buckets = {};
		_(matches.result).each(function(track) {
			$('#track-' + track.id).addClass('matched');
			buckets[track.bucket.id] = 1;
		});

		// mark parent buckets
		_(buckets).each(function(_value, bucketId) {
			$('#track-bucket-' + bucketId).addClass('matched');
		});

		return matches;
	}

	function searchInRadioView(query) {
		let matches = libraryService.searchRadioStations(query, MAX_MATCHES_IN_PLAYLIST);
		_(matches.result).each(function(station) {
			$('#radio-station-' + station.id).addClass('matched');
		});

		return matches;
	}

	function searchInPodcastsView(query) {
		let matches = libraryService.searchPodcasts(query, MAX_MATCHES);

		// mark episode matches and collect the unique parent channels
		let channels = {};
		_(matches.result).each(function(episode) {
			$('#podcast-episode-' + episode.id).addClass('matched');
			channels[episode.channel.id] = 1;
		});

		// mark parent channels of the matches
		_(channels).each(function(_value, channelId) {
			$('#podcast-channel-' + channelId).addClass('matched');
		});

		// podcasts view has a single ".artist-area" which should be always "matched" i.e. not hidden
		$('.artist-area').addClass('matched');

		return matches;
	}

	function searchInSmartistView(query) {
		let matches = libraryService.searchTracksInSmartlist(query, MAX_MATCHES);
		_(matches.result).each(function(track) {
			$('li[data-track-id=' + track.id + ']').addClass('matched');
		});

		return matches;
	}

	function searchInPlaylistView(playlistId, query) {
		let matches = libraryService.searchTracksInPlaylist(playlistId, query, MAX_MATCHES_IN_PLAYLIST);
		_(matches.result).each(function(track) {
			$('li[data-track-id=' + track.id + ']').addClass('matched');
		});

		return matches;
	}

	function cleanUpPrevMatches() {
		if ($rootScope.currentView === '#/folders' && !$scope.foldersFlatLayout) {
			// folder view with tree layout is a special case
			_(treeFolderMatches).each(folder => {folder.matched = false;});
			treeFolderMatches = {};
			$('.track-list .matched').removeClass('matched');
		} else {
			// any other view
			$('.matched').removeClass('matched');
		}
	}

	function clearSearch() {
		$rootScope.$emit('searchOff');
		$('#app-view').removeClass('searchmode');
		cleanUpPrevMatches();
		$rootScope.searchMode = false;
		$rootScope.$emit('inViewObserver_visibilityEvent', false);
		$scope.searchResultsOmitted = false;
		$scope.noSearchResults = false;
		endProgress();
	}

	$rootScope.$on('deactivateView', function() {
		$rootScope.searchMode = false;
		$scope.searchResultsOmitted = false;
		$scope.noSearchResults = false;
		$rootScope.$emit('searchOff');
		endProgress();
	});
}]);
