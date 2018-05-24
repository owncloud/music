/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Gregory Baudet <gregory.baudet@gmail.com>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Gregory Baudet 2018
 * @copyright Pauli Järvinen 2018
 */

angular.module('Music').controller('SettingsViewController', [
	'$scope', '$rootScope', 'Restangular', '$window', '$timeout', 'gettext', 'gettextCatalog',
	function ($scope, $rootScope, Restangular, $window, $timeout, gettext, gettextCatalog) {

		$rootScope.currentView = window.location.hash;

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
				gettextCatalog.getString(gettext('Path to your music collection')),
				function (path) {
					if (path.substr(-1) !== '/') {
						path = path + '/';
					}
					if ($scope.settings.path !== path) {
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
									parent.updateFilesToScan();
								} else {
									$scope.errorPath = true;
								}
							}
						);
					}
				},
				false,
				'httpd/unix-directory',
				true
			);
		};

		$scope.resetCollection = function() {
			OC.dialogs.confirm(
				gettextCatalog.getString(gettext('Are you sure to reset the music collection? This removes all scanned trakcs and user-created playlists!')),
				gettextCatalog.getString(gettext('Reset music collection')),
				function(confirmed) {
					if (confirmed) {
						$scope.resetOngoing = true;

						// stop any ongoing scan before posting the reset command
						$scope.$parent.stopScanning();

						// $scope.$parent may not be available any more in the callback in case
						// the user has navigated to another view in the meantime
						var parent = $scope.$parent;
						var executeReset = function() {
							Restangular.all('resetscanned').post().then(
									function (data) {
										if (data.success) {
											parent.update();
											parent.updateFilesToScan();
										}
										$scope.resetOngoing = false;
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

		$scope.errorPath = false;
		$scope.errorAmpache = false;

		$timeout(function() {
			Restangular.one('settings').get().then(function (value) {
				$scope.settings=value;
				$rootScope.loading = false;
			});
		});

		subscribe('deactivateView', function() {
			$rootScope.$emit('viewDeactivated');
		});

	}
]);
