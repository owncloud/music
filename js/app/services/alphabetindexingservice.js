/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright 2020 Pauli Järvinen
 *
 */

angular.module('Music').service('alphabetIndexingService', [function() {

	var _indexChars = [
		'#', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M',
		'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', '…'
	];

	function _isVariantOfZ(char) {
		return ('Zz\u017A\u017B\u017C\u017D\u017E\u01B5\u01B6\u0224\u0225\u0240\u1E90\u1E91\u1E92'
			+ '\u1E93\u1E94\u1E95\u24CF\u24E9\u2C6B\u2C6C\uA762\uA763\uFF3A\uFF5A').indexOf(char) >= 0;
	}

	return {
		indexChars: function() {
			return _indexChars;
		},

		titlePrecedesIndexCharAt: function(title, charIdx) {
			var initialChar = title.slice(0,1).toUpperCase();

			// Special case: '…' is considered to be larger than Z or any of its variants
			// but equal to any other character greater than Z
			if (_indexChars[charIdx] === '…') {
				return _isVariantOfZ(initialChar) || this.titlePrecedesIndexCharAt(title, charIdx-1);
			} else {
				return initialChar.localeCompare(_indexChars[charIdx], OCA.Music.Utils.getLocale()) < 0;
			}
		},

		indexCharForTitle: function(title) {
			for (var i = 0; i < _indexChars.length - 1; ++i) {
				if (this.titlePrecedesIndexCharAt(title, i+1)) {
					return _indexChars[i];
				}
			}
			return '…';
		}
	};
}]);
