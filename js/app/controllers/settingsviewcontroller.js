/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Gregory Baudet <gregory.baudet@gmail.com>
 * @copyright Gregory Baudet 2018
 */

angular.module('Music').controller('SettingsViewController', [
	'$scope', '$rootScope', 'Restangular','$window', '$timeout',
	function ($scope, $rootScope, Restangular, $window, $timeout ) {

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
				t('music', 'Path to your music collection'),
				function (path) {
					if ($scope.settings.path !== path) {
						Restangular.one('settings/user/path').customPOST({value: path}, '', {}, {}).then(function (data) {
							if (data.success) {
								$scope.errorPath = false;
								$scope.settings.path = path;
								$scope.$parent.update();
								$scope.$parent.updateFilesToScan();
							} else {
								$scope.errorPath = true;
							}
						});
					}
				},
				false,
				'httpd/unix-directory',
				true
			);
		};

		$scope.addAPIKey = function() {
			var password = Math.random().toString(36).slice(-6) + Math.random().toString(36).slice(-6);
			Restangular.one('settings/userkey/add').customPOST({ password: password, description: $scope.ampacheDescription }, '', {}, {}).then(function(data) {
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
			Restangular.one('settings/userkey/remove').customPOST({ id: key.id }, '', {}, {}).then(function(data) {
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
