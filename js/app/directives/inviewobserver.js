/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright 2019 Pauli Järvinen
 *
 */

/**
 * This directive observes whether the host element is within the view-port or not.
 * When the status changes to either direction, it notifies the registered listeners.
 */
angular.module('Music').directive('inViewObserver', ['$rootScope', '$timeout', function($rootScope, $timeout) {

	var instances = []; // in creation order i.e. top-most first

	// Drop all instances when view switching begins
	$rootScope.$on('deactivateView', function() {
		instances = [];
	});

	document.getElementById("app-content").addEventListener('scroll', onScroll);
	$rootScope.$on('resize', onScroll);

	function onScroll() {
		[].forEach.call(instances, function(inst) {
			var elem = inst.element;
			var nowInViewPort = elemInViewPort(elem);
			var wasInViewPort = inst.inViewPort;

			if (inst.promise) {
				if (!nowInViewPort) {
					// element left the viewport before it had been set up, cancel the pending notify
					$timeout.cancel(inst.promise);
					inst.promise = null;
				}
			}
			else if (!wasInViewPort && nowInViewPort) {
				// element entered the viewport, notify the listeners with small delay
				inst.promise = $timeout(onEnterView, 100, true, inst);
			}
			else if (wasInViewPort && !nowInViewPort) {
				// element left the viewport after it had been notified that it has entered the view
				onLeaveView(inst);
			}
		});
	}

	function elemInViewPort(elem) {
		var appView = document.getElementById('app-view');
		var header = document.getElementById('header');
		var viewPortTop = header.offsetHeight;
		var viewPortBottom = header.offsetHeight + appView.offsetHeight;

		var rect = elem.getBoundingClientRect();
		return rect.bottom >= viewPortTop && rect.top <= viewPortBottom;
	}

	function onEnterView(inst) {
		inst.promise = null;
		inst.inViewPort = true;
		inst.listeners.forEach(function(listener) {
			listener.onEnterView();
		});
	}

	function onLeaveView(inst) {
		inst.inViewPort = false;
		inst.listeners.forEach(function(listener) {
			listener.onLeaveView();
		});
	}

	return {
		scope: {},
		controller: function($scope, $element) {
			this.inViewPort = false;
			this.element = $element[0];
			this.listeners = [];

			/**
			 * Listener must have two function-type properties: onEnterView and onLeaveView
			 */
			this.registerListener = function(listener) {
				this.listeners.push(listener);
			};

			instances.push(this);
		}
	};
}]);
