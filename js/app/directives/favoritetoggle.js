/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright 2024 Pauli Järvinen
 */

angular.module('Music').directive('favoriteToggle', ['Restangular', function(Restangular) {
	return {
		scope: {
			entity: '=',
			restPrefix: '<'
		},
		templateUrl: 'favoritetoggle.html',
		replace: true,
		link: function(scope, _element, _attrs, _controller) {
			scope.setFavorite = function(favStatus) {
				Restangular.one(scope.restPrefix, scope.entity.id).one('favorite').customPUT({ status: favStatus }).then((result) => {
					scope.entity.favorite = result.favorite;
				});
			};
		}
	};
}]);
