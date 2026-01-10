/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2020 - 2026
 */


angular.module('Music').controller('AlbumDetailsController', [
	'$rootScope', '$scope', '$timeout', 'Restangular', 'gettextCatalog', 'libraryService', 'playQueueService',
	function ($rootScope, $scope, $timeout, Restangular, gettextCatalog, libraryService, playQueueService) {

		function resetContents() {
			$scope.album = null;
			$scope.totalLength = null;
			$scope.lastfmInfo = null;
			$scope.albumInfo = null;
			$scope.albumTags = null;
			$scope.mbid = null;
			$scope.featuredArtists = null;
			setImageUrl('');
		}
		resetContents();

		function setImageUrl(url) {
			if (url) {
				url = 'url("' + url + '")';
			}
			$('#album-details > .albumart').css('background-image', url);
		}

		function showDetails(albumId) {
			if (!$scope.album || albumId != $scope.album.id) {
				resetContents();

				$timeout(() => {
					$scope.album = libraryService.getAlbum(albumId);
					$scope.totalLength = _.reduce($scope.album.tracks, function(sum, track) {
						return sum + track.length;
					}, 0);
	
					if ($scope.album.cover) {
						let url = OC.generateUrl('apps/music/api/albums/') + albumId + '/cover?originalSize=true';
						setImageUrl(url);
					}

					$scope.featuredArtists = _($scope.album.tracks).map('artist').uniq().sortBy('name').value();

					// Because of the asynchronous nature of teh REST queries, it is possible that the
					// current album has already changed again by the time we get the result. If that has
					// happened, then the result should be ignored.
					Restangular.one('albums', albumId).one('details').get().then(
						function(result) {
							if ($scope.album && $scope.album.id == albumId) {
								$scope.lastfmInfo = result;

								if ('album' in result) {
									if ('wiki' in result.album) {
										$scope.albumInfo = result.album.wiki.content || result.album.wiki.summary;
										// modify all links in the info so that they will open to a new tab
										$scope.albumInfo = $scope.albumInfo.replace(/<a href=/g, '<a target="_blank" href=');
									}
									else {
										let linkText = gettextCatalog.getString('See the album on Last.fm');
										$scope.albumInfo = `<a target="_blank" href="${result.album.url}">${linkText}</a>`;
									}

									if ('tags' in result.album) {
										$scope.albumTags = $scope.formatLastfmTags(result.album.tags.tag);
									}

									const mbid = result.album.mbid;
									if (mbid) {
										$scope.mbid = `<a target="_blank" href="https://musicbrainz.org/release/${mbid}">${mbid}</a>`;
									}

									if (!$scope.album.cover && 'image' in result.album) {
										// there are usually many image sizes provided but the last one should be the largest
										const lastfmImageUrl = result.album.image.at(-1)['#text'];
										const relayImageUrl = OC.generateUrl('apps/music/api/cover/external?url={url}', {url: lastfmImageUrl});
										setImageUrl(relayImageUrl);
									}
								}

								$scope.$parent.adjustFixedPositions();
							}
						}
					);
				});
			}
		}

		$scope.getTrackData = function(listItem, _index, _scope) {
			return {
				title: listItem.title,
				title2: listItem.artist.name,
				tooltip: listItem.title,
				tooltip2: listItem.artist.name,
				number: listItem.formattedNumber,
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
				playTracks('album-' + $scope.album.id, $scope.album.tracks, index);
			}
		};

		$scope.getArtistData = function(listItem, index, _scope) {
			return {
				title: listItem.name,
				tooltip: listItem.name,
				number: index + 1,
				id: listItem.id,
				art: listItem
			};
		};

		$scope.getArtistDraggable = function(artistId) {
			return { artist: artistId };
		};

		$scope.onArtistClick = function(artistId, _index) {
			const tracks = libraryService.findTracksByArtist(artistId);
			playTracks('featured-artist-' + artistId, tracks);
		};

		function playTracks(listId, tracks, startIndex /*optional*/) {
			let playlist = _.map(tracks, (track) => {
				return { track: track };
			});
			playQueueService.setPlaylist(listId, playlist, startIndex);
			playQueueService.publish('play');
		}

		$scope.$watch('contentId', function(newId) {
			if (newId !== null) {
				showDetails(newId);
			} else {
				resetContents();
			}
		});

		$scope.$watch('selectedTab', $scope.$parent.adjustFixedPositions);

		$scope.showArtist = function() {
			$rootScope.$emit('showArtistDetails', $scope.album.artist.id);
		};
	}
]);
