/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2024
 */

angular.module('Music').controller('AdvancedSearchViewController', [
	'$rootScope', '$scope', 'libraryService', 'playlistService', '$timeout', 'Restangular',
	function ($rootScope, $scope, libraryService, playlistService, $timeout, Restangular) {

		$rootScope.currentView = $scope.getViewIdFromUrl();

		// $rootScope listeners must be unsubscribed manually when the control is destroyed
		let _unsubFuncs = [];

		function subscribe(event, handler) {
			_unsubFuncs.push( $rootScope.$on(event, handler) );
		}

		$scope.$on('$destroy', () => {
			_.each(_unsubFuncs, (func) => func());
		});

		$scope.searchRuleTypes = [
			{ key: 'anywhere',			name: 'Any searchable text',	type: 'text' },
			{ key: 'title',				name: 'Title',					type: 'text' },
			{ key: 'favorite',			name: 'Favorite',				type: 'text' },
			{ key: 'favorite_album',	name: 'Favorite album',			type: 'text' },
			{ key: 'favorite_artist',	name: 'Favorite artist',		type: 'text' },
			{ key: 'rating',			name: 'Rating',					type: 'numeric_rating' },
			{ key: 'albumrating',		name: 'Album rating',			type: 'numeric_rating' },
			{ key: 'artistrating',		name: 'Artist rating',			type: 'numeric_rating' },
			{ key: 'added',				name: 'Add date',				type: 'date' },
			{ key: 'updated',			name: 'Update date',			type: 'date' },
			{ key: 'recent_added',		name: 'Recently added',			type: 'numeric_limit' },
			{ key: 'recent_updated',	name: 'Recently updated',		type: 'numeric_limit' },
			{ key: 'album',				name: 'Album name',				type: 'text' },
			{ key: 'artist',			name: 'Artist name',			type: 'text' },
			{ key: 'album_artist',		name: 'Album artist name',		type: 'text' },
			{ key: 'track',				name: 'Track number',			type: 'numeric' },
			{ key: 'year',				name: 'Year',					type: 'numeric' },
			{ key: 'played_times',		name: 'Played times',			type: 'numeric' },
			{ key: 'last_play',			name: 'Last played',			type: 'date' },
			{ key: 'recent_played',		name: 'Recently played',		type: 'numeric_limit' },
			{ key: 'myplayed',			name: 'Is played',				type: 'boolean' },
			{ key: 'myplayedalbum',		name: 'Is played album',		type: 'boolean' },
			{ key: 'myplayedartist',	name: 'Is played artist',		type: 'boolean' },
			{ key: 'time',				name: 'Duration',				type: 'numeric' },
			{ key: 'song_genre',		name: 'Song genre',				type: 'text' },
			{ key: 'album_genre',		name: 'Album genre',			type: 'text' },
			{ key: 'artist_genre',		name: 'Artist genre',			type: 'text' },
			{ key: 'no_genre',			name: 'Has no genre',			type: 'boolean' },
			{ key: 'playlist',			name: 'Playlist',				type: 'playlist' },
			{ key: 'playlist_name',		name: 'Playlist name',			type: 'text' },
			{ key: 'file',				name: 'File name',				type: 'text' },
		];

		$scope.searchRuleOperators = {
			text: [
				{ key: 'contain',		name: 'Contains' },
				{ key: 'notcontain',	name: 'Does not contain' },
				{ key: 'start',			name: 'Starts with' },
				{ key: 'end',			name: 'Ends with' },
				{ key: 'is',			name: 'Is' },
				{ key: 'isnot',			name: 'Is not' },
				{ key: 'sounds',		name: 'Sounds like' },
				{ key: 'notsounds',		name: 'Does not sound like' },
				{ key: 'regexp',		name: 'Matches regular expression' },
				{ key: 'notregexp',		name: 'Does not match regular expression' }
			],
			numeric: [
				{ key: '>=',			name: 'Is greater than or equal to' },
				{ key: '<=',			name: 'Is less than or equal to' },
				{ key: '=',				name: 'Equals' },
				{ key: '!=',			name: 'Does not equal' },
				{ key: '>',				name: 'Is greater than' },
				{ key: '<',				name: 'Is less than' }
			],
			numeric_limit: [
				{ key: 'limit',			name: 'Limit' }
			],
			boolean: [
				{ key: 'true',			name: 'Is true' },
				{ key: 'false',			name: 'Is false' }
			],
			date: [
				{ key: '<',				name: 'Before' },
				{ key: '>',				name: 'After' }
			],
			playlist: [
				{ key: 'equal',			name: 'Is equal to' },
				{ key: 'ne',			name: 'Is not equal to' },
			]
		};
		$scope.searchRuleOperators.numeric_rating = $scope.searchRuleOperators.numeric; // use the same operators

		$scope.resultList = libraryService.getAdvancedSearchResult();
		$scope.errorDescription = null;

		$scope.searchRules = [];

		$scope.addSearchRule = function() {
			const rule = $scope.searchRuleTypes[0];
			const operator = $scope.searchRuleOperators[rule.type][0];
			$scope.searchRules.push({ rule: rule.key, operator: operator.key, input: '' });
		};
		$scope.addSearchRule();

		$scope.removeSearchRule = function(index) {
			$scope.searchRules.splice(index, 1);
		};

		$scope.ruleType = function(ruleKey) {
			const rule = _.find($scope.searchRuleTypes, { key: ruleKey });
			return rule?.type;
		};

		$scope.operatorsForRule = function(ruleKey) {
			return $scope.searchRuleOperators[$scope.ruleType(ruleKey)] ?? [];
		};

		$scope.search = function() {
			$scope.resultList = libraryService.setAdvancedSearchResult(null);
			$scope.errorDescription = null;

			const searchArgs = {
				conjunction: 'and', // TODO: support 'or'
				rules: JSON.stringify($scope.searchRules)
			};

			Restangular.one('advanced_search').customPOST(searchArgs).then(
				(result) => {
					$scope.resultList = libraryService.setAdvancedSearchResult(result);
				},
				(error) => {
					$scope.errorDescription = error.data.message;
				}
			);
		};

		function play(startIndex = null) {
			playlistService.setPlaylist('adv_search_results' + $scope.resultList.id, $scope.resultList.tracks, startIndex);
			playlistService.publish('play');
		}

		// Call playlistService to play all songs in the current playlist from the beginning
		$scope.onHeaderClick = play;

		// Play the list, starting from a specific track
		$scope.onTrackClick = function(trackId) {
			// play/pause if currently playing list item clicked
			if ($scope.$parent.currentTrack && $scope.$parent.currentTrack.id === trackId) {
				playlistService.publish('togglePlayback');
			}
			// on any other list item, start playing the list from this item
			else {
				let index = _.findIndex($scope.resultList.tracks, (i) => i.track.id == trackId);
				play(index);
			}
		};

		$scope.getTrackData = function(listItem, index, _scope) {
			var track = listItem.track;
			return {
				title: track.artist.name + ' - ' + track.title,
				tooltip: '',
				number: index + 1,
				id: track.id
			};
		};

		$scope.getDraggable = function(trackId) {
			return { track: trackId };
		};

		subscribe('scrollToTrack', function(_event, trackId) {
			if ($scope.$parent) {
				$scope.$parent.scrollToItem('track-' + trackId);
			}
		});

		$timeout(() => {
			$rootScope.loading = false;
			$rootScope.$emit('viewActivated');
		});

		subscribe('deactivateView', () => {
			$rootScope.$emit('viewDeactivated');
		});

	}
]);
