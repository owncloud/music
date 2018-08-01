/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <morris.jobke@gmail.com>
 * @copyright 2014 Morris Jobke
 *
 */

angular.module('Music').factory('Token', [function () {
	return document.getElementsByTagName('head')[0].getAttribute('data-requesttoken');
}]);
