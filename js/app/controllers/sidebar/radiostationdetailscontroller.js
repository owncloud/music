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

					// Always fetch the metadata from the server when the viewed station changes.
					// With this, we get the up-to-date "Now playing" info even for stations
					// which are not currently playing. The periodic update happens only for the
					// playing station (in playerController).
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
		});

		$scope.$watch('station.created', function(created) {
			$scope.createdDate = OCA.Music.Utils.formatDateTime(created);
		});

		$scope.$watch('station.updated', function(updated) {
			$scope.updatedDate = OCA.Music.Utils.formatDateTime(updated);
		});

		$scope.$watch('station.metadata', function(metadata) {
			$scope.resetLastFmData();
			$scope.lastfmCoverUrl = null;

			if (metadata?.title == null) {
				$scope.selectedTab = 'general';
			} else {
				// Split artist name and track title assuming the format "artist - track".
				// If there are multiple instances of " - ", the first one is used as separator
				// and the rest are assumed to belong to the track title.
				const matches = metadata.title.match(/^(.+?) - (.+)$/);
				if (matches === null) {
					$scope.setLastfmPlaceholder(metadata.title, null);
				} else {
					const [, artistName, songTitle] = matches;
					$scope.setLastfmPlaceholder(songTitle, artistName);
					if (metadata.title != $scope.station.nowPlaying?.title || $scope.station.nowPlaying?.lastfm == null) {
						$scope.station.nowPlaying = {title: metadata?.title, lastfm: null};
						updateDetails($scope.station, songTitle, artistName);
					} else {
						// previously fetched Last.fm data is still valid, show it
						setLastfmInfo($scope.station.nowPlaying.lastfm);
					}
				}
			}
		});

		// Update details from external sources like Last.fm
		function updateDetails(station, songTitle, artistName) {
			const titleOnFetch = station.nowPlaying.title;
			Restangular.one('details').get({song: songTitle, artist: artistName}).then(
				(response) => {
					// ignore the response if the current song of the station has changed in teh meantimne
					if (station.nowPlaying.title == titleOnFetch) {
						station.nowPlaying.lastfm = response.lastfm;

						// Update the visible info only if the current station has not changed while fethcing the details
						if ($scope.station == station) {
							setLastfmInfo(response.lastfm);
						}
					}
				},
				(error) => console.error(error)
			);
		}

		function setLastfmInfo(lastfmInfo) {
			$scope.setLastfmTrackInfo(lastfmInfo);
			if (lastfmInfo.track?.album?.image) {
				// there are usually many image sizes provided but the last one should be the largest
				const urlFromLastFm = lastfmInfo.track.album.image.at(-1)['#text'];
				// Last.fm may sometimes return empty URLs
				if (urlFromLastFm.length > 0) {
					$scope.lastfmCoverUrl = OC.generateUrl('apps/music/api/cover/external?url={url}', {url: urlFromLastFm});
				}
			}
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
