/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright 2019 - 2025 Pauli Järvinen
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

	const playText = gettextCatalog.getString('Play');
	const detailsText = gettextCatalog.getString('Details');
	let actionsMenu = null;
	let actionsMenuOwner = null;

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
			let fragment = document.createDocumentFragment();

			let outerSpan = document.createElement('span');
			outerSpan.setAttribute('draggable', data.getDraggable !== undefined);
			if (data.tooltip) {
				outerSpan.setAttribute('title', data.tooltip);
			}
			outerSpan.className = 'heading';

			let innerSpan = document.createElement('span');
			innerSpan.innerHTML = data.heading;
			outerSpan.appendChild(innerSpan);

			if (data.headingExt) {
				let extSpan = document.createElement('span');
				extSpan.className = 'muted';
				extSpan.innerHTML = data.headingExt;
				outerSpan.appendChild(extSpan);
			}

			if (data.showPlayIcon) {
				let playIcon = document.createElement('img');
				playIcon.className = 'play svg';
				playIcon.setAttribute('alt', playText);
				playIcon.setAttribute('src', playIconPath);
				outerSpan.appendChild(playIcon);
			}

			fragment.appendChild(outerSpan);

			if (data.onDetailsClick) {
				let detailsButton = document.createElement('button');
				detailsButton.className = 'icon-details';
				detailsButton.setAttribute('title', detailsText);
				fragment.appendChild(detailsButton);
				data.element.className = 'with-actions';
			}
			else if (data.actions) {
				let moreButton = document.createElement('button');
				moreButton.className = 'icon-more';
				fragment.appendChild(moreButton);

				let loadSpinner = document.createElement('span');
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
			let container = document.createElement('div');
			container.className = 'popovermenu bubble heading-actions';
			let list = document.createElement('ul');
			container.appendChild(list);

			for (var action of actions) {
				let listitem = document.createElement('li');
				let link = document.createElement('a');
				let icon = document.createElement('span');
				icon.className = 'icon icon-' + action.icon;
				let text = document.createElement('span');
				text.innerText = gettextCatalog.getString(action.text);
				// Note: l10n-extract cannot find localized string defined like above.
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
			let offset = {x: e.offsetX, y: e.offsetY};
			let transferDataObject = {
				data: data.getDraggable(data.model),
				channel: 'defaultchannel',
				offset: offset
			};
			let transferDataText = angular.toJson(transferDataObject);
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
			let hElem = document.createElement('h' + (tmplAttrs.level || '1'));
			tmplElement.replaceWith(hElem);

			return {
				post: function(scope, element, attrs, controller) {
					let data = {
						heading: _.escape(scope.$eval(attrs.heading)),
						headingExt: _.escape(scope.$eval(attrs.headingExt)),
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
