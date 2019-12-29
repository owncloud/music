/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Moritz Meißelbach <moritz@meisselba.ch>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright 2017 Moritz Meißelbach
 * @copyright 2018, 2019 Pauli Järvinen
 *
 */


/**
 * This custom directive produces a self-contained track list widget that updates
 * its list items according to the global playback state and user interaction.
 * Handling this with markup alone would produce a large amount of watchers.
 * 
 * The directive also contains a lazy-loading logic: The list is not populated
 * with track item entries, and no listeners are registered before the list instance
 * in question is scrolled to the viewport. Respectively, the list item elements are
 * removed and listeners de-registered once the list instance leaves the viewport.
 */

angular.module('Music').directive('trackList', ['$rootScope', '$interpolate', '$timeout', 'gettextCatalog',
function ($rootScope, $interpolate, $timeout, gettextCatalog) {

	var trackTemplate = '<div class="play-pause"></div>' +
		'<span class="muted">{{ number ? number + ".&nbsp;" : "" }}</span>' +
		'<span title="{{ tooltip }}">{{ title }}</span>';
	var trackRenderer = $interpolate(trackTemplate);

	// Localized strings
	var lessText = gettextCatalog.getString('Show less …');
	var detailsText = gettextCatalog.getString('Details');
	var moreText = function(count) {
		return gettextCatalog.getString('Show all {{ count }} songs …', { count: count });
	};


	/**
	 * Set up the track items and the listeners for a given <ul> element
	 */
	function setup(data) {
		data.listeners.push(data.scope.$watch('currentTrack', updateClasses));
		data.listeners.push($rootScope.$watch('playing', updateClasses));

		var htmlElem = data.element[0];

		/**
		 * Remove any placeholder and add the nested <li> elements for each shown track.
		 */
		removeChildNodes(htmlElem);
		htmlElem.appendChild(renderTrackList());

		if (data.expanded) {
			renderHiddenTracks();
		}

		/**
		 * Set classes of the track items according to current scope
		 */
		function updateClasses() {
			var elems = htmlElem.querySelectorAll(".playing, .current");
			_(elems).each(function (el) {
				el.classList.remove('current');
				el.classList.remove('playing');
			});

			if (data.scope.currentTrack) {
				var currentTrack = htmlElem.querySelector('#track-' + data.scope.currentTrack.id);
				if (currentTrack) {
					currentTrack.classList.add('current');
					if ($rootScope.playing) {
						currentTrack.classList.add('playing');
					} else {
						currentTrack.classList.remove('playing');
					}
				}
			}
		}

		/**
		 * Create the list of individual tracks. Skips after reaching the "toggle threshold"
		 * so only tracks that are initially visible are actually being rendered
		 *
		 * @returns {DocumentFragment}
		 */
		function renderTrackList() {
			var trackListFragment = document.createDocumentFragment();

			var tracksToShow = data.tracks.length;
			if (tracksToShow > data.collapseLimit) {
				tracksToShow = data.collapseLimit - 1;
			}

			for (var i = 0; i < tracksToShow; i++) {
				trackListFragment.appendChild(getTrackNode(data.tracks[i], i));
			}

			if (data.tracks.length > data.collapseLimit) {
				var lessEl = document.createElement('li');
				var moreEl = document.createElement('li');

				lessEl.innerHTML = lessText;
				lessEl.className = 'muted more-less collapsible';
				moreEl.innerHTML = moreText(data.tracks.length);
				moreEl.className = 'muted more-less';
				trackListFragment.appendChild(lessEl);
				trackListFragment.appendChild(moreEl);
			}
			return trackListFragment;
		}

		/**
		 * Renders a single Track HTML Node
		 *
		 * @param object track
		 * @param int index
		 * @param string className (optional)
		 * @returns {HTMLLIElement}
		 */
		function getTrackNode(track, index, className) {
			var listItem = document.createElement('li');

			var listItemContent = document.createElement('div');
			var trackData = data.getTrackData(track, index, data.scope);
			listItemContent.innerHTML = trackRenderer(trackData);
			listItemContent.setAttribute('draggable', true);
			listItem.appendChild(listItemContent);

			var detailsButton = document.createElement('button');
			detailsButton.className = 'icon-details';
			detailsButton.title = detailsText;
			listItem.appendChild(detailsButton);

			listItem.id = 'track-' + trackData.id;
			if (className) {
				listItem.className = className;
			}
			return listItem;
		}

		/**
		 * Adds those tracks that aren't initially visible to the element
		 */
		function renderHiddenTracks() {
			var trackListFragment = document.createDocumentFragment();

			for (var i = data.collapseLimit - 1; i < data.tracks.length; i++) {
				trackListFragment.appendChild(getTrackNode(data.tracks[i], i, 'collapsible'));
			}
			var toggle = htmlElem.getElementsByClassName('muted more-less collapsible');
			htmlElem.insertBefore(trackListFragment, toggle[0]);

			updateClasses();

			data.hiddenTracksRendered = true;
		}

		function trackIdFromElementId(elemId) {
			if (elemId && elemId.substring(0, 6) === 'track-') {
				return parseInt(elemId.split('-')[1]);
			} else {
				return null;
			}
		}

		/**
		 * Click handler for list items
		 */
		data.element.on('click', 'li', function(event) {
			var trackId = trackIdFromElementId(this.id);
			if (trackId) {
				if (event.target.className == 'icon-details') {
					data.showTrackDetails(trackId);
				} else {
					data.playTrack(trackId);
					data.scope.$apply();
				}
			}
			else { // "show more/less" item
				if (!data.hiddenTracksRendered) {
					renderHiddenTracks();
				}
				data.expanded = !data.expanded;
				data.element.toggleClass('collapsed');
			}
		});

		/**
		 * Drag&Drop compatibility
		 */
		data.element.on('dragstart', 'li', function(e) {
			if (e.originalEvent) {
				e.dataTransfer = e.originalEvent.dataTransfer;
			}
			var trackId = trackIdFromElementId(this.id);
			var offset = {x: e.offsetX, y: e.offsetY};
			var transferDataObject = {
				data: data.getDraggable(trackId),
				channel: 'defaultchannel',
				offset: offset
			};
			var transferDataText = angular.toJson(transferDataObject);
			e.dataTransfer.setData('text', transferDataText);
			e.dataTransfer.effectAllowed = 'copyMove';
			$rootScope.$broadcast('ANGULAR_DRAG_START', e, 'defaultchannel', transferDataObject);
		});

		data.element.on('dragend', 'li', function (e) {
			$rootScope.$broadcast('ANGULAR_DRAG_END', e, 'defaultchannel');
		});

		data.scope.$on('$destroy', function() {
			tearDown(data);
		});
	}

	/**
	 * Tear down a given <ul> element, removing all child nodes and unsubscribing any listeners
	 */
	function tearDown(data) {
		data.element.off();
		_(data.listeners).each(function(lstnr) {
			lstnr();
		});
	}

	/**
	 * Setup a placeholder list item within the given <ul> element using the given height
	 */
	function setupPlaceholder(data, height) {
		data.hiddenTracksRendered = false;
		removeChildNodes(data.element[0]);
		placeholder = document.createElement('li');
		placeholder.style.height = height + 'px';
		data.element[0].appendChild(placeholder);
	}

	/**
	 * Calculate total height of all the child nodes of the given <ul> element
	 */
	function calculateContentsHeight(data) {
		totalHeight = 0;
		data.element.children().each(function() {
			totalHeight += $(this).outerHeight(true);
		});
		return totalHeight;
	}

	/**
	 * Estimate the total height needed for the <li> entries of the track list element
	 */
	function estimateContentsHeight(data) {
		var rowCount = Math.min(data.tracks.length, data.collapseLimit);
		return 31.5 * rowCount;
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
		template: '<ul class="track-list collapsed"></ul>',
		replace: true,
		require: '?^inViewObserver',
		link: function(scope, element, attrs, controller) {
			var data = {
				expanded: false,
				hiddenTracksRendered: false,
				tracks: scope.$eval(attrs.tracks),
				getTrackData: scope.$eval(attrs.getTrackData),
				playTrack: scope.$eval(attrs.playTrack),
				showTrackDetails: scope.$eval(attrs.showTrackDetails),
				getDraggable: scope.$eval(attrs.getDraggable),
				collapseLimit: attrs.collapseLimit || 999999,
				listeners: [],
				scope: scope,
				element: element
			};

			// In case this directive has inViewObserver as ancestor, populate it first
			// with a placeholder. The placeholder is replaced with the actual content
			// once the element enters the viewport (with some margins).
			if (controller) {
				setupPlaceholder(data, estimateContentsHeight(data));

				controller.registerListener({
					onEnterView: function() {
						setup(data);
					},
					onLeaveView: function() {
						var height = calculateContentsHeight(data);
						tearDown(data);
						setupPlaceholder(data, height);
					}
				});
			}
			// Otherwise, populate immediately with the actual content
			else {
				setup(data);
			}
		}
	};
}]);
