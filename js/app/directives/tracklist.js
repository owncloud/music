/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Moritz Meißelbach <moritz@meisselba.ch>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright 2017 Moritz Meißelbach
 * @copyright 2018 - 2023 Pauli Järvinen
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

angular.module('Music').directive('trackList', ['$rootScope', '$interpolate', 'gettextCatalog',
function ($rootScope, $interpolate, gettextCatalog) {

	const trackTemplate = '<div class="play-pause"></div>' +
		'<span class="muted">{{ number ? number + ".&nbsp;" : "" }}</span>' +
		'<span title="{{ tooltip }}">{{ title }}</span>';
	const trackRenderer = $interpolate(trackTemplate);

	// Localized strings
	const lessText = gettextCatalog.getString('Show less …');
	const detailsText = gettextCatalog.getString('Details');
	const moreText = function(count) { // this is the default implementation, may be overridden with attributes
		return gettextCatalog.getString('Show all {{ count }} songs …', { count: count });
	};

	// Search support
	let searchModeTrackMatches = null;
	$rootScope.$on('searchMatchedTracks', function(_event, matchingTracks) {
		// store only the IDs of the matching tracks; store them in sorted array
		// to enable binary search
		searchModeTrackMatches = _.map(matchingTracks, 'id');
		searchModeTrackMatches.sort(function(a,b) { return a - b; });
	}); 
	$rootScope.$on('searchOff', function() {
		searchModeTrackMatches = null;
	}); 
	function inSearchMode() {
		return searchModeTrackMatches !== null;
	}
	function trackMatchedInSearch(trackId) {
		return _.sortedIndexOf(searchModeTrackMatches, trackId) !== -1;
	}

	/**
	 * Set up the track items and the listeners for a given <ul> element
	 */
	function setup(data) {
		data.listeners = [
			data.scope.$watch('currentTrack', updateClasses),
			$rootScope.$watch('playing', updateClasses)
		];

		// non-lazy-loaded directive needs to react to search mode activating on its own;
		// lazy-loaded directives get this via onEnterView
		if (!data.useLazyLoading) {
			data.listeners.push(
				$rootScope.$on('searchMatchedTracks', function() {
					tearDown(data);
					setup(data);
				})
			);
		}

		let htmlElem = data.element[0];

		/**
		 * Remove any placeholder and add the nested <li> elements for each shown track.
		 */
		removeChildNodes(htmlElem);
		htmlElem.appendChild(renderTrackList());

		if (data.expanded || inSearchMode()) {
			renderHiddenTracks();
		}

		/**
		 * Set classes of the track items according to current scope
		 */
		function updateClasses() {
			let elems = htmlElem.querySelectorAll('.playing, .current');
			_(elems).each(function (el) {
				el.classList.remove('current');
				el.classList.remove('playing');
			});

			if (data.scope.currentTrack?.type === data.contentType) {
				let currentTrack = htmlElem.querySelector('#' + data.trackIdPrefix + data.scope.currentTrack.id);
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
			let trackListFragment = document.createDocumentFragment();

			let tracksToShow = data.tracks.length;
			if (tracksToShow > data.collapseLimit) {
				tracksToShow = data.collapseLimit - 1;
			}

			for (var i = 0; i < tracksToShow; i++) {
				trackListFragment.appendChild(getTrackNode(data.tracks[i], i));
			}

			if (data.tracks.length > data.collapseLimit) {
				let lessEl = document.createElement('li');
				let moreEl = document.createElement('li');

				lessEl.innerHTML = lessText;
				lessEl.className = 'muted more-less collapsible';
				moreEl.innerHTML = data.showCollapsedText(data.tracks.length);
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
		function getTrackNode(track, index, className = null) {
			let listItem = document.createElement('li');

			let listItemContent = document.createElement('div');
			let trackData = data.getTrackData(track, index, data.scope);
			listItemContent.innerHTML = trackRenderer(trackData);
			listItemContent.setAttribute('draggable', data.getDraggable !== undefined);
			listItem.appendChild(listItemContent);

			if (data.showTrackDetails) {
				let detailsButton = document.createElement('button');
				detailsButton.className = 'icon-details';
				detailsButton.title = detailsText;
				listItem.appendChild(detailsButton);
			}

			listItem.id = data.trackIdPrefix + trackData.id;
			if (className) {
				listItem.className = className;
			}

			if (inSearchMode()) {
				if (trackMatchedInSearch(trackData.id)) {
					listItem.className += ' matched';
				}
			}

			return listItem;
		}

		function trackIdFromElementId(elemId) {
			if (elemId && elemId.startsWith(data.trackIdPrefix)) {
				return parseInt(elemId.split('-').pop());
			} else {
				return null;
			}
		}

		/**
		 * Adds those tracks that aren't initially visible to the element
		 */
		function renderHiddenTracks() {
			if (data.collapseLimit < data.tracks.length) {
				let trackListFragment = document.createDocumentFragment();

				for (var i = data.collapseLimit - 1; i < data.tracks.length; i++) {
					trackListFragment.appendChild(getTrackNode(data.tracks[i], i, 'collapsible'));
				}
				let toggle = htmlElem.getElementsByClassName('muted more-less collapsible');
				htmlElem.insertBefore(trackListFragment, toggle[0]);

				updateClasses();

				data.hiddenTracksRendered = true;
			}
		}

		/**
		 * Click handler for list items
		 */
		data.element.on('click', 'li', function(event) {
			let trackId = trackIdFromElementId(this.id);
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
			let trackId = trackIdFromElementId(this.id);
			let offset = {x: e.offsetX, y: e.offsetY};
			let transferDataObject = {
				data: data.getDraggable(trackId),
				channel: 'defaultchannel',
				offset: offset
			};
			let transferDataText = angular.toJson(transferDataObject);
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
		if (data.listeners !== null) {
			data.element.off();
			_(data.listeners).each(function(lstnr) {
				lstnr();
			});
			data.listeners = null;
		}
	}

	/**
	 * Setup a placeholder list item within the given <ul> element
	 */
	function setupPlaceholder(data) {
		data.hiddenTracksRendered = false;
		removeChildNodes(data.element[0]);

		let height = estimateContentsHeight(data);
		let placeholder = document.createElement('li');
		placeholder.style.height = height + 'px';
		placeholder.className = 'placeholder';
		data.element[0].appendChild(placeholder);
	}

	/**
	 * Estimate the total height needed for the <li> entries of the track list element
	 */
	function estimateContentsHeight(data) {
		let rowCount = 0;

		// During search, all matched tracks are shown
		if (inSearchMode()) {
			for (var i = 0; i < data.tracks.length; ++i) {
				let trackData = data.getTrackData(data.tracks[i], i, data.scope);
				if (trackMatchedInSearch(trackData.id)) {
					rowCount++;
				}
			}
		}
		// Otherwise, the non-collapsed tracks are shown
		else {
			if (data.expanded) {
				rowCount = data.tracks.length + 1; // all tracks + "Show less"
			} else {
				rowCount = Math.min(data.tracks.length, data.collapseLimit);
			}
		}
		return 31.4833 * rowCount;
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
			let data = {
				expanded: false,
				hiddenTracksRendered: false,
				tracks: scope.$eval(attrs.tracks),
				getTrackData: scope.$eval(attrs.getTrackData),
				playTrack: scope.$eval(attrs.playTrack),
				showTrackDetails: scope.$eval(attrs.showTrackDetails),
				getDraggable: scope.$eval(attrs.getDraggable),
				collapseLimit: attrs.collapseLimit || 999999,
				showCollapsedText: scope.$eval(attrs.showCollapsedText) ?? moreText,
				trackIdPrefix: (scope.$eval(attrs.trackIdPrefix) ?? 'track') + '-',
				contentType: scope.$eval(attrs.contentType) ?? 'song',
				listeners: null,
				scope: scope,
				element: element,
				useLazyLoading: _.isObject(controller)
			};

			// In case this directive has inViewObserver as ancestor, populate it first
			// with a placeholder. The placeholder is replaced with the actual content
			// once the element enters the viewport (with some margins).
			if (data.useLazyLoading) {
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
}]);
