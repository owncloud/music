/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright 2019 - 2022 Pauli Järvinen
 *
 */

import playIconPath from '../../../img/play-big.svg';

/**
 * This custom directive produces a self-contained list heading widget which
 * is able to lazy-load its contents only when it is about to enter the viewport.
 * Respectively, contents are cleared when the widget leaves the viewport.
 */

angular.module('Music').directive('listHeading', ['$rootScope', 'gettextCatalog',
function ($rootScope, gettextCatalog) {

	var playText = gettextCatalog.getString('Play');
	var detailsText = gettextCatalog.getString('Details');
	var actionsMenu = null;
	var actionsMenuOwner = null;

	/**
	 * Set up the contents for a given heading element
	 */
	function setup(data) {
		const ngElem = $(data.element);
		data.listeners = [];

		/**
		 * Remove any placeholder and add the proper content
		 */
		removeChildNodes(data.element);
		data.element.appendChild(render());

		/**
		 * Create the contained HTML elements
		 */
		function render() {
			var fragment = document.createDocumentFragment();

			var outerSpan = document.createElement('span');
			outerSpan.setAttribute('draggable', data.getDraggable !== undefined);
			if (data.tooltip) {
				outerSpan.setAttribute('title', data.tooltip);
			}
			outerSpan.className = 'heading';

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
				playIcon.setAttribute('src', playIconPath);
				outerSpan.appendChild(playIcon);
			}

			fragment.appendChild(outerSpan);

			if (data.onDetailsClick) {
				var detailsButton = document.createElement('button');
				detailsButton.className = 'icon-details';
				detailsButton.setAttribute('title', detailsText);
				fragment.appendChild(detailsButton);
				data.element.className = 'with-actions';
			}
			else if (data.actions) {
				var moreButton = document.createElement('button');
				moreButton.className = 'icon-more';
				fragment.appendChild(moreButton);

				var loadSpinner = document.createElement('span');
				loadSpinner.className = 'icon-loading-small';
				fragment.appendChild(loadSpinner);

				data.element.className = 'with-actions';
			}
			else {
				data.element.className = '';
			}

			return fragment;
		}

		function createActionsMenu(actions) {
			var container = document.createElement('div');
			container.className = 'popovermenu bubble heading-actions';
			var list = document.createElement('ul');
			container.appendChild(list);

			for (var action of actions) {
				var listitem = document.createElement('li');
				var link = document.createElement('a');
				var icon = document.createElement('span');
				icon.className = 'icon icon-' + action.icon;
				var text = document.createElement('span');
				text.innerText = gettextCatalog.getString(action.text);
				// Note: l10n-extract cannot find localised string defined like above.
				// Ensure that the same string can be extracted from somewhere else.
				$(listitem).data('callback', action.callback);
				link.appendChild(icon);
				link.appendChild(text);
				listitem.appendChild(link);
				list.appendChild(listitem);
			}

			return container;
		}

		function toggleActionsMenu() {
			if (actionsMenu) {
				actionsMenuOwner.removeChild(actionsMenu);
				actionsMenu = null;
			}

			if (actionsMenuOwner !== data.element) {
				actionsMenu = createActionsMenu(data.actions);
				data.element.appendChild(actionsMenu);
				actionsMenuOwner = data.element;
			}

			if (!actionsMenu) {
				actionsMenuOwner = null;
			}
		}

		function closeActionsMenu() {
			actionsMenuOwner.removeChild(actionsMenu);
			actionsMenu = null;
			actionsMenuOwner = null;
		}

		/**
		 * Sync the "busy" state of the model
		 */
		data.listeners.push(
			data.scope.$watch(() => data.model.busy, updateModelBusy),
		);
		updateModelBusy(data.model.busy);

		function updateModelBusy(busy) {
			ngElem.toggleClass('busy', (busy === true));
		}

		/**
		 * Click handlers
		 */
		ngElem.on('click', '.heading', function(_e) {
			data.onClick(data.model);
		});
		ngElem.on('click', 'button.icon-details', function(_e) {
			data.onDetailsClick(data.model);
		});
		ngElem.on('click', 'button.icon-more', function(_e) {
			toggleActionsMenu();
		});
		ngElem.on('click', 'li', function(_e) {
			$(this).data('callback')(data.model);
			closeActionsMenu();
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
		_(data.listeners).each((unsubFunc) => unsubFunc());
		data.listeners = null;
	}

	function setupPlaceholder(data) {
		data.element.innerHTML = data.heading;
		data.element.className = 'placeholder';
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
		require: '?^inViewObserver',
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
						onDetailsClick: scope.$eval(attrs.onDetailsClick),
						actions: scope.$eval(attrs.actions),
						getDraggable: scope.$eval(attrs.getDraggable),
						element: element[0],
						scope: scope,
						listeners: null
					};

					if (data.onDetailsClick && data.actions) {
						console.error('Invalid configuration for list heading, a heading cannot have both details button and actions menu');
					}

					// In case this directive has inViewObserver as ancestor, populate it first
					// with a placeholder. The placeholder is replaced with the actual content
					// once the element enters the viewport (with some margins).
					if (controller) {
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
					// Otherwise, populate immediately with the actual content
					else {
						setup(data);
					}
				}
			};
		}
	};
}]);
