/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright 2013 Morris Jobke
 * @copyright 2018 - 2021 Pauli Järvinen
 *
 */

angular.module('Music').directive('alphabetNavigation', ['$rootScope', '$timeout', 'alphabetIndexingService',
function($rootScope, $timeout, alphabetIndexingService) {
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
		link: function(scope, element, _attrs, _ctrl) {

			var links = alphabetIndexingService.indexChars();
			var linksShort = [
				'#', 'A-B', 'C-D', 'E-F', 'G-H', 'I-J', 'K-L', 'M-N',
				'O-P', 'Q-R', 'S-T', 'U-V', 'W-X', 'Y-Z', '…'
			];
			var linksExtraShort = [
				'A-C', 'D-F', 'G-I', 'J-L', 'M-O', 'P-R', 'S-U', 'V-X', 'Y-Z'
			];
			scope.links = links;
			scope.targets = {};

			function itemPrecedesLetter(itemIdx, linkIdx) {
				var title = scope.getElemTitle(itemIdx);
				return (linkIdx >= links.length
						|| alphabetIndexingService.titlePrecedesIndexCharAt(title, linkIdx));
			}

			function setUpMainLinks() {
				scope.targets = {}; // erase any previous targets first

				for (var linkIdx = 0, itemIdx = 0;
					linkIdx < links.length && itemIdx < scope.itemCount;
					++linkIdx)
				{
					if (itemPrecedesLetter(itemIdx, linkIdx + 1)) {
						// Item is smaller than the next alphabet, i.e.
						// alphabet <= item < nextAlphabet, link the item to this alphabet
						var alphabet = links[linkIdx];
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
					element.find('a').removeClass('dotted');
				} else if (height < 300) {
					scope.links = linksShort;
					element.find('a').removeClass('dotted');
				} else if (height < 450) {
					scope.links = links;
					element.find('a').addClass('dotted');
				} else {
					scope.links = links;
					element.find('a').removeClass('dotted');
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
				$rootScope.$on('collectionLoaded', setUpTargets),
				$rootScope.$on('viewContentChanged', setUpTargets)
			];

			// unsubscribe listeners when the scope is destroyed
			scope.$on('$destroy', function () {
				_.each(unsubscribeFuncs, function(func) { func(); });
			});
		}
	};
}]);
