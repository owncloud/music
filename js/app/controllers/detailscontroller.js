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

		function toArray(obj) {
			return _.map(obj, function(val, key) {
				return {key: key, value: val};
			});
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
					$scope.details = {
							path: result.path,
							tags: toArray(result.tags),
							fileinfo: toArray(result.fileinfo)
					};
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

		$scope.tagRank = function(tag) {
			switch (tag.key) {
			case 'title':					return 1;
			case 'artist':					return 2;
			case 'album':					return 3;
			case 'band':					return 4;
			case 'composer':				return 5;
			case 'part_of_a_set':			return 6;
			case 'track_number':			return 7;
			case 'comment':					return 100;
			case 'unsynchronised_lyric':	return 101;
			default:						return 10;
			}
		};
	}
]);
