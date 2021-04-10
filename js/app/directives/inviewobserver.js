/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright 2019 - 2021 Pauli Järvinen
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
	// tracking the range of visible items reduces the workload when there are a huge number of instances,
	// but it cannot be used while some of the instances may be hidden (e.g. with "display: none")
	var _trackVisibleRange = true;

	// Drop all instances when view switching or artists reloading begins
	$rootScope.$on('deactivateView', eraseInstances);
	$rootScope.$on('artistsUpdating', eraseInstances);

	function eraseInstances() {
		// cancel any pending notifications first
		_(_instances).each(function(inst) {
			if (inst.pendingEnterView) {
				$timeout.cancel(inst.pendingEnterView);
			}
		});

		_instances = [];
		_firstIndexInView = 0;
		_lastIndexInView = -1;
	}

	var throttledOnScroll = _.throttle(onScroll, 50, {leading: false});

	var scrollContainer = OCA.Music.Utils.newLayoutStructure() ? document : document.getElementById('app-content');
	scrollContainer.addEventListener('scroll', throttledOnScroll);
	$rootScope.$on('resize', throttledOnScroll);
	$rootScope.$on('trackListCollapsed', throttledOnScroll);
	$rootScope.$on('artistsLoaded', throttledOnScroll);
	$rootScope.$on('albumsLayoutChanged', throttledOnScroll);
	$rootScope.$watch('loading', throttledOnScroll);

	$rootScope.$on('inViewObserver_visibilityEvent', function(event, itemsMayBeHidden) {
		_trackVisibleRange = !itemsMayBeHidden;

		resetAll();
		if (_trackVisibleRange) {
			initInViewRange(/*skipDelays=*/true);
		} else {
			updateStatusForAll(/*skipDelays=*/true);
		}
	});

	$rootScope.$on('inViewObserver_revealElement', function(event, element) {
		var inst = _(_instances).find({element: element});

		// cancel any pending "enter view" because it's about to happen immediately
		if (inst.pendingEnterView) {
			$timeout.cancel(inst.pendingEnterView);
			inst.pendingEnterView = null;
			inst.inViewPort = false;
		}

		// nothing to do if the instance is already within the viewport and and notified about it
		if (!inst.inViewPort) {
			onEnterView(inst);
			inst.inViewPort = true;
		}
	});

	var debouncedNotifyLeave = _.debounce(function() {
		_(_instances).each(function(inst) {
			if (inst.leaveViewPending) {
				onLeaveView(inst);
			}
		});
	}, 1000);

	function onScroll() {
		// do not react while the layout building is still ongoing
		if (!$rootScope.loading) {

			if (_trackVisibleRange) {
				if (!validInViewRange()) {
					initInViewRange();
				} else {
					updateInViewRange();
				}
			}
			else {
				updateStatusForAll();
			}

			// leave notifications are post-poned until when the scrolling stops
			debouncedNotifyLeave();
		}
	}

	function validInViewRange() {
		return _firstIndexInView <= _lastIndexInView;
	}

	/**
	 * Initial setup of the in-view-port statuses of the available instances
	 */
	function initInViewRange(skipDelays/*optional*/) {
		var length = _instances.length;
		var i;

		// loop from the begining until we find the first instance in viewport
		for (i = 0; i < length; ++i) {
			if (updateInViewStatus(_instances[i], skipDelays)) {
				_firstIndexInView = i;
				_lastIndexInView = i;
				break;
			}
		}

		// if some instance was found, then continue looping until we have found
		// all the instances in viewport
		for (++i; i < length; ++i) {
			if (updateInViewStatus(_instances[i], skipDelays)) {
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
	function updateInViewStatus(inst, skipDelays/*optional*/) {
		skipDelays = skipDelays || false;

		var wasInViewPort = inst.inViewPort;
		inst.inViewPort = instanceInViewPort(inst) && !instanceIsInvisible(inst);

		if (!wasInViewPort && inst.inViewPort) {
			if (!inst.leaveViewPending) {
				// element entered the viewport, notify the listeners with small delay,
				// unless immediate action has been requested
				if (skipDelays) {
					onEnterView(inst);
				} else {
					inst.pendingEnterView = $timeout(onEnterView, 250, true, inst);
				}
			}
			inst.leaveViewPending = false;
		}
		else if (wasInViewPort && !inst.inViewPort) {
			if (inst.pendingEnterView) {
				// element left the viewport before onEnterView was called, cancel the pending call
				$timeout.cancel(inst.pendingEnterView);
				inst.pendingEnterView = null;
			}
			else {
				// element left the viewport after it had been notified that it has entered the view
				if (skipDelays) {
					onLeaveView(inst);
				} else {
					inst.leaveViewPending = true;
				}
			}
		}

		return inst.inViewPort;
	}

	function instanceInViewPort(inst) {
		var margin = inst.viewPortMargin;
		return inViewService.isElementInViewPort(inst.element, margin, margin);
	}

	function instanceIsInvisible(inst) {
		var el = inst.element;
		// IE uses currentStyle, all the other browsers the getComputedStyle
		return el.currentStyle
			? (el.currentStyle.display == 'none')
			: (getComputedStyle(el, null).display == 'none');
	}

	function onEnterView(inst) {
		inst.pendingEnterView = null;

		if (!instanceIsInvisible(inst)) {
			_(inst.listeners).each(function(listener) {
				listener.onEnterView();
			});
		}
	}

	function onLeaveView(inst) {
		inst.leaveViewPending = false;
		_(inst.listeners).each(function(listener) {
			listener.onLeaveView();
		});
	}

	function resetAll() {
		_(_instances).each(function(inst) {
			if (inst.pendingEnterView) {
				$timeout.cancel(inst.pendingEnterView);
				inst.pendingEnterView = null;
			}
			if (!instanceIsInvisible(inst)) {
				onLeaveView(inst);
				inst.inViewPort = false;
			}
		});
		_firstIndexInView = 0;
		_lastIndexInView = -1;
	}

	function updateStatusForAll(skipDelays/*optional*/) {
		_(_instances).each(function(inst) {
			updateInViewStatus(inst, skipDelays);
		});
	}

	return {
		scope: {},
		controller: ['$scope', '$element', function($scope, $element) {
			this.inViewPort = false;
			this.leaveViewPending = false;
			this.pendingEnterView = null;
			this.element = $element[0];
			this.listeners = [];

			/**
			 * Listener must have two function-type properties: onEnterView and onLeaveView
			 */
			this.registerListener = function(listener) {
				this.listeners.push(listener);
			};

			_instances.push(this);
		}],
		link: function(scope, element, attributes, controller) {
			controller.viewPortMargin = Number(attributes.inViewObserverMargin) || 500;

			// Remove this instance from the static array if this would still be there upon destruction.
			// This seems to happen when the album view contents are updated during/after scanning.
			scope.$on('$destroy', function() {
				var index = _instances.indexOf(controller);
				if (index !== -1) {
					_instances.splice(index, 1);
				}
			});

			// The visibilites should be evaluated after all the ng-repeated instances
			// have been linked. There may be no actual `scroll` or `resize` events after
			// page load, in case there's so few items that no scrollbar appears.
			if (scope.$parent.$last && !$rootScope.loading) {
				throttledOnScroll();
			}
		}
	};
}]);
