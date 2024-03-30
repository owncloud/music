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

		$scope.maxResults = '100';
		$scope.order = 'name';
		$scope.conjunction = 'and';
		$scope.entityType = 'track';

		$scope.availableOrders = {
			track: [
				{ value: 'name',		text: 'by name' },
				{ value: 'parent',		text: 'by artist' },
				{ value: 'newest',		text: 'by time added' },
				{ value: 'play_count',	text: 'by play count' },
				{ value: 'last_played',	text: 'by recent play' },
				{ value: 'rating',		text: 'by rating' },
				{ value: 'random',		text: 'randomly' },
			],
			album: [
				{ value: 'name',	text: 'by name' },
				{ value: 'parent',	text: 'by artist' },
				{ value: 'newest',	text: 'by time added' },
				{ value: 'rating',	text: 'by rating' },
				{ value: 'random',	text: 'randomly' },
			],
		};

		$scope.searchRuleTypes = {
			track: [
				{
					label: null,
					options: [
						{ key: 'anywhere',			name: 'Any searchable text',	type: 'text' },
					]
				},
				{
					label: 'Song metadata',
					options: [
						{ key: 'title',				name: 'Name',					type: 'text' },
						{ key: 'album',				name: 'Album name',				type: 'text' },
						{ key: 'artist',			name: 'Artist name',			type: 'text' },
						{ key: 'album_artist',		name: 'Album artist name',		type: 'text' },
						{ key: 'track',				name: 'Track number',			type: 'numeric' },
						{ key: 'year',				name: 'Year',					type: 'numeric' },
						{ key: 'time',				name: 'Duration (seconds)',		type: 'numeric' },
						{ key: 'bitrate',			name: 'Bit rate',				type: 'numeric' },
						{ key: 'song_genre',		name: 'Song genre',				type: 'text' },
						{ key: 'album_genre',		name: 'Album genre',			type: 'text' },
						{ key: 'artist_genre',		name: 'Artist genre',			type: 'text' },
						{ key: 'no_genre',			name: 'Has no genre',			type: 'boolean' },
					]
				},
				{
					label: 'File data',
					options: [
						{ key: 'file',				name: 'File name',				type: 'text' },
						{ key: 'added',				name: 'Add date',				type: 'date' },
						{ key: 'updated',			name: 'Update date',			type: 'date' },
						{ key: 'recent_added',		name: 'Recently added',			type: 'numeric_limit' },
						{ key: 'recent_updated',	name: 'Recently updated',		type: 'numeric_limit' },
					]
				},
				{
					label: 'Rating',
					options: [
						{ key: 'favorite',			name: 'Favorite',				type: 'text' },
						{ key: 'favorite_album',	name: 'Favorite album',			type: 'text' },
						{ key: 'favorite_artist',	name: 'Favorite artist',		type: 'text' },
						{ key: 'rating',			name: 'Rating',					type: 'numeric_rating' },
						{ key: 'albumrating',		name: 'Album rating',			type: 'numeric_rating' },
						{ key: 'artistrating',		name: 'Artist rating',			type: 'numeric_rating' },
					]
				},
				{
					label: 'Play history',
					options: [
						{ key: 'played_times',		name: 'Played times',			type: 'numeric' },
						{ key: 'last_play',			name: 'Last played',			type: 'date' },
						{ key: 'recent_played',		name: 'Recently played',		type: 'numeric_limit' },
						{ key: 'myplayed',			name: 'Is played',				type: 'boolean' },
						{ key: 'myplayedalbum',		name: 'Is played album',		type: 'boolean' },
						{ key: 'myplayedartist',	name: 'Is played artist',		type: 'boolean' },
					]
				},
				{
					label: 'Playlist',
					options: [
						{ key: 'playlist',			name: 'Playlist',				type: 'playlist' },
						{ key: 'playlist_name',		name: 'Playlist name',			type: 'text' },
					]
				}
			],
			album: [
				{
					label: 'Album metadata',
					options: [
						{ key: 'title',				name: 'Name',					type: 'text' },
						{ key: 'artist',			name: 'Album artist name',		type: 'text' },
						{ key: 'song_artist',		name: 'Track artist name',		type: 'text' },
						{ key: 'year',				name: 'Year',					type: 'numeric' },
						{ key: 'time',				name: 'Duration (seconds)',		type: 'numeric' },
						{ key: 'song_count',		name: 'Song count',				type: 'numeric' },
						{ key: 'album_genre',		name: 'Album genre',			type: 'text' },
						{ key: 'song_genre',		name: 'Song genre',				type: 'text' },
						{ key: 'no_genre',			name: 'Has no genre',			type: 'boolean' },
						{ key: 'has_image',			name: 'Has image',				type: 'boolean' },
					]
				},
				{
					label: 'File data',
					options: [
						{ key: 'file',				name: 'File name',				type: 'text' },
						{ key: 'added',				name: 'Add date',				type: 'date' },
						{ key: 'updated',			name: 'Update date',			type: 'date' },
						{ key: 'recent_added',		name: 'Recently added',			type: 'numeric_limit' },
						{ key: 'recent_updated',	name: 'Recently updated',		type: 'numeric_limit' },
					]
				},
				{
					label: 'Rating',
					options: [
						{ key: 'favorite',			name: 'Favorite',				type: 'text' },
						{ key: 'rating',			name: 'Rating',					type: 'numeric_rating' },
						{ key: 'songrating',		name: 'Track rating',			type: 'numeric_rating' },
						{ key: 'artistrating',		name: 'Artist rating',			type: 'numeric_rating' },
					]
				},
				{
					label: 'Play history',
					options: [
						{ key: 'played_times',		name: 'Played times',			type: 'numeric' },
						{ key: 'last_play',			name: 'Last played',			type: 'date' },
						{ key: 'recent_played',		name: 'Recently played',		type: 'numeric_limit' },
						{ key: 'myplayed',			name: 'Is played',				type: 'boolean' },
						{ key: 'myplayedartist',	name: 'Is played artist',		type: 'boolean' },
					]
				},
				{
					label: 'Playlist',
					options: [
						{ key: 'playlist',			name: 'Playlist',				type: 'playlist' },
						{ key: 'playlist_name',		name: 'Playlist name',			type: 'text' },
					]
				}
			]
		};

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
			const rule = $scope.searchRuleTypes[$scope.entityType][0].options[0];
			const operator = $scope.searchRuleOperators[rule.type][0];
			$scope.searchRules.push({ rule: rule.key, operator: operator.key, input: '' });
		};
		$scope.addSearchRule();

		$scope.removeSearchRule = function(index) {
			$scope.searchRules.splice(index, 1);
		};

		$scope.onEntityTypeChanged = function() {
			// ensure the selected rules are valid for the current entity type
			const validRules = _($scope.searchRuleTypes[$scope.entityType]).map('options').flatten().map('key').value();
			for (let rule of $scope.searchRules) {
				if (!validRules.includes(rule.rule)) {
					rule.rule = validRules[0];
					$scope.onRuleChanged(rule);
				}
			}
			// ensure the selected ordering is valid for the current entity type
			const validOrders = $scope.availableOrders[$scope.entityType];
			if (!_.find(validOrders, { value: $scope.order })) {
				$scope.order = validOrders[0].value;
			}
		};

		$scope.onRuleChanged = function(rule) {
			// ensure the selected operator is valid for the current rule
			const validOperators = $scope.operatorsForRule(rule.rule);
			if (!_.find(validOperators, { key: rule.operator })) {
				rule.operator = validOperators[0].key;
			}
		};

		$scope.ruleType = function(ruleKey) {
			const rule = _($scope.searchRuleTypes[$scope.entityType]).map('options').flatten().find({ key: ruleKey });
			return rule?.type;
		};

		$scope.operatorsForRule = function(ruleKey) {
			return $scope.searchRuleOperators[$scope.ruleType(ruleKey)] ?? [];
		};

		$scope.search = function() {
			$scope.resultList = libraryService.setAdvancedSearchResult(null);
			$scope.errorDescription = null;

			const searchArgs = {
				entity: $scope.entityType,
				conjunction: $scope.conjunction,
				order: $scope.order,
				limit: $scope.maxResults || null,
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

		function play(tracks, startIndex = null) {
			const playlist = _.map(tracks, (track) => {
				return { track: track };
			});

			playlistService.setPlaylist('adv_search_results' + $scope.resultList.id, playlist, startIndex);
			playlistService.publish('play');
		}

		// Call playlistService to play all songs in the current playlist from the beginning
		$scope.onHeaderClick = play; // TODO

		$scope.getHeaderDraggable = function() {
			return { tracks: _.map($scope.resultList.tracks, 'id') };
		};

		$scope.resultCount = function() {
			const list = $scope.resultList;
			return list.tracks.length + list.albums.length;
		};

		$scope.onTrackClick = function(trackId) {
			// play/pause if currently playing list item clicked
			if ($scope.$parent.currentTrack && $scope.$parent.currentTrack.id === trackId) {
				playlistService.publish('togglePlayback');
			}
			// on any other list item, start playing the list from this item
			else {
				const index = _.findIndex($scope.resultList.tracks, { id: trackId });
				play($scope.resultList.tracks, index);
			}
		};

		$scope.getTrackData = function(listItem, index, _scope) {
			return {
				title: listItem.artist.name + ' - ' + listItem.title,
				tooltip: '',
				number: index + 1,
				id: listItem.id
			};
		};

		$scope.getTrackDraggable = function(trackId) {
			return { track: trackId };
		};

		$scope.onAlbumClick = function(albumId) {
			// TODO: play/pause if currently playing album clicked?
			const tracks = _($scope.resultList.albums).map('tracks').flatten().value();
			const album = _.find($scope.resultList.albums, { id: albumId });
			const index = _.findIndex(tracks, { id: album.tracks[0].id });
			play(tracks, index);
		};

		$scope.getAlbumData = function(listItem, index, _scope) {
			return {
				title: listItem.artist.name + ' - ' + listItem.name,
				tooltip: '',
				number: index + 1,
				id: listItem.id
			};
		};

		$scope.getAlbumDraggable = function(albumId) {
			return { album: albumId };
		};

		subscribe('scrollToTrack', function(_event, trackId) {
			if ($scope.$parent) {
				if ($scope.resultList?.tracks.length) {
					$scope.$parent.scrollToItem('track-' + trackId);
				} else if ($scope.resultList?.albums.length) {
					const track = libraryService.getTrack(trackId);
					if (track) {
						$scope.$parent.scrollToItem('track-' + track.album.id); // the prefix is 'track-' regardless of the actual entity type!
					}
				}
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
