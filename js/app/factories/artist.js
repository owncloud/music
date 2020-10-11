/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <morris.jobke@gmail.com>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright 2013 Morris Jobke
 * @copyright 2018 - 2020 Pauli Järvinen
 *
 */

angular.module('Music').factory('ArtistFactory', ['Restangular', function (Restangular) {
	return {
		getArtists: function() {
			return Restangular.all('prepare_collection').post().then(function(reply) {
				return Restangular.all('collection').getList({hash: reply.hash});
			});
		}
	};
}]);
