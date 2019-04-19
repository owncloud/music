/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright 2013 Morris Jobke
 * @copyright 2018, 2019 Pauli Järvinen
 *
 */

angular.module('Music').directive('alphabetNavigation', ['$rootScope', '$timeout',
function($rootScope, $timeout) {
	return {
		restrict: 'E',
		scope: {
			itemCount: '<',
			getElemTitle: '<',
			getElemId: '<',
			scrollToTarget: '<'
		},
		templateUrl: 'alphabetnavigation.html',
		replace: true,
		link: function(scope, element, attrs, ctrl) {

			scope.letters = [
				'#', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M',
				'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', '…'
			];
			scope.targets = {};

			function isVariantOfZ(char) {
				return ('Zz\u017A\u017B\u017C\u017D\u017E\u01B5\u01B6\u0224\u0225\u0240\u1E90\u1E91\u1E92'
					+ '\u1E93\u1E94\u1E95\u24CF\u24E9\u2C6B\u2C6C\uA762\uA763\uFF3A\uFF5A').indexOf(char) >= 0;
			}

			function itemPrecedesLetter(itemIdx, letterIdx) {
				var initialChar = scope.getElemTitle(itemIdx).substr(0,1).toUpperCase();

				// Special case: '…' is considered to be larger than Z or any of its variants
				// but equal to any other character greater than Z
				if (letterIdx === scope.letters.length-1) {
					return isVariantOfZ(initialChar) || itemPrecedesLetter(itemIdx, letterIdx-1);
				} else {
					return initialChar.localeCompare(scope.letters[letterIdx]) < 0;
				}
			}

			function setUpTargets() {
				for (var letterIdx = 0, itemIdx = 0;
					letterIdx < scope.letters.length && itemIdx < scope.itemCount;
					++letterIdx)
				{
					if (letterIdx === scope.letters.length - 1
						|| itemPrecedesLetter(itemIdx, letterIdx + 1))
					{
						scope.targets[scope.letters[letterIdx]] = scope.getElemId(itemIdx);

						do {
							++itemIdx;
						} while (itemIdx < scope.itemCount
								&& itemPrecedesLetter(itemIdx, letterIdx + 1));
					}
				}
			}
			setUpTargets();

			function onResize(event, appView) {
				// top and bottom padding of 5px each
				var height = appView.height() - 10;

				element.css('height', height);

				// Hide or replace every second letter on short screens
				if (height < 300) {
					element.find("a").removeClass("dotted").addClass("stripped");
				} else if (height < 500) {
					element.find("a").removeClass("stripped").addClass("dotted");
				} else {
					element.find("a").removeClass("dotted stripped");
				}

				if (height < 300) {
					element.css('line-height', Math.floor(height/Math.ceil(scope.letters.length/2.0)) + 'px');
				} else {
					element.css('line-height', Math.floor(height/scope.letters.length) + 'px');
				}

				// anchor the alphabet navigation to the right edge of the app view
				var appViewRight = document.body.clientWidth - appView.offset().left - appView.innerWidth();
				element.css('right', appViewRight);
			}

			function onPlayerBarShownOrHidden() {
				// React asynchronously so that angularjs bindings have had chance
				// to update the properties of the #app-view element.
				$timeout(function() {
					onResize(null, $('#app-view'));
				});
			}

			// Trigger resize on #app-view resize and player status changes.
			// Trigger re-evaluation of available scroll targets when collection reloaded.
			var unsubscribeFuncs = [
				$rootScope.$on('resize', onResize),
				$rootScope.$watch('started', onPlayerBarShownOrHidden),
				$rootScope.$on('artistsLoaded', setUpTargets)
			];

			// unsubscribe listeners when the scope is destroyed
			scope.$on('$destroy', function () {
				_.each(unsubscribeFuncs, function(func) { func(); });
			});
		}
	};
}]);
