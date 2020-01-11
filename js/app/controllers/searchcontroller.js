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
 * The controller is attached to the div#searchresults, but this element is always hidden.
 * The element's only purpose is, that having it present makes the ownCloud/Nextcloud core
 * show the searchbox element within the header.
 * 
 * When the search query is written to the searchbox, the core automatically sends the query
 * to the backend. However, we disregard any results from the backend and conduct the search
 * on our own, on the frontend.
 */
angular.module('Music').controller('SearchController', [
'$scope', '$rootScope', 'libraryService', '$timeout', '$document', 'gettextCatalog',
function ($scope, $rootScope, libraryService, $timeout, $document, gettextCatalog) {

	var searchbox = $('#searchbox');
	var queryString = searchbox.val().trim();

	/** Conduct the search when there is a pause in typing in text */
	var checkQueryChange = _.debounce(function() {
		if (queryString != searchbox.val().trim()) {
			$scope.$apply(onEnterSearchString);
		}
	}, 250);
	searchbox.bind('propertychange change keyup input paste', checkQueryChange);

	/** Handle clearing the searchbox. This has to be registered to the parent form
	 *  of the #searchbox element.
	 */
	$('.searchbox').on('reset', function() {
		queryString = '';
		clearSearch();
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
		queryString = searchbox.val().trim();

		if (queryString.length > 0) {
			runSearch();
		} else {
			clearSearch();
		}
	}

	function runSearch() {
		// reset previous matches
		$('.matched').removeClass('matched');

		var matchingTracks = [];
		var view = $rootScope.currentView;

		if (view == '#') {
			matchingTracks = searchInAlbumsView();
		} else if (view == '#/folders') {
			matchingTracks = searchInFoldersView();
		} else if (view == '#/alltracks') {
			matchingTracks = searchInAllTracksView();
		} else if (view.startsWith('#/playlist/')) {
			matchingTracks = searchInPlaylistView();
		} else {
			OC.Notification.showTemporary(gettextCatalog.getString('Search not available in this view'));
		}

		// inform the track-list directive about changed search matches
		$rootScope.$emit('searchMatchedTracks', matchingTracks);

		$('#app-view').addClass('searchmode');

		$rootScope.$emit('inViewObserverReInit');
	}

	function searchInAlbumsView() {
		var matchingTracks = libraryService.searchTracks(queryString);
		var matchingAlbums = libraryService.searchAlbums(queryString);
		var matchingArtists = libraryService.searchArtists(queryString);

		// add children of matching albums/artists
		matchingAlbums = _.union(
				matchingAlbums,
				_.flatten(_.pluck(matchingArtists, 'albums'))
		);
		matchingTracks = _.union(
				matchingTracks,
				_.flatten(_.pluck(matchingAlbums, 'tracks'))
		);

		// mark track matches
		_(matchingTracks).each(function(track) {
			$('#track-' + track.id).addClass('matched');
			$('#album-' + track.albumId).addClass('matched').parent().addClass('matched');
		});

		// mark album matches
		_(matchingAlbums).each(function(album) {
			$('#album-' + album.id).addClass('matched').parent().addClass('matched');
		});

		// mark artist matches
		_(matchingArtists).each(function(artist) {
			$('#artist-' + artist.id).addClass('matched');
		});

		return matchingTracks;
	}

	function searchInFoldersView() {
		var matchingTracks = libraryService.searchTracks(queryString);
		var matchingFolders = libraryService.searchFolders(queryString);

		// add children of matching folders
		matchingTracks = _.union(
				matchingTracks,
				_.chain(matchingFolders).pluck('tracks').flatten().pluck('track').value()
		);

		// mark track matches
		_(matchingTracks).each(function(track) {
			$('#track-' + track.id).addClass('matched');
			$('#folder-' + track.folderId).addClass('matched');
		});

		// mark folder matches
		_(matchingFolders).each(function(folder) {
			$('#folder-' + folder.id).addClass('matched');
		});

		return matchingTracks;
	}

	function searchInAllTracksView() {
		var matchingTracks = libraryService.searchTracks(queryString);
		_(matchingTracks).each(function(track) {
			$('#track-' + track.id).addClass('matched');
		});
		return matchingTracks;
	}

	function searchInPlaylistView() {
		var matchingTracks = libraryService.searchTracks(queryString);
		_(matchingTracks).each(function(track) {
			$('li[data-track-id=' + track.id + ']').addClass('matched');
		});

		// return no tracks because this view uses no track-list directives
		return [];
	}

	function clearSearch() {
		$rootScope.$emit('searchOff');
		$('#app-view').removeClass('searchmode');
		$('.matched').removeClass('matched');
		$rootScope.$emit('inViewObserverReInit');
	}
}]);
