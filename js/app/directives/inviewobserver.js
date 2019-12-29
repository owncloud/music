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
angular.module('Music').directive('inViewObserver', ['$rootScope', '$timeout', 'inViewService',
function($rootScope, $timeout, inViewService) {

	var _instances = []; // in creation order i.e. top-most first
	var _firstIndexInView = 0;
	var _lastIndexInView = -1;

	// Drop all instances when view switching begins
	$rootScope.$on('deactivateView', function() {
		// cancel any pending notifications first
		_(_instances).each(function(inst) {
			if (inst.promise) {
				$timeout.cancel(inst.promise);
			}
		});

		_instances = [];
		_firstIndexInView = 0;
		_lastIndexInView = -1;
	});

	var throttledOnScroll = _.throttle(onScroll, 50, {leading: false});

	document.getElementById('app-content').addEventListener('scroll', throttledOnScroll);
	$rootScope.$on('resize', throttledOnScroll);
	$rootScope.$on('trackListCollapsed', throttledOnScroll);

	function onScroll() {
		// do not react while the layout building is still ongoing
		if (!$rootScope.loading) {
			if (!validInViewRange()) {
				initInViewRange();
			} else {
				updateInViewRange();
			}
		}
	}

	function validInViewRange() {
		return _firstIndexInView <= _lastIndexInView;
	}

	/**
	 * Initial setup of the in-view-port statuses of the available instances
	 */
	function initInViewRange() {
		var length = _instances.length;
		var i;

		// loop from the begining until we find the first instance in viewport
		for (i = 0; i < length; ++i) {
			if (updateInViewStatus(_instances[i])) {
				_firstIndexInView = i;
				_lastIndexInView = i;
				break;
			}
		}

		// if some instance was found, then continue looping until we have found
		// all the instances in viewport
		for (++i; i < length; ++i) {
			if (updateInViewStatus(_instances[i])) {
				_lastIndexInView = i;
			} else {
				break;
			}
		}
	}

	/**
	 * Update in-view-port status when we have a valid previous in-view-range
	 */
	function updateInViewRange() {
		var prevFirst = _firstIndexInView;
		var prevLast = _lastIndexInView;
		var i;
		var length = _instances.length;

		// Check if instances in the beginning of the range have slided off
		for (i = _firstIndexInView; i <= _lastIndexInView; ++i) {
			if (!updateInViewStatus(_instances[i])) {
				++_firstIndexInView;
			} else {
				break;
			}
		}

		// Check if instances in the end of the range have slided off
		for (i = _lastIndexInView; i > _firstIndexInView; --i) {
			if (!updateInViewStatus(_instances[i])) {
				--_lastIndexInView;
			} else {
				break;
			}
		}

		if (!validInViewRange()) {
			// None of the previous instances were anymore in the view-port.
			// We have lost the track of the scrolling direction and location
			// and have to start from scratch.
			initInViewRange();
		}
		else {
			// There may be more in-view instances above if the first index
			// did not move downwards
			if (prevFirst === _firstIndexInView) {
				for (i = _firstIndexInView - 1; i >= 0; --i) {
					if (updateInViewStatus(_instances[i])) {
						--_firstIndexInView;
					} else {
						break;
					}
				}
			}

			// There may be more in-view instances below if the last index
			// did not move upwards
			if (prevLast === _lastIndexInView) {
				for (i = _lastIndexInView + 1; i < length; ++i) {
					if (updateInViewStatus(_instances[i])) {
						++_lastIndexInView;
					} else {
						break;
					}
				}
			}
		}
	}

	/**
	 * Update in-view-port status of the given instance
	 */
	function updateInViewStatus(inst) {
		var elem = inst.element;
		var nowInViewPort = elemInViewPort(elem);
		var wasInViewPort = inst.inViewPort;

		if (inst.promise) {
			if (!nowInViewPort) {
				// element left the viewport before onEnterView was called, cancel the pending call
				$timeout.cancel(inst.promise);
				inst.promise = null;
			}
		}
		else if (!wasInViewPort && nowInViewPort) {
			// element entered the viewport, notify the listeners with small delay
			inst.promise = $timeout(onEnterView, 250, true, inst);
		}
		else if (wasInViewPort && !nowInViewPort) {
			// element left the viewport after it had been notified that it has entered the view
			onLeaveView(inst);
		}

		return nowInViewPort;
	}

	function elemInViewPort(elem) {
		return inViewService.isElementInViewPort(elem, 500, 500);
	}

	function onEnterView(inst) {
		inst.promise = null;
		inst.inViewPort = true;
		_(inst.listeners).each(function(listener) {
			listener.onEnterView();
		});
	}

	function onLeaveView(inst) {
		inst.inViewPort = false;
		_(inst.listeners).each(function(listener) {
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

			_instances.push(this);
		},
		link: function(scope) {
			// The visibilites should be evaluated after all the ng-repeated instances
			// have been linked. There may be no actual `scroll` or `resize` events after
			// page load, in case there's so few items that no scrollbar appears.
			if (scope.$parent.$last && !$rootScope.loading) {
				throttledOnScroll();
			}
		}
	};
}]);
