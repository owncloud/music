/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <morris.jobke@gmail.com>
 * @copyright 2013 Morris Jobke
 *
 */

angular.module('Music').factory('Audio', ['$rootScope', function ($rootScope) {
	var wrapper = new PlayerWrapper();
	wrapper.init(function() {
		$rootScope.$emit('SoundManagerReady');
	});
	return wrapper;
}]);
