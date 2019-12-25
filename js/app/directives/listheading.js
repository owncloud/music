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
 * This custom directive produces a self-contained list heading widget which
 * is able to lazy-load its contents only when it is about to enter the viewport.
 * Respectively, contents are cleared when the widget leaves the viewport.
 */

angular.module('Music').directive('listHeading', ['$rootScope', '$timeout', 'gettextCatalog',
function ($rootScope, $timeout, gettextCatalog) {

	var playText = gettextCatalog.getString('Play');
	var playIconSrc = OC.imagePath('music','play-big.svg');

	var observer = null;
	var instances = null;

	// Lazy loading requires support for IntersectionObserver and WeakMap. This is not
	// available on IE and other ancient browsers.
	if (typeof IntersectionObserver !== 'undefined' && typeof WeakMap !== 'undefined') {
		var onVisibilityChange = function(changes) {
			changes.forEach(function(change) {
				var tgt = change.target;
				var data = instances.get(tgt);

				if (change.intersectionRatio > 0) {
					// element entered the viewport, setup the layout with small delay
					data.promise = $timeout(setup, 50, true, tgt, data);
				}
				else if (data.promise) {
					// element left the viewport before it had been set up, cancel the pending setup
					$timeout.cancel(data.promise);
					data.promise = null;
				}
				else {
					// element left the viewport after it had been set up, replace it with placeholder
					tearDown(tgt, data);
					setupPlaceholder(tgt, data);
				}
			});
		};
		var observerOptions = {
			root: document.getElementById("app-content"),
			rootMargin: '500px'
		};
		observer = new IntersectionObserver(onVisibilityChange, observerOptions);
		instances = new WeakMap();
	}

	/**
	 * Set up the contents and the listeners for a given heading element
	 */
	function setup(htmlElem, data) {
		data.promise = null;

		/**
		 * Remove any placeholder and add the nested <li> elements for each shown track.
		 */
		removeChildNodes(htmlElem);
		htmlElem.appendChild(render());

		/**
		 * Create the contained HTML elements
		 */
		function render() {
			var outerSpan = document.createElement('span');
			outerSpan.setAttribute('draggable', true);
			if (data.tooltip) {
				outerSpan.setAttribute('title', data.tooltip);
			}

			var innerSpan = document.createElement('span');
			innerSpan.innerHTML = data.heading;
			outerSpan.appendChild(innerSpan);

			if (data.headingExt) {
				var extSpan = document.createElement('span');
				extSpan.className = 'muted';
				extSpan.innerHTML = data.headingExt;
				outerSpan.appendChild(extSpan);
			}

			if (data.showPlayIcon) {
				var playIcon = document.createElement('img');
				playIcon.className = 'play svg';
				playIcon.setAttribute('alt', playText);
				playIcon.setAttribute('src', playIconSrc);
				outerSpan.appendChild(playIcon);
			}

			return outerSpan;
		}

		var ngElem = $(htmlElem);

		/**
		 * Click handler
		 */
		ngElem.on('click', 'span', function(e) {
			data.onClick(data.model);
		});

		/**
		 * Drag&Drop compatibility
		 */
		ngElem.on('dragstart', 'span', function(e) {
			if (e.originalEvent) {
				e.dataTransfer = e.originalEvent.dataTransfer;
			}
			var offset = {x: e.offsetX, y: e.offsetY};
			var transferDataObject = {
				data: data.getDraggable(data.model),
				channel: 'defaultchannel',
				offset: offset
			};
			var transferDataText = angular.toJson(transferDataObject);
			e.dataTransfer.setData('text', transferDataText);
			e.dataTransfer.effectAllowed = 'copyMove';
			$rootScope.$broadcast('ANGULAR_DRAG_START', e, 'defaultchannel', transferDataObject);
		});

		ngElem.on('dragend', 'span', function (e) {
			$rootScope.$broadcast('ANGULAR_DRAG_END', e, 'defaultchannel');
		});

		data.scope.$on('$destroy', function() {
			tearDown(htmlElem, data);
			if (observer !== null) {
				observer.unobserve(htmlElem);
				instances.delete(htmlElem);
			}
		});
	}

	/**
	 * Tear down a given <ul> element, removing all child nodes and unsubscribing any listeners
	 */
	function tearDown(htmlElem, data) {
		data.hiddenTracksRendered = false;
		$(htmlElem).off();
		[].forEach.call(data.listeners, function (el) {
			el();
		});
		removeChildNodes(htmlElem);
	}

	/**
	 * Setup a placeholder
	 */
	function setupPlaceholder(htmlElem, data) {
		htmlElem.innerHTML = data.heading;
	}

	/**
	 * Helper to remove all child nodes from an HTML element
	 */
	function removeChildNodes(htmlElem) {
		while (htmlElem.firstChild) {
			htmlElem.removeChild(htmlElem.firstChild);
		}
	}

	return {
		restrict: 'E',
		link: function (scope, element, attrs) {
			var data = {
				heading: scope.$eval(attrs.heading),
				headingExt: scope.$eval(attrs.headingExt),
				tooltip: scope.$eval(attrs.tooltip),
				showPlayIcon: scope.$eval(attrs.showPlayIcon),
				model: scope.$eval(attrs.model),
				onClick: scope.$eval(attrs.onClick),
				getDraggable: scope.$eval(attrs.getDraggable),
				listeners: [],
				scope: scope
			};

			// Replace the <list-heading> element with <h?> element of desired size
			var hElem = document.createElement('h' + (attrs.level || '1'));
			element.replaceWith(hElem);

			// On ancient browsers, build the heading fully at once
			if (observer === null) {
				setup(hElem, data);
			}
			// On modern browsers, populate the heading first with a placeholder.
			// The placeholder is replaced with the actual content once the element
			// enters the viewport (with some margins).
			else {
				setupPlaceholder(hElem, data);
				instances.set(hElem, data);
				observer.observe(hElem);
			}
		}
	};
}]);
