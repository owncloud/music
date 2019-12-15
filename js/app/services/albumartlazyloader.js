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
 * Lazy-loader for albumart. This service has an intimate relation to the albumart directive.
 */
angular.module('Music').service('albumartLazyLoader', ['$timeout', function($timeout) {

	var observer = null;
	var imagesToBeLoaded = null;

	if (typeof IntersectionObserver !== 'undefined'
		&& typeof WeakMap !== 'undefined')
	{
		imagesToBeLoaded = new WeakMap();

		var loadAlbumart = function(tgtElem) {
			var tgt = $(tgtElem);
			tgt.css('background-image', 'url(' + tgt.attr('cover') + ')');
		};

		var onVisibilityChange = function(changes) {
			changes.forEach(function(change) {
				var tgt = change.target;
				var promise;
				if (change.intersectionRatio > 0) {
					// Load the albumart with a small delay after it enters the viewport with margins.
					// This is to avoid loading images which just pass through the viewport during
					// fast scrolling.
					promise = $timeout(loadAlbumart, 100, true, tgt);
					imagesToBeLoaded.set(tgt, promise);
				} else {
					// Cancel any pending load for albumart exiting the viewport with margins
					promise = imagesToBeLoaded.get(tgt);
					if (promise !== undefined) {
						$timeout.cancel(promise);
						imagesToBeLoaded.delete(tgt);
					}
				}
			});
		};
		var observerOptions = {
			root: document.getElementById("app-content"),
			rootMargin: '1000px'
		};

		observer = new IntersectionObserver(onVisibilityChange, observerOptions);
	}

	return {
		supported: function() {
			return (observer !== null);
		},
		register: function(element) {
			var img = angular.element(element)[0];
			observer.observe(img);
		}
	};
}]);
