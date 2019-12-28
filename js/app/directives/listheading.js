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

	/**
	 * Set up the contents and the listeners for a given heading element
	 */
	function setup(data) {
		/**
		 * Remove any placeholder and add the proper content
		 */
		removeChildNodes(data.element);
		data.element.appendChild(render());

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

		var ngElem = $(data.element);

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

		ngElem.on('dragend', 'span', function(e) {
			$rootScope.$broadcast('ANGULAR_DRAG_END', e, 'defaultchannel');
		});

		data.scope.$on('$destroy', function() {
			tearDown(data);
		});
	}

	function tearDown(data) {
		$(data.element).off();
		_(data.listeners).each(function (el) {
			el();
		});
		removeChildNodes(data.element);
	}

	function setupPlaceholder(data) {
		data.element.innerHTML = data.heading;
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
		require: '^inViewObserver',
		compile: function(tmplElement, tmplAttrs) {
			// Replace the <list-heading> element with <h?> element of desired size
			var hElem = document.createElement('h' + (tmplAttrs.level || '1'));
			tmplElement.replaceWith(hElem);

			return {
				post: function(scope, element, attrs, controller) {
					var data = {
						heading: scope.$eval(attrs.heading),
						headingExt: scope.$eval(attrs.headingExt),
						tooltip: scope.$eval(attrs.tooltip),
						showPlayIcon: scope.$eval(attrs.showPlayIcon),
						model: scope.$eval(attrs.model),
						onClick: scope.$eval(attrs.onClick),
						getDraggable: scope.$eval(attrs.getDraggable),
						listeners: [],
						element: element[0],
						scope: scope
					};

					// Populate the heading first with a placeholder.
					// The placeholder is replaced with the actual content once the element
					// enters the viewport (with some margins).
					setupPlaceholder(data);

					controller.registerListener({
						onEnterView: function() {
							setup(data);
						},
						onLeaveView: function() {
							tearDown(data);
							setupPlaceholder(data);
						}
					});
				}
			};
		}
	};
}]);
