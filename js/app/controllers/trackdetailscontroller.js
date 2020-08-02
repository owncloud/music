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
	'$rootScope', '$scope', 'Restangular', 'gettextCatalog', 'libraryService',
	function ($rootScope, $scope, Restangular, gettextCatalog, libraryService) {

		$scope.selectedTab = 'general';

		var currentTrack = null;

		function getFileId() {
			var files = currentTrack.files;
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
			if (!currentTrack || trackId != currentTrack.id) {
				currentTrack = libraryService.getTrack(trackId);
				$scope.details = null;
				$scope.lastfmInfo = null;
				$scope.lastfmTags = null;

				var albumart = $('#app-sidebar .albumart');
				albumart.css('background-image', '').css('height', '0');

				var fileId = getFileId();
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

					if (($scope.selectedTab == 'lyrics' && !result.lyrics)
							|| ($scope.selectedTab == 'lastfm' && (!result.lastfm || !result.lastfm.track))) {
						// selected tab is not available on this track => select 'general' tab
						$scope.selectedTab = 'general';
					}

					if (result.lastfm) {
						setLastfmInfo(result.lastfm);
					}

					$scope.$parent.adjustFixedPositions();
				});
			}
		}

		function setLastfmInfo(data) {
			if ('track' in data) {
				if ('wiki' in data.track) {
					$scope.lastfmInfo = data.track.wiki.content || data.track.wiki.summary;
					// modify all links in the info so that they will open to a new tab
					$scope.lastfmInfo = $scope.lastfmInfo.replace(/<a href=/g, '<a target="_blank" href=');
				}
				else {
					var linkText = gettextCatalog.getString('See the track on Last.fm');
					$scope.lastfmInfo = '<a target="_blank" href="' + data.track.url + '">' + linkText +'</a>';
				}

				if ('toptags' in data.track) {
					$scope.lastfmTags = formatTags(data.track.toptags.tag);
				}
			}
		}

		function formatTags(linkArray) {
			htmlLinks = _.map(linkArray, function(item) {
				return '<a href="' + item.url + '" target="_blank">' + item.name + '</a>';
			});
			return htmlLinks.join(', ');
		}

		$scope.$watch('contentId', showDetails);

		$rootScope.$on('playerProgress', function(event, time) {
			// check if we are viewing time-synced lyrics of the currently playing track
			if ($scope.details && $scope.details.lyrics && $scope.details.lyrics.synced
					&& $scope.$parent.currentTrack.id == currentTrack.id) {
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

		$scope.tagHasDetails = function(tag) {
			switch (tag.key) {
			case 'artist':			return currentTrack.artistName == tag.value;
			case 'album':			return currentTrack.album.name == tag.value;
			case 'albumartist':		// fall through
			case 'album_artist':	// fall through
			case 'band':			return currentTrack.album.artist.name == tag.value;
			default:				return false;
			}
		};

		$scope.showTagDetails = function(tag) {
			switch (tag.key) {
			case 'artist':
				$rootScope.$emit('showArtistDetails', currentTrack.artistId);
				break;
			case 'album':
				$rootScope.$emit('showAlbumDetails', currentTrack.album.id);
				break;
			case 'albumartist':		// fall through
			case 'album_artist':	// fall through
			case 'band':
				$rootScope.$emit('showArtistDetails', currentTrack.album.artist.id);
				break;
			default:
				// nothing
			}
		};
	}
]);
