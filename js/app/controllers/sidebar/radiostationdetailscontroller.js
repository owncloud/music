/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2021 - 2025
 */


angular.module('Music').controller('RadioStationDetailsController', [
	'$scope', '$rootScope', '$timeout', 'Restangular', 'libraryService',
	function ($scope, $rootScope, $timeout, Restangular, libraryService) {

		$scope.selectedTab = 'general';

		function resetContents() {
			$scope.station = null;
			$scope.stationName = null;
			$scope.streamUrl = null;
			$scope.createdDate = null;
			$scope.updatedDate = null;
			$scope.editing = false;
			$scope.resetLastFmData();
		}
		resetContents();

		$scope.$watch('contentId', function(stationId) {
			if (!$scope.station || stationId != $scope.station.id) {
				resetContents();

				if (stationId === null) {
					$scope.editing = true;
					$timeout(function() {
						$('#radio-name-editor').focus();
					});
				} else {
					const station = libraryService.getRadioStation(stationId);
					$scope.station = station;

					$scope.stationName = $scope.station.name;
					$scope.streamUrl = $scope.station.stream_url;

					// fetch the metadata if not already cached
					if (!station.metadata) {
						Restangular.one('radio', stationId).one('info').get().then(
							function(response) {
								station.metadata = response;
							},
							function(_error) {
								// ignore errors
							}
						);
					}
				}
			}
		});

		$scope.$watch('station.created', function(created) {
			$scope.createdDate = OCA.Music.Utils.formatDateTime(created);
		});

		$scope.$watch('station.updated', function(updated) {
			$scope.updatedDate = OCA.Music.Utils.formatDateTime(updated);
		});

		var prevTitle = null;
		$scope.$watch('station.metadata', function(metadata) {
			if (metadata?.title != prevTitle) {
				prevTitle = metadata?.title;

				$scope.resetLastFmData();
				$scope.lastfmCoverUrl = null;
				if (metadata?.title) {
					// Split artist name and track title assuming the format "artist - track".
					// If there are multiple instances of " - ", the first one is used as separator
					// and the rest are assumed to belong to the track title.
					const matches = metadata.title.match(/^(.+?) - (.+)$/);
					if (matches === null) {
						$scope.setLastfmPlaceholder(metadata.title, null);
					} else {
						updateDetails(matches[2], matches[1]);
					}
				} else {
					$scope.selectedTab = 'general';
				}
			}
		});

		// Update details from external sources like Last.fm
		function updateDetails(songTitle, artistName) {
			$scope.setLastfmPlaceholder(songTitle, artistName);
			Restangular.one('details').get({song: songTitle, artist: artistName}).then(
				(response) => {
					$scope.setLastfmTrackInfo(response.lastfm);
					if (response.lastfm.track?.album?.image) {
						// there are usually many image sizes provided but the last one should be the largest
						const urlFromLastFm = response.lastfm.track.album.image.at(-1)['#text'];
						$scope.lastfmCoverUrl = OC.generateUrl('apps/music/api/cover/external?url={url}', {url: urlFromLastFm});
					}
				},
				(error) => console.error(error)
			);
		}

		// Enter the edit mode
		$scope.startEdit = function(targetEditor) {
			if (!$scope.editing) {
				$scope.editing = true;
				// Move the focus to the clicked input field
				$timeout(function() {
					$(targetEditor).focus();
				});
			}
		};

		// Commit the edited content
		$scope.commitEdit = function() {
			// do not allow committing if the stream URL is empty
			if ($scope.streamUrl.length > 0) {
				const newData = {name: $scope.stationName, streamUrl: $scope.streamUrl};

				if ($scope.station === null) { // creating new
					Restangular.all('radio').post(newData).then(
						function (result) {
							libraryService.addRadioStation(result);
							$scope.$parent.$parent.contentId = result.id;
							$rootScope.$emit('playlistUpdated', 'radio', /*onlyReorder=*/false);
						}
					);
				}
				else {
					// push the change to the server only if the data has actually changed
					if ($scope.stationName !== $scope.station.name || $scope.streamUrl !== $scope.station.stream_url) {
						$scope.station.name = $scope.stationName;
						$scope.station.stream_url = $scope.streamUrl;
						Restangular.one('radio', $scope.station.id).customPUT(newData).then(
							function (result) {
								$scope.station.updated = result.updated;
							}
						);
					}
					libraryService.sortRadioStations();
					$rootScope.$emit('playlistUpdated', 'radio', /*onlyReorder=*/true);
				}
				$scope.editing = false;
			}
		};

		// Rollback any edited content
		$scope.cancelEdit = function() {
			if ($scope.station === null) { // creating new
				$scope.stationName = null;
				$scope.streamUrl = null;
				$rootScope.$emit('hideDetails');
			} else {
				$scope.stationName = $scope.station.name;
				$scope.streamUrl = $scope.station.stream_url;
				$scope.editing = false;
			}
		};
	}
]);
