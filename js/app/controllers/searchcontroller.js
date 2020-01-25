/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2020
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
'$scope', '$rootScope', 'libraryService', 'alphabetIndexingService', '$timeout', '$document', 'gettextCatalog',
function ($scope, $rootScope, libraryService, alphabetIndexingService, $timeout, $document, gettextCatalog) {

	var MAX_TRACK_MATCHES = 1000;
	var MAX_ALBUM_MATCHES = 100;
	var MAX_ARTIST_MATCHES = 30;
	var MAX_FOLDER_MATCHES = 100;

	var searchbox = $('#searchbox');
	$scope.queryString = searchbox.val().trim();

	/** Conduct the search when there is a pause in typing in text */
	var checkQueryChange = _.debounce(function() {
		if ($scope.queryString != searchbox.val().trim()) {
			onEnterSearchString();
		}
	}, 250);
	searchbox.bind('propertychange change keyup input paste', checkQueryChange);

	/** Handle clearing the searchbox. This has to be registered to the parent form
	 *  of the #searchbox element.
	 */
	$('.searchbox').on('reset', function() {
		$scope.queryString = '';
		$scope.$apply(startProgress);
		$timeout(clearSearch);
	});

	/** Catch ctrl+f except when the Settings view is active */
	$document.bind('keydown', function(e) {
		if ($rootScope.currentView !== '#/settings' && e.ctrlKey && e.key === 'f') {
			searchbox.focus();
			return false;
		}
		return true;
	});

	/** Run search when enter pressed within the searchbox */
	searchbox.bind('keydown', function (event) {
		if (event.which === 13) {
			onEnterSearchString();
		}
	});

	function onEnterSearchString() {
		$scope.queryString = searchbox.val().trim();

		$scope.$apply(startProgress);

		$timeout(function() {
			if ($scope.queryString.length > 0) {
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
		// reset previous matches
		$('.matched').removeClass('matched');

		var matchingTracks = null;
		var view = $rootScope.currentView;

		if (view == '#') {
			matchingTracks = searchInAlbumsView(query);
		} else if (view == '#/folders') {
			matchingTracks = searchInFoldersView(query);
		} else if (view == '#/alltracks') {
			matchingTracks = searchInAllTracksView(query);
		} else if (view.startsWith('#/playlist/')) {
			matchingTracks = searchInPlaylistView(view.substr('#/playlist/'.length), query);
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

		$rootScope.$emit('inViewObserver_visibilityEvent', true);

		endProgress();
	}

	function searchInAlbumsView(query) {
		var trackResults = libraryService.searchTracks(query, MAX_TRACK_MATCHES);
		var albumResults = libraryService.searchAlbums(query, MAX_ALBUM_MATCHES);
		var artistResults = libraryService.searchArtists(query, MAX_ARTIST_MATCHES);

		// add children of matching albums/artists (if have not hit the maximum amount)
		if (!albumResults.truncated) {
			albumResults = OC_Music_Utils.limitedUnion(
					MAX_ALBUM_MATCHES,
					albumResults.result,
					_.flatten(_.pluck(artistResults.result, 'albums'))
			);
		}
		if (!trackResults.truncated) {
			trackResults = OC_Music_Utils.limitedUnion(
					MAX_TRACK_MATCHES,
					trackResults.result,
					_.flatten(_.pluck(albumResults.result, 'tracks'))
			);
		}

		// mark track matches
		_(trackResults.result).each(function(track) {
			$('#track-' + track.id).addClass('matched');
			$('#album-' + track.album.id).addClass('matched').parent().addClass('matched');
		});

		// mark album matches
		_(albumResults.result).each(function(album) {
			$('#album-' + album.id).addClass('matched').parent().addClass('matched');
		});

		// mark artist matches
		_(artistResults.result).each(function(artist) {
			$('#artist-' + artist.id).addClass('matched');
		});

		return {
			result: trackResults.result,
			truncated: trackResults.truncated || albumResults.truncated || artistResults.truncated
		};
	}

	function searchInFoldersView(query) {
		var trackResults = libraryService.searchTracks(query, MAX_TRACK_MATCHES);
		var folderResults = libraryService.searchFolders(query, MAX_FOLDER_MATCHES);

		// add children of matching folders
		if (!trackResults.truncated) {
			trackResults = OC_Music_Utils.limitedUnion(
					MAX_TRACK_MATCHES,
					trackResults.result,
					_.chain(folderResults.result).pluck('tracks').flatten().pluck('track').value()
			);
		}

		// mark track matches
		_(trackResults.result).each(function(track) {
			$('#track-' + track.id).addClass('matched');
			$('#folder-' + track.folder.id).addClass('matched');
		});

		// mark folder matches
		_(folderResults.result).each(function(folder) {
			$('#folder-' + folder.id).addClass('matched');
		});

		return {
			result: trackResults.result,
			truncated: trackResults.truncated || folderResults.truncated
		};
	}

	function searchInAllTracksView(query) {
		var trackResults = libraryService.searchTracks(query, MAX_TRACK_MATCHES);
		_(trackResults.result).each(function(track) {
			$('#track-' + track.id).addClass('matched');
			var indexChar = alphabetIndexingService.indexCharForTitle(track.artistName);
			if (indexChar == '#') {
				indexChar = '\\#';
			}
			$('.track-bucket-' + indexChar).addClass('matched');
		});
		return trackResults;
	}

	function searchInPlaylistView(playlistId, query) {
		var trackResults = libraryService.searchTracksInPlaylist(playlistId, query, MAX_TRACK_MATCHES);
		_(trackResults.result).each(function(track) {
			$('li[data-track-id=' + track.id + ']').addClass('matched');
		});

		return trackResults;
	}

	function clearSearch() {
		$rootScope.$emit('searchOff');
		$('#app-view').removeClass('searchmode');
		$('.matched').removeClass('matched');
		$rootScope.$emit('inViewObserver_visibilityEvent', false);
		$scope.searchResultsOmitted = false;
		$scope.noSearchResults = false;
		endProgress();
	}

	$rootScope.$on('deactivateView', function() {
		$scope.searchResultsOmitted = false;
		$scope.noSearchResults = false;
		$rootScope.$emit('searchOff');
		endProgress();
	});
}]);
