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

		$scope.artist = null;
		$scope.loading = true;
		$scope.artAvailable = false;
		$scope.lastfmInfo = null;

		function showDetails(artistId) {
			if (!$scope.artist || artistId != $scope.artist.id) {
				$scope.loading = true;
				$scope.artAvailable = false;
				$scope.lastfmInfo = null;

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
					}
				);
			}
		}

		$scope.$watch('contentId', showDetails);
	}
]);
