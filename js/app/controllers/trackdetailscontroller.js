/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2018 - 2020
 */


angular.module('Music').controller('TrackDetailsController', [
	'$rootScope', '$scope', 'Restangular', 'libraryService',
	function ($rootScope, $scope, Restangular, libraryService) {

		$scope.selectedTab = 'general';

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

		function isFloat(n) {
			return typeof n === "number" && Math.floor(n) !== n;
		}

		function showDetails(trackId) {
			$scope.$parent.adjustFixedPositions();
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

					result.tags = toArray(result.tags);
					result.fileinfo = toArray(result.fileinfo);
					$scope.details = result;

					if ($scope.selectedTab == 'lyrics' && !$scope.details.lyrics) {
						// 'lyrics' tab is selected but not available => select 'general' tab
						$scope.selectedTab = 'general';
					}

					$scope.$parent.adjustFixedPositions();
				});
			}
		}

		$scope.$watch('contentId', showDetails);

		$rootScope.$on('playerProgress', function(event, time) {
			// check if we are viewing time-synced lyrics of the currently playing track
			if ($scope.details && $scope.details.lyrics && $scope.details.lyrics.synced
					&& $scope.$parent.currentTrack.id == currentTrack) {
				// Check if the highlighted row needs to change. First find the last row
				// which has been already reached by the playback.
				var allRows = $("#app-sidebar .lyrics");
				for (var i = allRows.length - 1; i >= 0; --i) {
					var curRow = $(allRows[i]);
					if (Number(curRow.attr('data-timestamp')) <= time) {
						if (!curRow.hasClass('highlight')) {
							// highlight actually needs to move
							allRows.removeClass('highlight');
							curRow.addClass('highlight');
						}
						break;
					}
				}
			}
		});

		$scope.$watch('selectedTab', $scope.$parent.adjustFixedPositions);

		$scope.formatDetailValue = function(value) {
			if (isFloat(value)) {
				// limit the number of shown digits on floating point numbers
				return Number(value.toPrecision(6));
			} else if (_.isString(value)){
				// convert \r\n -> \n because IE9 prints two new-lines on the former
				return value.replace(/\r\n/g, '\n');
			} else {
				return value;
			}
		};

		$scope.formatDetailName = function(rawName) {
			switch (rawName) {
			case 'band':			return 'album artist';
			case 'albumartist':		return 'album artist';
			case 'tracktotal':		return 'total tracks';
			case 'totaltracks':		return 'total tracks';
			case 'part_of_a_set':	return 'disc number';
			case 'discnumber':		return 'disc number';
			case 'dataformat':		return 'format';
			case 'channelmode':		return 'channel mode';
			default:				return rawName.replace(/_/g, ' ').toLowerCase();
			}
		};

		$scope.tagRank = function(tag) {
			switch (tag.key) {
			case 'title':			return 1;
			case 'artist':			return 2;
			case 'album':			return 3;
			case 'albumartist':		return 4;
			case 'album_artist':	return 4;
			case 'band':			return 4;
			case 'composer':		return 5;
			case 'part_of_a_set':	return 6;
			case 'discnumber':		return 6;
			case 'disc_number':		return 6;
			case 'track_number':	return 7;
			case 'tracknumber':		return 7;
			case 'track':			return 7;
			case 'totaltracks':		return 8;
			case 'tracktotal':		return 8;
			case 'comment':			return 100;
			default:				return 10;
			}
		};
	}
]);
