/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2021
 */

angular.module('Music').service('podcastService', [
'$rootScope', '$timeout', '$q', 'libraryService', 'gettextCatalog', 'Restangular',
function($rootScope, $timeout, $q, libraryService, gettextCatalog, Restangular) {

	// Private functions
	function reloadChannel(channel) {
		var deferred = $q.defer();

		Restangular.one('podcasts', channel.id).all('update').post({prevHash: channel.hash}).then(
			function (result) {
				if (!result.success) {
					OC.Notification.showTemporary(
							gettextCatalog.getString('Could not update the channel "{{ title }}" from the source', { title: channel.title }));
				} else if (result.updated) {
					libraryService.replacePodcastChannel(result.channel);
					$timeout(() => $rootScope.$emit('viewContentChanged'));
				}
				deferred.resolve(result);
			},
			function (_error) {
				OC.Notification.showTemporary(
						gettextCatalog.getString('Unexpected error when updating the channel "{{ title }}"', { title: channel.title }));
				deferred.resolve(); // resolve even on failure so that callers need to define only one callback
			}
		);

		return deferred.promise;
	}

	// Service API
	return {

		// Show a popup dialog to add a new podcast channel from an RSS feed
		showAddPodcastDialog: function() {
			const subscribePodcastChannel = function(url) {
				$rootScope.$emit('podcastsBusyEvent', true);
				Restangular.all('podcasts').post({url: url}).then(
					function (result) {
						libraryService.addPodcastChannel(result);
						OC.Notification.showTemporary(
							gettextCatalog.getString('Podcast channel "{{ title }}" added', { title: result.title }));
						$rootScope.$emit('podcastsBusyEvent', false);
						if ($rootScope.currentView === '#/podcasts') {
							$timeout(() => $rootScope.$emit('viewContentChanged'));
						}
					},
					function (error) {
						var errMsg;
						if (error.status === 400) {
							errMsg = gettextCatalog.getString('Invalid RSS feed URL');
						} else if (error.status === 409) {
							errMsg = gettextCatalog.getString('This channel is already subscribed');
						} else {
							errMsg = gettextCatalog.getString('Failed to add the podcast channel');
						}
						OC.Notification.showTemporary(errMsg);
						$rootScope.$emit('podcastsBusyEvent', false);
					}
				);
			};

			OC.dialogs.prompt(
					gettextCatalog.getString('Add a new podcast channel from an RSS feed'),
					gettextCatalog.getString('Add channel'),
					function (confirmed, url) {
						if (confirmed) {
							subscribePodcastChannel(url);
						}
					},
					true, // modal
					gettextCatalog.getString('URL'),
					false // password
			);
		},

		// Refresh the contents of the given podcast channel
		reloadPodcastChannel: function(channel) {
			$rootScope.$emit('podcastsBusyEvent', true);
			reloadChannel(channel).then(function(result) {
				if (result?.updated) {
					OC.Notification.showTemporary(
							gettextCatalog.getString('The channel was updated from the source'));
				} else if (result?.updated === false) {
					OC.Notification.showTemporary(
							gettextCatalog.getString('The channel was already up-to-date'));
				} else {
					// nothing to do, error has already been shown by the reloadChannel function
				}
				$rootScope.$emit('podcastsBusyEvent', false);
			});
		},

		// Refresh the contents of all the subscribed podcast channels
		reloadAllPodcasts: function() {
			const channels = libraryService.getAllPodcastChannels();
			var index = 0;
			var changeCount = 0;

			$rootScope.$emit('podcastsBusyEvent', true);

			const processNextChannel = function() {
				if (index < channels.length) {
					reloadChannel(channels[index]).then(function(result) {
						if (result?.updated) {
							changeCount++;
						}
						index++;
						processNextChannel();
					});
				}
				else {
					$rootScope.$emit('podcastsBusyEvent', false);

					if (changeCount === 0) {
						OC.Notification.showTemporary(
							gettextCatalog.getString('All channels were already up-to-date'));
					} else {
						OC.Notification.showTemporary(
							gettextCatalog.getPlural(changeCount,
								'Changes were loaded for one channel',
								'Changes were loaded for {{ count }} channels', { count: changeCount })
						);
					}
				}
			};
			processNextChannel();
		},

		// Remove a single previously subscribed podcast channel
		removePodcastChannel: function(channel) {
			const doDelete = function() {
				Restangular.one('podcasts', channel.id).remove().then(
					function (result) {
						if (!result.success) {
							OC.Notification.showTemporary(
									gettextCatalog.getString('Could not remove the channel "{{ title }}"', { title: channel.title }));
						} else {
							libraryService.removePodcastChannel(channel);
							$timeout(() => $rootScope.$emit('viewContentChanged'));
						}
					},
					function (_error) {
						OC.Notification.showTemporary(
								gettextCatalog.getString('Could not remove the channel "{{ title }}"', { title: channel.title }));
					}
				);
			};

			OC.dialogs.confirm(
					gettextCatalog.getString('Are you sure to remove the podcast channel "{{ title }}"?', { title: channel.title }),
					gettextCatalog.getString('Remove channel'),
					function(confirmed) {
						if (confirmed) {
							doDelete();
						}
					},
					true
			);
		}
	};
}]);
