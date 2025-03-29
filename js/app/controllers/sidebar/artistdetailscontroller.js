/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2020 - 2025
 */


angular.module('Music').controller('ArtistDetailsController', [
	'$rootScope', '$scope', 'Restangular', 'gettextCatalog', 'libraryService', 'playQueueService',
	function ($rootScope, $scope, Restangular, gettextCatalog, libraryService, playQueueService) {

		function resetContents() {
			$scope.artist = null;
			$scope.featuredAlbums = null;
			$scope.artistTracks = null;
			$scope.artistAlbumTrackCount = 0;
			$scope.loading = true;
			$scope.artAvailable = false;
			$scope.lastfmInfo = null;
			$scope.artistBio = null;
			$scope.artistTags = null;
			$scope.mbid = null;
			$scope.similarArtistsInLib = null;
			$scope.similarArtistsNotInLib = null;
			$scope.allSimilarShown = false;
			$scope.allSimilarLoading = false;
		}
		resetContents();

		function showDetails(artistId) {
			if (!$scope.artist || artistId != $scope.artist.id) {
				resetContents();

				$scope.artist = libraryService.getArtist(artistId);
				$scope.artistAlbumTrackCount = _($scope.artist.albums).map('tracks').flatten().size();
				$scope.artistTracks = libraryService.findTracksByArtist(artistId);
				$scope.featuredAlbums = _($scope.artistTracks).map('album').uniq().difference($scope.artist.albums).value();

				if ($scope.selectedTab == 'tracks' && $scope.artistTracks.length == 0) {
					$scope.selectedTab = 'info';
				}

				let art = $('#app-sidebar .albumart');
				art.css('background-image', '');

				// Because of the asynchronous nature of teh REST queries, it is possible that the
				// current artist has already changed again by the time we get the result. If that has
				// happened, then the result should be ignored.
				Restangular.one('artists', artistId).one('cover').get().then(
					function(_result) {
						if ($scope.artist && $scope.artist.id == artistId) {
							$scope.artAvailable = true;
							$scope.loading = false;

							let url = OC.generateUrl('apps/music/api/artists/') + artistId + '/cover?originalSize=true';
							art.css('background-image', 'url("' + url + '")');
						}
					},
					function(_error) {
						// error handling
						if ($scope.artist && $scope.artist.id == artistId) {
							$scope.artAvailable = false;
							$scope.loading = false;
							$scope.noImageHint = gettextCatalog.getString(
								'Upload image named "{{name}}" to anywhere within your library path to see it here.',
								{ name: $scope.artist.name.replaceAll(/[<>:"/\\|?*]/g, '_') + '.*' }
							);
						}
					}
				);

				Restangular.one('artists', artistId).one('details').get().then(
					function(result) {
						if ($scope.artist && $scope.artist.id == artistId) {
							$scope.lastfmInfo = result;

							if ('artist' in result) {
								$scope.artistBio = result.artist.bio.content || result.artist.bio.summary;
								// modify all links in the biography so that they will open to a new tab
								$scope.artistBio = $scope.artistBio.replace(/<a href=/g, '<a target="_blank" href=');
								$scope.artistTags = $scope.formatLastfmTags(result.artist.tags.tag);

								setSimilarArtists(result.artist.similar.artist);

								const mbid = result.artist.mbid;
								if (mbid) {
									$scope.mbid = `<a target="_blank" href="https://musicbrainz.org/artist/${mbid}">${mbid}</a>`;
								}
							}

							$scope.$parent.adjustFixedPositions();
						}
					}
				);
			}
		}

		$scope.getAlbumData = function(listItem, index, _scope) {
			return {
				title: listItem.name,
				title2: listItem.year,
				tooltip: listItem.name,
				number: index + 1,
				id: listItem.id,
				art: listItem
			};
		};

		$scope.getAlbumDraggable = function(albumId) {
			return { album: albumId };
		};

		$scope.onAlbumClick = function(albumId) {
			// TODO: play/pause if currently playing album clicked?
			const album = libraryService.getAlbum(albumId);
			playTracks('album-' + album.id, album.tracks);
		};

		$scope.getTrackData = function(listItem, index, _scope) {
			return {
				title: listItem.title,
				title2: listItem.album.name + (listItem.album.year ? ` (${listItem.album.year})` : ''),
				tooltip: listItem.title,
				tooltip2: listItem.album.name + (listItem.album.year ? ` (${listItem.album.year})` : ''),
				number: index + 1,
				id: listItem.id,
				art: listItem.album
			};
		};

		$scope.getTrackDraggable = function(trackId) {
			return { track: trackId };
		};

		$scope.onTrackClick = function(trackId, index) {
			const currentTrack = $scope.$parent.currentTrack;
			if (currentTrack?.id === trackId && currentTrack?.type == 'song') {
				// play/pause if currently playing list item clicked
				playQueueService.publish('togglePlayback');
			} else {
				// on any other list item, start playing the list from this item
				playTracks('artist-tracks-' + $scope.artist.id, $scope.artistTracks, index);
			}
		};

		function playTracks(listId, tracks, startIndex /*optional*/) {
			let playlist = _.map(tracks, function(track) {
				return { track: track };
			});
			playQueueService.setPlaylist(listId, playlist, startIndex);
			playQueueService.publish('play');
		}

		$scope.onShowAllSimilar = function() {
			$scope.allSimilarShown = true;
			$scope.allSimilarLoading = true;
			Restangular.one('artists', $scope.artist.id).one('similar').get().then(
				function(result) {
					setSimilarArtists(result);
					$scope.allSimilarLoading = false;
					$scope.$parent.adjustFixedPositions();
				}
			);
		};

		function setSimilarArtists(artists) {
			// similar artists are divided to those within the library and the rest
			let artistIsInLib = function(artist) {
				return 'id' in artist && artist.id !== null;
			};
			$scope.similarArtistsInLib = _.filter(artists, artistIsInLib);
			$scope.similarArtistsNotInLib = $scope.formatLinkList( 
				_.reject(artists, artistIsInLib)
			);
		}

		$scope.onClickKnownArtist = function(id) {
			$rootScope.$emit('showArtistDetails', id);
		};

		$scope.$watch('contentId', function(newId) {
			if (newId !== null) {
				showDetails(newId);
			} else {
				resetContents();
			}
		});

		$scope.$watch('selectedTab', $scope.$parent.adjustFixedPositions);
	}
]);
