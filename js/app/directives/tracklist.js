/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Moritz Meißelbach <moritz@meisselba.ch>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright 2017 Moritz Meißelbach
 * @copyright 2018 Pauli Järvinen
 *
 */


/**
 * This custom directive produces a self-contained track list widget that updates
 * its list items according to the global playback state and user interaction.
 * Handling this with markup alone would produce a large amount of watchers.
 */

angular.module('Music').directive('trackList', ['$rootScope', '$interpolate',
function ($rootScope, $interpolate) {

	var tpl = '<div class="play-pause"></div>' +
		'<span class="muted">{{ number ? number + ".&nbsp;" : "" }}</span>' +
		'<span title="{{ tooltip }}">{{ title }}</span>';

	var trackRenderer = $interpolate(tpl);

	return {
		restrict: 'E',
		link: function (scope, element, attrs) {
			var trackListRendered = false;
			var hiddenTracksRendered = false;
			var listContainer;
			var tracks = scope.$eval(attrs.tracks);
			var getTrackData = scope.$eval(attrs.getTrackData);
			var playTrack = scope.$eval(attrs.playTrack);
			var getDraggable = scope.$eval(attrs.getDraggable);
			var moreText = scope.$eval(attrs.moreText);
			var lessText = scope.$eval(attrs.lessText);
			var collapseLimit = attrs.collapseLimit || 999999;

			var listeners = [
				$rootScope.$watch('currentTrack', render),
				$rootScope.$watch('playing', render)
			];

			/**
			 * Render markup (once) and set classes according to current scope (always)
			 */
			function render () {
				if (!trackListRendered) {
					var widget = document.createDocumentFragment();
					var trackListFragment = renderTrackList();

					listContainer = document.createElement('ul');
					listContainer.className = 'track-list';

					listContainer.appendChild(trackListFragment);
					widget.appendChild(listContainer);
					element.html(widget);
					element.addClass('collapsed');
					trackListRendered = true;
				}

				// Set classes for the currently active list item
				var elems = listContainer.querySelectorAll(".playing, .current");
				[].forEach.call(elems, function (el) {
					el.classList.remove('current');
					el.classList.remove('playing');
				});

				if (scope.currentTrack) {
					var playing = listContainer.querySelector('#track-' + scope.currentTrack.id);
					if (playing) {
						playing.classList.add('current');
						if ($rootScope.playing) {
							playing.classList.add('playing');
						} else {
							playing.classList.remove('playing');
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
			function renderTrackList () {
				var trackListFragment = document.createDocumentFragment();

				var tracksToShow = tracks.length;
				if (tracksToShow > collapseLimit) {
					tracksToShow = collapseLimit - 1;
				}

				for (var i = 0; i < tracksToShow; i++) {
					trackListFragment.appendChild(getTrackNode(tracks[i], i));
				}

				if (tracks.length > collapseLimit) {
					var lessEl = document.createElement('li');
					var moreEl = document.createElement('li');

					lessEl.innerHTML = lessText;
					lessEl.className = 'muted more-less collapsible';
					moreEl.innerHTML = moreText;
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
			function getTrackNode (track, index, className) {
				var listItem = document.createElement('li');
				var trackData = getTrackData(track, index, scope);
				var newElement = trackRenderer(trackData);
				listItem.id = 'track-' + trackData.id;
				listItem.setAttribute('draggable', true);
				if (className) {
					listItem.className = className;
				}
				listItem.innerHTML = newElement;
				return listItem;
			}

			/**
			 * Adds those tracks that aren't initially visible to the listContainer
			 */
			function renderHiddenTracks () {
				var trackListFragment = document.createDocumentFragment();

				for (var i = collapseLimit - 1; i < tracks.length; i++) {
					trackListFragment.appendChild(getTrackNode(tracks[i], i, 'collapsible'));
				}
				var toggle = listContainer.getElementsByClassName('muted more-less collapsible');
				listContainer.insertBefore(trackListFragment, toggle[0]);
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
			element.on('click', 'li', function (event) {
				var trackId = trackIdFromElementId(this.id);
				if (trackId) {
					playTrack(trackId);
					scope.$apply();
				}
				else { // "show more/less" item
					if (!hiddenTracksRendered) {
						renderHiddenTracks();
						hiddenTracksRendered = true;
					}
					element.toggleClass('collapsed');
				}
			});

			/**
			 * Drag&Drop compatibility
			 */
			element.on('dragstart', 'li', function (e) {
				if (e.originalEvent) {
					e.dataTransfer = e.originalEvent.dataTransfer;
				}
				var trackId = trackIdFromElementId(this.id);
				var offset = {x: e.offsetX, y: e.offsetY};
				var transferDataObject = {
					data: getDraggable(trackId),
					channel: 'defaultchannel',
					offset: offset
				};
				var transferDataText = angular.toJson(transferDataObject);
				e.dataTransfer.setData('text', transferDataText);
				e.dataTransfer.effectAllowed = 'copyMove';
				$rootScope.$broadcast('ANGULAR_DRAG_START', e, 'defaultchannel', transferDataObject);
			});

			element.on('dragend', 'li', function (e) {
				$rootScope.$broadcast('ANGULAR_DRAG_END', e, 'defaultchannel');
			});

			scope.$on('$destroy', function () {
				element.off();
				[].forEach.call(listeners, function (el) {
					el();
				});
			});

		}
	};
}]);
