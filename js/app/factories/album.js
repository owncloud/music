/**
 * ownCloud - Music app
 *
 * @author Webhippie United
 * @copyright 2014 Webhippie <team@webhippie.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

angular.module('Music').factory('Album', ['Restangular', '$rootScope', function (Restangular, $rootScope) {
  var all = function(tree) {
    return Restangular.all('albums').getList({fulltree: tree});
  };
  return {
    get: function(id) {
      return Restangular.one('album', id).get({fulltree: true});
    },
    getWithTree: function() {
      return all(true);
    },
    getWithoutTree: function() {
      return all(false);
    }
  };
}]);