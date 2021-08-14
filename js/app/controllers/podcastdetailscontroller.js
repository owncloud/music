/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2021
 */


angular.module('Music').controller('PodcastDetailsController', [
	'$rootScope', '$scope', 'Restangular', 'gettextCatalog', 'libraryService',
	function ($rootScope, $scope, Restangular, gettextCatalog, libraryService) {

		$scope.details = null;
		$scope.entity = null;

		function formatTimestamp(timestamp) {
			if (!timestamp) {
				return null;
			} else {
				var date = new Date(timestamp + 'Z');
				return date.toLocaleString();
			}
		}

		const patternLooksLikeHtml = /<\/?[a-z][\s\S]*>/i;
		const patternExtractUrl = /((?:http|ftp|https):\/\/([\w_-]+(?:(?:\.[\w_-]+)+))([\w.,@?^=%&:/~+#-]*[\w@?^=%&/~+#-])?)/ig; // source: https://stackoverflow.com/a/6041965/4348850
		const patternLinkStart = /<a href=/ig;
		const patternNewline = /(?:\r\n|\r|\n)/g;
		function formatDescription(rawValue) {
			var desc = rawValue;

			// Descriptions from the feeds are sometimes HTML-formatted and sometimes they are not. Our template 
			// always renders them as HTML but this has the side-effect of removing any newline characters.
			// Solution: If it doesn't look like HTML-formatted, then substitue any newline with <br/>.
			if (!patternLooksLikeHtml.test(rawValue)) {
				desc = desc.replaceAll(patternNewline, '\n<br>');

				// also, make anything looking like an URL to be a link
				desc = desc.replaceAll(patternExtractUrl, '<a target="_blank" href="$1">$1</a>');
			} else {
				// Seems to be HTML already. Just ensure that all links open to a new tab.
				desc = desc.replaceAll(patternLinkStart, '<a target="_blank" href=');
			}

			return desc;
		}

		function showDetails() {
			if ($scope.contentType && $scope.contentId) {
				$scope.details = null;
				$scope.entity = null;

				var albumart = $('#app-sidebar .albumart');
				albumart.css('background-image', '').css('height', '0');

				var restPath = 'podcasts';
				if ($scope.contentType == 'podcastChannel') {
					$scope.entity = libraryService.getPodcastChannel($scope.contentId);
				} else {
					$scope.entity = libraryService.getPodcastEpisode($scope.contentId);
					restPath += '/episodes';
				}

				Restangular.one(restPath, $scope.contentId).one('details').get().then(function(result) {
					if (result.image === undefined && $scope.entity.channel !== undefined) {
						result.image = $scope.entity.channel.image;
					}

					if (result.image) {
						albumart.css('background-image', 'url("' + result.image + '")');
						albumart.css('height', ''); // remove the inline height and use the one from the css file
					}

					$scope.details = result.plain();

					$scope.$parent.adjustFixedPositions();
				});
			}
		}

		$scope.$watch('contentId', showDetails);

		$scope.keyShown = function(key, value) {
			return value !== null && value !== '' && key !== 'id' && key !== 'image';
		};

		$scope.formatKey = function(rawName) {
			switch (rawName) {
			case 'channel_id':	return 'channel';
			default:			return rawName.replace(/_/g, ' ').toLowerCase();
			}
		};

		$scope.formatValue = function(key, value) {
			switch (key) {
			case 'channel_id':		return libraryService.getPodcastChannel(value).title;
			case 'duration':		return OCA.Music.Utils.formatPlayTime(value);
			case 'size':			return OCA.Music.Utils.formatFileSize(value);
			case 'bit_rate':		return OCA.Music.Utils.formatBitrate(value);
			case 'published':		// fall through
			case 'update_checked':	return formatTimestamp(value);
			case 'link_url':		// fall through
			case 'rss_url':			// fall through
			case 'stream_url':		return `<a href="${value}" target="_blank">${value}</a>`;
			case 'description':		return formatDescription(value);
			default:				return value;
			}
		};

		$scope.keyMayCollapse = function(key) {
			return (key === 'description');
		}

		$scope.keyHasDetails = function(key) {
			return (key === 'channel_id');
		};

		$scope.showKeyDetails = function(key, value) {
			if (key === 'channel_id') {
				$rootScope.$emit('showPodcastChannelDetails', value);
			}
		};
	}
]);
