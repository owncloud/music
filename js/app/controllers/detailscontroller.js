/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2018
 */


angular.module('Music').controller('DetailsController', [
	'$rootScope', '$scope', 'Restangular', '$timeout', 'libraryService',
	function ($rootScope, $scope, Restangular, $timeout, libraryService) {

		var currentTrack = null;

		function getFileId(trackId) {
			var files = libraryService.getTrack(trackId).files;
			return files[Object.keys(files)[0]];
		}

		$rootScope.$on('showDetails', function(event, trackId) {
			OC.Apps.showAppSidebar();

			if (trackId != currentTrack) {
				currentTrack = trackId;
				$scope.details = null;

				var albumart = $('#app-sidebar .albumart');
				albumart.css('background-image', '').css('height', '0');

				var fileId = getFileId(trackId);
				$('#path').attr('href', OC.generateUrl('/f/' + fileId));

				Restangular.one('file', fileId).one('details').get().then(function(result) {
					if (result.tags.picture) {
						albumart.css('background-image', 'url("' + result.tags.picture + '")');
						albumart.css('height', ''); // remove the inline height and use the one from the css file
					}

					delete result.tags.picture;
					$scope.details = result;
				});
			}
		});

		$rootScope.$on('hideDetails', function() {
			OC.Apps.hideAppSidebar();
		});

		$scope.formatDetailName = function(rawName) {
			if (rawName === 'band') {
				return 'album artist';
			} else if (rawName === 'unsynchronised_lyric') {
				return 'lyrics';
			} else {
				return rawName.replace(/_/g, ' ');
			}
		};
	}
]);
