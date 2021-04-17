/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Gregory Baudet <gregory.baudet@gmail.com>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Gregory Baudet 2018
 * @copyright Pauli Järvinen 2018 - 2021
 */

angular.module('Music').controller('SettingsViewController', [
	'$scope', '$rootScope', 'Restangular', '$window', '$timeout', 'gettextCatalog',
	function ($scope, $rootScope, Restangular, $window, $timeout, gettextCatalog) {

		$rootScope.currentView = window.location.hash;

		$scope.issueTrackerUrl = 'https://github.com/owncloud/music/issues';
		$scope.ampacheClientsUrl = 'https://github.com/owncloud/music/wiki/Ampache';
		$scope.subsonicClientsUrl = 'https://github.com/owncloud/music/wiki/Subsonic';

		var savedExcludedPaths = [];

		// $rootScope listeneres must be unsubscribed manually when the control is destroyed
		var unsubFuncs = [];

		function subscribe(event, handler) {
			unsubFuncs.push( $rootScope.$on(event, handler) );
		}

		$scope.$on('$destroy', function () {
			_.each(unsubFuncs, function(func) { func(); });
		});

		$scope.selectPath = function() {
			OC.dialogs.filepicker(
				gettextCatalog.getString('Path to your music collection'),
				function (path) {
					if (path.substr(-1) !== '/') {
						path = path + '/';
					}
					if ($scope.settings.path !== path) {
						$scope.pathChangeOngoing = true;

						// Stop any ongoing scan if path got changed
						$scope.$parent.stopScanning();

						// Store the parent reference before posting the changed value to backend;
						// $scope.$parent may not be available any more in the callback in case
						// the user has navigated to another view in the meantime.
						var parent = $scope.$parent;
						Restangular.all('settings/user/path').post({value: path}).then(
							function (data) {
								if (data.success) {
									$scope.errorPath = false;
									$scope.settings.path = path;
									parent.update();
								} else {
									$scope.errorPath = true;
								}
								$scope.pathChangeOngoing = false;
							},
							function(_error) { // error handling
								$scope.pathChangeOngoing = false;
								$scope.errorPath = true;
							}
						);
					}
				},
				false,
				'httpd/unix-directory',
				true
			);
		};

		$scope.selectExcludedPath = function(index) {
			OC.dialogs.filepicker(
				gettextCatalog.getString('Path to exclude from your music collection'),
				function (path) {
					if (path.substr(-1) !== '/') {
						path = path + '/';
					}
					$scope.settings.excludedPaths[index] = path;
					$scope.commitExcludedPaths();
				},
				false,
				'httpd/unix-directory',
				true
			);
		};

		$scope.removeExcludedPath = function(index) {
			$scope.settings.excludedPaths.splice(index, 1);
			$scope.commitExcludedPaths();
		};

		$scope.addExcludedPath = function() {
			$scope.settings.excludedPaths.push('');
			// no commit here, as the empty path is meaningless
		};

		$scope.commitExcludedPaths = function() {
			// Get the entered paths, trimming excess white space and filtering out any empty paths
			var paths = $scope.settings.excludedPaths;
			paths = _.map(paths, function(path) { return path.trim(); });
			paths = _.filter(paths, function(path) { return path !== ''; });

			// Send the paths to the back-end if there are any changes
			if (!_.isEqual(paths, savedExcludedPaths)) {
				$scope.savingExcludedPaths = true;
				Restangular.all('settings/user/exclude_paths').post({value: paths}).then(
					function(_data) {
						// success
						$scope.savingExcludedPaths = false;
						$scope.errorIgnoredPaths = false;
						savedExcludedPaths = paths;
					},
					function(_error) {
						// error handling
						$scope.savingExcludedPaths = false;
						$scope.errorIgnoredPaths = true;
					}
				);
			}
		};

		$scope.resetCollection = function() {
			OC.dialogs.confirm(
				gettextCatalog.getString('Are you sure to reset the music collection? This removes all scanned tracks and user-created playlists!'),
				gettextCatalog.getString('Reset music collection'),
				function(confirmed) {
					if (confirmed) {
						$scope.collectionResetOngoing = true;

						// stop any ongoing scan before posting the reset command
						$scope.$parent.stopScanning();

						// $scope.$parent may not be available any more in the callback in case
						// the user has navigated to another view in the meantime
						var parent = $scope.$parent;
						var executeReset = function() {
							Restangular.all('resetscanned').post().then(
									function(data) {
										if (data.success) {
											parent.resetScanned();
											parent.update();
										}
										$scope.collectionResetOngoing = false;
									},
									function(error) { // error handling
										$scope.collectionResetOngoing = false;
										OC.Notification.showTemporary(
												gettextCatalog.getString('Failed to reset the collection: ') + error.status);
									}
								);
						};

						// Trigger the reset with a small delay. This is to tackle a small issue when
						// reset button is pressed during scanning: if the POST /api/scan call fires
						// just before POST /api/resetscanned, the server may receive these two messages
						// in undeterministic order. This is because modern browsers typically hold several
						// TCP connections and successive messages are often sent through different TCP pipes.
						$timeout(executeReset, 100);
					}
				},
				true
			);
		};

		$scope.resetRadio = function() {
			OC.dialogs.confirm(
				gettextCatalog.getString('Are you sure to permanently erase all the configured internet radio stations?'),
				gettextCatalog.getString('Reset internet radio stations'),
				function(confirmed) {
					if (confirmed) {
						$scope.radioResetOngoing = true;

						// $scope.$parent may not be available any more in the callback in case
						// the user has navigated to another view in the meantime
						var parent = $scope.$parent;
						Restangular.all('radio/reset').post().then(
								function(data) {
									if (data.success) {
										parent.updateRadio();
									}
									$scope.radioResetOngoing = false;
								},
								function(error) { // error handling
									$scope.radioResetOngoing = false;
									OC.Notification.showTemporary(
											gettextCatalog.getString('Failed to reset the radio stations: ') + error.status);
								}
							);
					}
				},
				true
			);
		};

		$scope.songNotificationsEnabled = (Cookies.get('oc_music_song_notifications') !== 'false');

		$scope.$watch('songNotificationsEnabled', function(enabled) {
			Cookies.set('oc_music_song_notifications', enabled.toString());

			if (enabled && Notification.permission !== 'granted') {
				Notification.requestPermission().then(function(permission) {
					if (permission !== 'granted') {
						$timeout(() => $scope.songNotificationsEnabled = false);
					}
				});
			}
		});

		$scope.addAPIKey = function() {
			var password = Math.random().toString(36).slice(-6) + Math.random().toString(36).slice(-6);
			Restangular.all('settings/userkey/add').post({ password: password, description: $scope.ampacheDescription }).then(function(data) {
				if (data.success) {
					$scope.settings.ampacheKeys.push({
						description: $scope.ampacheDescription,
						id: data.id
					});
					$scope.ampacheDescription = '';
					$scope.ampachePassword = password;
				} else {
					$scope.ampachePassword = '';
					$scope.errorAmpache = true;
				}
			});
		};

		$scope.removeAPIKey = function(key) {
			key.loading=true;
			Restangular.all('settings/userkey/remove').post({ id: key.id }).then(function(data) {
				if (data.success) {
					// refresh remaining ampacheKeys
					Restangular.one('settings').get().then(function (value) {
						$scope.settings.ampacheKeys = value.ampacheKeys;
					});
				} else {
					key.loading=false;
				}
			});
		};

		$scope.copyToClipboard = function(elementId) {
			var range = document.createRange();
			range.selectNode(document.getElementById(elementId));
			window.getSelection().removeAllRanges(); // clear current selection
			window.getSelection().addRange(range); // to select text
			var success = document.execCommand('copy');

			if (success) {
				OC.Notification.showTemporary(
						gettextCatalog.getString('Text copied to clipboard'));
			}
		};

		$scope.errorPath = false;
		$scope.errorAmpache = false;

		$timeout(function() {
			Restangular.one('settings').get().then(function (value) {
				$scope.settings = value;
				$rootScope.loading = false;
				savedExcludedPaths = _.clone(value.excludedPaths);
				$rootScope.$emit('viewActivated');
			});
		});

		subscribe('deactivateView', function() {
			$rootScope.$emit('viewDeactivated');
		});

	}
]);
