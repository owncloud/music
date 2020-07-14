/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2020
 */


angular.module('Music').controller('ArtistDetailsController', [
	'$rootScope', '$scope', 'Restangular', 'gettextCatalog', 'libraryService',
	function ($rootScope, $scope, Restangular, gettextCatalog, libraryService) {

		function resetContents() {
			$scope.artist = null;
			$scope.artistAlbumTrackCount = 0;
			$scope.artistTrackCount = 0;
			$scope.loading = true;
			$scope.artAvailable = false;
			$scope.lastfmInfo = null;
			$scope.artistBio = null;
			$scope.artistTags = null;
			$scope.similarArtists = null;
		}
		resetContents();

		function showDetails(artistId) {
			if (!$scope.artist || artistId != $scope.artist.id) {
				resetContents();

				$scope.artist = libraryService.getArtist(artistId);
				$scope.artistAlbumTrackCount = _.chain($scope.artist.albums).pluck('tracks').flatten().value().length;
				$scope.artistTrackCount = libraryService.findTracksByArtist(artistId).length;

				var art = $('#app-sidebar .albumart');
				art.css('background-image', '');

				Restangular.one('artist', artistId).one('cover').get().then(
					function(result) {
						$scope.artAvailable = true;
						$scope.loading = false;

						var url = OC.generateUrl('apps/music/api/artist/') + artistId + '/cover?originalSize=true';
						art.css('background-image', 'url("' + url + '")');
					},
					function(result) {
						// error handling
						$scope.artAvailable = false;
						$scope.loading = false;
						$scope.noImageHint = gettextCatalog.getString(
							'Upload image named "{{name}}" to anywhere within your library path to see it here.',
							{ name: $scope.artist.name + '.*' }
						);
					}
				);

				Restangular.one('artist', artistId).one('details').get().then(
					function(result) {
						$scope.lastfmInfo = result;
						$scope.artistBio = result.artist.bio.content || result.artist.bio.summary;
						// modify all links in the biography so that they will open to a new tab
						$scope.artistBio = $scope.artistBio.replace(/<a href=/g, '<a target="_blank" href=');

						$scope.artistTags = formatTags(result.artist.tags.tag);
						$scope.similarArtists = formatLinkList(result.artist.similar.artist);

						$scope.$parent.adjustFixedPositions();
					}
				);
			}
		}

		function formatTags(tags) {
			// Filter out the tag "seen live" because it is intended to be used on Last.fm for the feature
			// which allows filtering based on the tags set by the user herself. As a global tag, it makes
			// no sense because almost all artists have been seen live by someone.
			tags = _.reject(tags, {name: 'seen live'});
			return formatLinkList(tags);
		}

		function formatLinkList(linkArray) {
			htmlLinks = _.map(linkArray, function(item) {
				return '<a href="' + item.url + '" target="_blank">' + item.name + '</a>';
			});
			return htmlLinks.join(', ');
		}

		$scope.$watch('contentId', showDetails);
	}
]);
