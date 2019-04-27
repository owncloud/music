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

			var links = [
				'#', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M',
				'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', '…'
			];
			var linksShort = [
				'#', 'A-B', 'C-D', 'E-F', 'G-H', 'I-J', 'K-L', 'M-N',
				'O-P', 'Q-R', 'S-T', 'U-V', 'W-X', 'Y-Z', '…'
			];
			var linksExtraShort = [
				'A-C', 'D-F', 'G-I', 'J-L', 'M-O', 'P-R', 'S-U', 'V-X', 'Y-Z'
			];
			scope.links = links;
			scope.targets = {};

			function isVariantOfZ(char) {
				return ('Zz\u017A\u017B\u017C\u017D\u017E\u01B5\u01B6\u0224\u0225\u0240\u1E90\u1E91\u1E92'
					+ '\u1E93\u1E94\u1E95\u24CF\u24E9\u2C6B\u2C6C\uA762\uA763\uFF3A\uFF5A').indexOf(char) >= 0;
			}

			function itemPrecedesLetter(itemIdx, linkIdx) {
				var initialChar = scope.getElemTitle(itemIdx).substr(0,1).toUpperCase();

				// Special case: '…' is considered to be larger than Z or any of its variants
				// but equal to any other character greater than Z
				if (links[linkIdx] === '…') {
					return isVariantOfZ(initialChar) || itemPrecedesLetter(itemIdx, linkIdx-1);
				} else {
					return initialChar.localeCompare(links[linkIdx]) < 0;
				}
			}

			function setUpMainLinks() {
				for (var linkIdx = 0, itemIdx = 0;
					linkIdx < links.length && itemIdx < scope.itemCount;
					++linkIdx)
				{
					var alphabet = links[linkIdx];

					if (linkIdx === links.length - 1) {
						// Last link '…' reached while there are items left, the remaining items go under this link
						scope.targets[alphabet] = scope.getElemId(itemIdx);
					}
					else if (itemPrecedesLetter(itemIdx, linkIdx + 1)) {
						// Item is smaller than the next alphabet, i.e.
						// alphabet <= item < nextAlphabet, link the item to this alphabet
						scope.targets[alphabet] = scope.getElemId(itemIdx);
	
						// Skip the rest of the items belonging to the same alphabet
						do {
							++itemIdx;
						} while (itemIdx < scope.itemCount
								&& itemPrecedesLetter(itemIdx, linkIdx + 1));
					}
				}
			}

			function setUpGroupedLinks(groupSize) {
				for (var i = 1; i < links.length - groupSize; i += groupSize) {
					var group = links[i] + '-' + links[i+groupSize-1];

					for (var j = 0; j < groupSize; ++j) {
						var alphabet = links[i+j];
						if (alphabet in scope.targets) {
							scope.targets[group] = scope.targets[alphabet];
							break;
						}
					}
				}
			}

			function setUpTargets() {
				setUpMainLinks();
				setUpGroupedLinks(2);
				setUpGroupedLinks(3);
			}
			setUpTargets();

			function onResize(event, appView) {
				// top and bottom padding of 5px each
				var height = appView.height() - 10;

				element.css('height', height);

				if (height < 200) {
					scope.links = linksExtraShort;
					element.find("a").removeClass("dotted");
				} else if (height < 300) {
					scope.links = linksShort;
					element.find("a").removeClass("dotted");
				} else if (height < 450) {
					scope.links = links;
					element.find("a").addClass("dotted");
				} else {
					scope.links = links;
					element.find("a").removeClass("dotted");
				}
				scope.$apply();

				// adapt line-height to spread links over the available height
				element.css('line-height', Math.floor(height/scope.links.length) + 'px');

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
