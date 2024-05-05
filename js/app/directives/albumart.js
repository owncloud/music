/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright 2013 Morris Jobke
 * @copyright 2016 - 2024 Pauli Järvinen
 *
 */

angular.module('Music').directive('albumart', ['albumartService', function(albumartService) {

	return {
		require: '?^inViewObserver',
		scope: {
			'albumart': '<'
		},
		link: function(scope, element, _attrs, ctrl) {
			/**
			 * This directive can be used for two different purposes in two different
			 * contexts:
			 * 1. Within a scrolling container, in which case there should be inViewObserver
			 *    directive as ancestor of this directive. In this case, inViewObserver is 
			 *    passed to here in the argument `ctrl`.
			 * 2. Within the player control pane, in which case there's no ancestor inViewObserver
			 *    and `ctrl` is null. In this case, the directive observes any changes on the
			 *    related attributes of the element.
			 */

			if (ctrl) {
				ctrl.registerListener({
					onEnterView: () => albumartService.setArt(element, scope.albumart),
					onLeaveView: () => { /* nothing to do */ }
				});
			}
			else {
				scope.$watch('albumart', () => albumartService.setArt(element, scope.albumart));
			}
		}
	};
}]);

