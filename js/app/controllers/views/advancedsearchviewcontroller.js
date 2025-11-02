/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2024, 2025
 */

angular.module('Music').controller('AdvancedSearchViewController', [
	'$rootScope', '$scope', '$document', 'libraryService', 'playQueueService', '$timeout', 'Restangular', 'gettextCatalog',
	function ($rootScope, $scope, $document, libraryService, playQueueService, $timeout, Restangular, gettextCatalog) {

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
		$scope.showResultsMenu = false;

		$scope.onResultsContextMenuButton = function(event) {
			$scope.showResultsMenu = !$scope.showResultsMenu;
			event.stopPropagation();
		};

		// hide the results context menu when user clicks anywhere on the page
		$document.click(function(_event) {
			$timeout(() => $scope.showResultsMenu = false);
		});

		$scope.availableOrders = {
			track: [
				{ value: 'name',		text: gettextCatalog.getString('by name') },
				{ value: 'parent',		text: gettextCatalog.getString('by artist') },
				{ value: 'newest',		text: gettextCatalog.getString('by time added') },
				{ value: 'play_count',	text: gettextCatalog.getString('by play count') },
				{ value: 'last_played',	text: gettextCatalog.getString('by recent play') },
				{ value: 'rating',		text: gettextCatalog.getString('by rating') },
				{ value: 'random',		text: gettextCatalog.getString('randomly') },
			],
			album: [
				{ value: 'name',		text: gettextCatalog.getString('by name') },
				{ value: 'parent',		text: gettextCatalog.getString('by artist') },
				{ value: 'newest',		text: gettextCatalog.getString('by time added') },
				{ value: 'rating',		text: gettextCatalog.getString('by rating') },
				{ value: 'random',		text: gettextCatalog.getString('randomly') },
			],
			artist: [
				{ value: 'name',		text: gettextCatalog.getString('by name') },
				{ value: 'newest',		text: gettextCatalog.getString('by time added') },
				{ value: 'rating',		text: gettextCatalog.getString('by rating') },
				{ value: 'random',		text: gettextCatalog.getString('randomly') },
			],
			playlist: [
				{ value: 'name',		text: gettextCatalog.getString('by name') },
				{ value: 'newest',		text: gettextCatalog.getString('by time added') },
				{ value: 'random',		text: gettextCatalog.getString('randomly') },
			],
			podcast_episode: [
				{ value: 'name',		text: gettextCatalog.getString('by name') },
				{ value: 'parent',		text: gettextCatalog.getString('by channel') },
				{ value: 'newest',		text: gettextCatalog.getString('by time added') },
				{ value: 'rating',		text: gettextCatalog.getString('by rating') },
				{ value: 'random',		text: gettextCatalog.getString('randomly') },
			],
			podcast_channel: [
				{ value: 'name',		text: gettextCatalog.getString('by name') },
				{ value: 'newest',		text: gettextCatalog.getString('by time added') },
				{ value: 'rating',		text: gettextCatalog.getString('by rating') },
				{ value: 'random',		text: gettextCatalog.getString('randomly') },
			],
			radio_station: [
				{ value: 'name',		text: gettextCatalog.getString('by name') },
				{ value: 'newest',		text: gettextCatalog.getString('by time added') },
				{ value: 'random',		text: gettextCatalog.getString('randomly') },
			],
		};

		$scope.searchRuleTypes = {
			track: [
				{
					label: null,
					options: [
						{ key: 'anywhere',			name: gettextCatalog.getString('Any searchable text'),	type: 'text' },
					]
				},
				{
					label: gettextCatalog.getString('Track metadata'),
					options: [
						{ key: 'title',				name: gettextCatalog.getString('Name'),					type: 'text' },
						{ key: 'album',				name: gettextCatalog.getString('Album name'),			type: 'text' },
						{ key: 'artist',			name: gettextCatalog.getString('Artist name'),			type: 'text' },
						{ key: 'album_artist',		name: gettextCatalog.getString('Album artist name'),	type: 'text' },
						{ key: 'track',				name: gettextCatalog.getString('Track number'),			type: 'numeric' },
						{ key: 'year',				name: gettextCatalog.getString('Year'),					type: 'numeric' },
						{ key: 'time',				name: gettextCatalog.getString('Duration (seconds)'),	type: 'numeric' },
						{ key: 'bitrate',			name: gettextCatalog.getString('Bit rate'),				type: 'numeric' },
						{ key: 'song_genre',		name: gettextCatalog.getString('Track genre'),			type: 'text' },
						{ key: 'album_genre',		name: gettextCatalog.getString('Album genre'),			type: 'text' },
						{ key: 'artist_genre',		name: gettextCatalog.getString('Artist genre'),			type: 'text' },
						{ key: 'no_genre',			name: gettextCatalog.getString('Has no genre'),			type: 'boolean' },
					]
				},
				{
					label: gettextCatalog.getString('File data'),
					options: [
						{ key: 'file',				name: gettextCatalog.getString('File name'),			type: 'text' },
						{ key: 'added',				name: gettextCatalog.getString('Add date'),				type: 'date' },
						{ key: 'updated',			name: gettextCatalog.getString('Update date'),			type: 'date' },
						{ key: 'recent_added',		name: gettextCatalog.getString('Recently added'),		type: 'numeric_limit' },
						{ key: 'recent_updated',	name: gettextCatalog.getString('Recently updated'),		type: 'numeric_limit' },
					]
				},
				{
					label: gettextCatalog.getString('Rating'),
					options: [
						{ key: 'favorite',			name: gettextCatalog.getString('Favorite'),				type: 'text' },
						{ key: 'favorite_album',	name: gettextCatalog.getString('Favorite album'),		type: 'text' },
						{ key: 'favorite_artist',	name: gettextCatalog.getString('Favorite artist'),		type: 'text' },
						{ key: 'rating',			name: gettextCatalog.getString('Rating'),				type: 'numeric_rating' },
						{ key: 'albumrating',		name: gettextCatalog.getString('Album rating'),			type: 'numeric_rating' },
						{ key: 'artistrating',		name: gettextCatalog.getString('Artist rating'),		type: 'numeric_rating' },
					]
				},
				{
					label: gettextCatalog.getString('Play history'),
					options: [
						{ key: 'played_times',		name: gettextCatalog.getString('Played times'),			type: 'numeric' },
						{ key: 'last_play',			name: gettextCatalog.getString('Last played'),			type: 'date' },
						{ key: 'recent_played',		name: gettextCatalog.getString('Recently played'),		type: 'numeric_limit' },
						{ key: 'myplayed',			name: gettextCatalog.getString('Is played'),			type: 'boolean' },
						{ key: 'myplayedalbum',		name: gettextCatalog.getString('Is played album'),		type: 'boolean' },
						{ key: 'myplayedartist',	name: gettextCatalog.getString('Is played artist'),		type: 'boolean' },
					]
				},
				{
					label: gettextCatalog.getString('Playlist'),
					options: [
						{ key: 'playlist',			name: gettextCatalog.getString('Playlist'),				type: 'playlist' },
						{ key: 'playlist_name',		name: gettextCatalog.getString('Playlist name'),		type: 'text' },
					]
				}
			],
			album: [
				{
					label: gettextCatalog.getString('Album metadata'),
					options: [
						{ key: 'title',				name: gettextCatalog.getString('Name'),					type: 'text' },
						{ key: 'artist',			name: gettextCatalog.getString('Album artist name'),	type: 'text' },
						{ key: 'song_artist',		name: gettextCatalog.getString('Track artist name'),	type: 'text' },
						{ key: 'song',				name: gettextCatalog.getString('Track name'),			type: 'text' },
						{ key: 'year',				name: gettextCatalog.getString('Year'),					type: 'numeric' },
						{ key: 'time',				name: gettextCatalog.getString('Duration (seconds)'),	type: 'numeric' },
						{ key: 'song_count',		name: gettextCatalog.getString('Track count'),			type: 'numeric' },
						{ key: 'disk_count',		name: gettextCatalog.getString('Disk count'),			type: 'numeric' },
						{ key: 'album_genre',		name: gettextCatalog.getString('Album genre'),			type: 'text' },
						{ key: 'song_genre',		name: gettextCatalog.getString('Track genre'),			type: 'text' },
						{ key: 'no_genre',			name: gettextCatalog.getString('Has no genre'),			type: 'boolean' },
						{ key: 'has_image',			name: gettextCatalog.getString('Has image'),			type: 'boolean' },
					]
				},
				{
					label: gettextCatalog.getString('File data'),
					options: [
						{ key: 'file',				name: gettextCatalog.getString('File name'),			type: 'text' },
						{ key: 'added',				name: gettextCatalog.getString('Add date'),				type: 'date' },
						{ key: 'updated',			name: gettextCatalog.getString('Update date'),			type: 'date' },
						{ key: 'recent_added',		name: gettextCatalog.getString('Recently added'),		type: 'numeric_limit' },
						{ key: 'recent_updated',	name: gettextCatalog.getString('Recently updated'),		type: 'numeric_limit' },
					]
				},
				{
					label: gettextCatalog.getString('Rating'),
					options: [
						{ key: 'favorite',			name: gettextCatalog.getString('Favorite'),				type: 'text' },
						{ key: 'rating',			name: gettextCatalog.getString('Rating'),				type: 'numeric_rating' },
						{ key: 'songrating',		name: gettextCatalog.getString('Track rating'),			type: 'numeric_rating' },
						{ key: 'artistrating',		name: gettextCatalog.getString('Artist rating'),		type: 'numeric_rating' },
					]
				},
				{
					label: gettextCatalog.getString('Play history'),
					options: [
						{ key: 'played_times',		name: gettextCatalog.getString('Played times'),			type: 'numeric' },
						{ key: 'last_play',			name: gettextCatalog.getString('Last played'),			type: 'date' },
						{ key: 'recent_played',		name: gettextCatalog.getString('Recently played'),		type: 'numeric_limit' },
						{ key: 'myplayed',			name: gettextCatalog.getString('Is played'),			type: 'boolean' },
						{ key: 'myplayedartist',	name: gettextCatalog.getString('Is played artist'),		type: 'boolean' },
					]
				},
				{
					label: gettextCatalog.getString('Playlist'),
					options: [
						{ key: 'playlist',			name: gettextCatalog.getString('Playlist'),				type: 'playlist' },
						{ key: 'playlist_name',		name: gettextCatalog.getString('Playlist name'),		type: 'text' },
					]
				},
			],
			artist: [
				{
					label: gettextCatalog.getString('Artist metadata'),
					options: [
						{ key: 'title',				name: gettextCatalog.getString('Name'),					type: 'text' },
						{ key: 'album',				name: gettextCatalog.getString('Album name'),			type: 'text' },
						{ key: 'song',				name: gettextCatalog.getString('Track name'),			type: 'text' },
						{ key: 'time',				name: gettextCatalog.getString('Duration (seconds)'),	type: 'numeric' },
						{ key: 'album_count',		name: gettextCatalog.getString('Album count'),			type: 'numeric' },
						{ key: 'song_count',		name: gettextCatalog.getString('Track count'),			type: 'numeric' },
						{ key: 'genre',				name: gettextCatalog.getString('Artist genre'),			type: 'text' },
						{ key: 'song_genre',		name: gettextCatalog.getString('Track genre'),			type: 'text' },
						{ key: 'no_genre',			name: gettextCatalog.getString('Has no genre'),			type: 'boolean' },
						{ key: 'has_image',			name: gettextCatalog.getString('Has image'),			type: 'boolean' },
					]
				},
				{
					label: gettextCatalog.getString('File data'),
					options: [
						{ key: 'file',				name: gettextCatalog.getString('File name'),			type: 'text' },
						{ key: 'added',				name: gettextCatalog.getString('Add date'),				type: 'date' },
						{ key: 'updated',			name: gettextCatalog.getString('Update date'),			type: 'date' },
						{ key: 'recent_added',		name: gettextCatalog.getString('Recently added'),		type: 'numeric_limit' },
						{ key: 'recent_updated',	name: gettextCatalog.getString('Recently updated'),		type: 'numeric_limit' },
					]
				},
				{
					label: gettextCatalog.getString('Rating'),
					options: [
						{ key: 'favorite',			name: gettextCatalog.getString('Favorite'),				type: 'text' },
						{ key: 'rating',			name: gettextCatalog.getString('Rating'),				type: 'numeric_rating' },
						{ key: 'songrating',		name: gettextCatalog.getString('Track rating'),			type: 'numeric_rating' },
						{ key: 'albumrating',		name: gettextCatalog.getString('Album rating'),			type: 'numeric_rating' },
					]
				},
				{
					label: gettextCatalog.getString('Play history'),
					options: [
						{ key: 'played_times',		name: gettextCatalog.getString('Played times'),			type: 'numeric' },
						{ key: 'last_play',			name: gettextCatalog.getString('Last played'),			type: 'date' },
						{ key: 'recent_played',		name: gettextCatalog.getString('Recently played'),		type: 'numeric_limit' },
						{ key: 'myplayed',			name: gettextCatalog.getString('Is played'),			type: 'boolean' },
					]
				},
				{
					label: gettextCatalog.getString('Playlist'),
					options: [
						{ key: 'playlist',			name: gettextCatalog.getString('Playlist'),				type: 'playlist' },
						{ key: 'playlist_name',		name: gettextCatalog.getString('Playlist name'),		type: 'text' },
					]
				},
			],
			playlist: [
				{
					label: null,
					options: [
						{ key: 'title',				name: gettextCatalog.getString('Name'),					type: 'text' },
						{ key: 'added',				name: gettextCatalog.getString('Add date'),				type: 'date' },
						{ key: 'updated',			name: gettextCatalog.getString('Update date'),			type: 'date' },
						{ key: 'recent_added',		name: gettextCatalog.getString('Recently added'),		type: 'numeric_limit' },
						{ key: 'recent_updated',	name: gettextCatalog.getString('Recently updated'),		type: 'numeric_limit' },
						{ key: 'favorite',			name: gettextCatalog.getString('Favorite'),				type: 'text' },
					]
				},
			],
			podcast_episode: [
				{
					label: gettextCatalog.getString('Podcast metadata'),
					options: [
						{ key: 'title',				name: gettextCatalog.getString('Name'),					type: 'text' },
						{ key: 'podcast',			name: gettextCatalog.getString('Podcast channel'),		type: 'text' },
						{ key: 'time',				name: gettextCatalog.getString('Duration (seconds)'),	type: 'numeric' },
					]
				},
				{
					label: gettextCatalog.getString('History'),
					options: [
						{ key: 'pubdate',			name: gettextCatalog.getString('Date published'),		type: 'date' },
						{ key: 'added',				name: gettextCatalog.getString('Add date'),				type: 'date' },
						{ key: 'updated',			name: gettextCatalog.getString('Update date'),			type: 'date' },
						{ key: 'recent_added',		name: gettextCatalog.getString('Recently added'),		type: 'numeric_limit' },
						{ key: 'recent_updated',	name: gettextCatalog.getString('Recently updated'),		type: 'numeric_limit' },
					]
				},
				{
					label: gettextCatalog.getString('Rating'),
					options: [
						{ key: 'favorite',			name: gettextCatalog.getString('Favorite'),				type: 'text' },
						{ key: 'rating',			name: gettextCatalog.getString('Rating'),				type: 'numeric_rating' },
					]
				},
			],
			podcast_channel: [
				{
					label: gettextCatalog.getString('Podcast metadata'),
					options: [
						{ key: 'title',				name: gettextCatalog.getString('Name'),					type: 'text' },
						{ key: 'podcast_episode',	name: gettextCatalog.getString('Podcast episode'),		type: 'text' },
						{ key: 'time',				name: gettextCatalog.getString('Duration (seconds)'),	type: 'numeric' },
					]
				},
				{
					label: gettextCatalog.getString('History'),
					options: [
						{ key: 'pubdate',			name: gettextCatalog.getString('Date published'),		type: 'date' },
						{ key: 'added',				name: gettextCatalog.getString('Add date'),				type: 'date' },
						{ key: 'updated',			name: gettextCatalog.getString('Update date'),			type: 'date' },
						{ key: 'recent_added',		name: gettextCatalog.getString('Recently added'),		type: 'numeric_limit' },
						{ key: 'recent_updated',	name: gettextCatalog.getString('Recently updated'),		type: 'numeric_limit' },
					]
				},
				{
					label: gettextCatalog.getString('Rating'),
					options: [
						{ key: 'favorite',			name: gettextCatalog.getString('Favorite'),				type: 'text' },
						{ key: 'rating',			name: gettextCatalog.getString('Rating'),				type: 'numeric_rating' },
					]
				},
			],
			radio_station: [
				{
					label: null,
					options: [
						{ key: 'title',				name: gettextCatalog.getString('Name'),					type: 'text' },
						{ key: 'stream_url',		name: gettextCatalog.getString('Stream URL'),			type: 'text' },
						{ key: 'added',				name: gettextCatalog.getString('Add date'),				type: 'date' },
						{ key: 'updated',			name: gettextCatalog.getString('Update date'),			type: 'date' },
						{ key: 'recent_added',		name: gettextCatalog.getString('Recently added'),		type: 'numeric_limit' },
						{ key: 'recent_updated',	name: gettextCatalog.getString('Recently updated'),		type: 'numeric_limit' },
					]
				},
			],
		};

		$scope.searchRuleOperators = {
			text: [
				{ key: 'contain',		name: gettextCatalog.getString('Contains') },
				{ key: 'notcontain',	name: gettextCatalog.getString('Does not contain') },
				{ key: 'start',			name: gettextCatalog.getString('Starts with') },
				{ key: 'end',			name: gettextCatalog.getString('Ends with') },
				{ key: 'is',			name: gettextCatalog.getString('Is') },
				{ key: 'isnot',			name: gettextCatalog.getString('Is not') },
				{ key: 'sounds',		name: gettextCatalog.getString('Sounds like') },
				{ key: 'notsounds',		name: gettextCatalog.getString('Does not sound like') },
				{ key: 'regexp',		name: gettextCatalog.getString('Matches regular expression') },
				{ key: 'notregexp',		name: gettextCatalog.getString('Does not match regular expression') },
			],
			numeric: [
				{ key: '>=',			name: gettextCatalog.getString('Is greater than or equal to') },
				{ key: '<=',			name: gettextCatalog.getString('Is less than or equal to') },
				{ key: '=',				name: gettextCatalog.getString('Equals') },
				{ key: '!=',			name: gettextCatalog.getString('Does not equal') },
				{ key: '>',				name: gettextCatalog.getString('Is greater than') },
				{ key: '<',				name: gettextCatalog.getString('Is less than') },
			],
			numeric_limit: [
				{ key: 'limit',			name: gettextCatalog.getString('Limit') },
			],
			boolean: [
				{ key: 'true',			name: gettextCatalog.getString('Is true') },
				{ key: 'false',			name: gettextCatalog.getString('Is false') },
			],
			date: [
				{ key: 'before',		name: gettextCatalog.getString('Before') },
				{ key: 'after',			name: gettextCatalog.getString('After') },
			],
			playlist: [
				{ key: 'equal',			name: gettextCatalog.getString('Is equal to') },
				{ key: 'ne',			name: gettextCatalog.getString('Is not equal to') },
			]
		};
		$scope.searchRuleOperators.numeric_rating = $scope.searchRuleOperators.numeric; // use the same operators

		$scope.results = libraryService.getAdvancedSearchResult();
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
			$scope.results = libraryService.setAdvancedSearchResult(null);
			$scope.errorDescription = null;
			$scope.showResultsMenu = false;

			const searchArgs = {
				entity: $scope.entityType,
				conjunction: $scope.conjunction,
				order: $scope.order,
				limit: $scope.maxResults || null,
				rules: angular.toJson($scope.searchRules)
			};

			Restangular.one('advanced_search').customPOST(searchArgs).then(
				(result) => {
					$scope.results = libraryService.setAdvancedSearchResult(result);
				},
				(error) => {
					$scope.errorDescription = error.data.message;
				}
			);
		};

		$scope.saveResults = function() {
			const trackIds = _.map(getTracksFromResult(), 'id');

			const args = {
				name: gettextCatalog.getString('Search {{ datetime }}', { datetime: OCA.Music.Utils.formatDateTime($scope.results.date) }),
				trackIds: trackIds.join(','),
				comment: gettextCatalog.getString('Search criteria: {{ params }}', { params: angular.toJson(_.omitBy($scope.results.criteria, _.isNil), 2) })
			};
			Restangular.all('playlists').post(args).then(playlist => {
				libraryService.addPlaylist(playlist);
				$timeout(() => $scope.navigateTo(`#playlist/${playlist.id}`));
			});
		};

		function play(tracks, startIndex = null) {
			const playlist = _.map(tracks, (track) => {
				return { track: track };
			});

			playQueueService.setPlaylist('adv_search_results' + $scope.results.id, playlist, startIndex);
			playQueueService.publish('play');
		}

		function getTracksFromResult() {
			const trackResults = $scope.results.tracks;
			const tracksFromAlbums = _($scope.results.albums).map('tracks').flatten().value();
			const tracksFromArtists = _($scope.results.artists).map(a => libraryService.findTracksByArtist(a.id)).flatten().value();
			const tracksFromPlaylists = _($scope.results.playlists).map('tracks').flatten().map('track').value();
			const episodeResults = $scope.results.podcastEpisodes;
			const episodesFromChannels = _($scope.results.podcastChannels).map('episodes').flatten().value();
			const radioResults = $scope.results.radioStations;
			return [].concat(trackResults, tracksFromAlbums, tracksFromArtists, tracksFromPlaylists, episodeResults, episodesFromChannels, radioResults);
		}

		// Call playQueueService to play all songs in the current playlist from the beginning
		$scope.onHeaderClick = function() {
			if ($scope.resultCount()) {
				play(getTracksFromResult());
			}
		};

		$scope.getHeaderDraggable = function() {
			const tracks = _.filter(getTracksFromResult(), {type: 'song'});
			return { tracks: _.map(tracks, 'id') };
		};

		$scope.resultCount = function() {
			const res = $scope.results;
			return res.tracks.length + res.albums.length + res.artists.length + res.playlists.length
					+ res.podcastEpisodes.length + res.podcastChannels.length + res.radioStations.length;
		};

		/** Results which may be saved to a playlist */
		$scope.saveableResultCount = function() {
			const res = $scope.results;
			return res.tracks.length + res.albums.length + res.artists.length + res.playlists.length;
		};

		$scope.onTrackClick = function(trackId) {
			// play/pause if currently playing list item clicked
			const currentTrack = $scope.$parent.currentTrack;
			if (currentTrack && currentTrack.id === trackId && currentTrack.type == 'song') {
				playQueueService.publish('togglePlayback');
			}
			// on any other list item, start playing the list from this item
			else {
				const index = _.findIndex($scope.results.tracks, { id: trackId });
				play($scope.results.tracks, index);
			}
		};

		$scope.getTrackData = function(listItem, index, _scope) {
			return {
				title: listItem.title,
				title2: listItem.artist.name,
				tooltip: listItem.title,
				tooltip2: listItem.artist.name,
				number: index + 1,
				id: listItem.id,
				art: listItem.album
			};
		};

		$scope.getTrackDraggable = function(trackId) {
			return { track: trackId };
		};

		$scope.onAlbumClick = function(albumId) {
			// TODO: play/pause if currently playing album clicked?
			const tracks = getTracksFromResult();
			const album = _.find($scope.results.albums, { id: albumId });
			const index = _.findIndex(tracks, { id: album.tracks[0].id });
			play(tracks, index);
		};

		$scope.getAlbumData = function(listItem, index, _scope) {
			return {
				title: listItem.name,
				title2: listItem.artist.name,
				tooltip: listItem.name,
				tooltip2: listItem.artist.name,
				number: index + 1,
				id: listItem.id,
				art: listItem
			};
		};

		$scope.getAlbumDraggable = function(albumId) {
			return { album: albumId };
		};

		$scope.onArtistClick = function(artistId) {
			// TODO: play/pause if currently playing artist clicked?
			const tracks = getTracksFromResult();
			const artistTracks = libraryService.findTracksByArtist(artistId);
			const index = _.findIndex(tracks, { id: artistTracks[0].id });
			play(tracks, index);
		};

		$scope.getArtistData = function(listItem, index, _scope) {
			return {
				title: listItem.name,
				tooltip: listItem.name,
				number: index + 1,
				id: listItem.id,
				art: listItem
			};
		};

		$scope.getArtistDraggable = function(artistId) {
			return { artist: artistId };
		};

		$scope.onPlaylistClick = function(playlistId) {
			// TODO: play/pause if currently playing playlist clicked?
			playQueueService.setPlaylist('playlist-' + playlistId, libraryService.getPlaylist(playlistId).tracks);
			playQueueService.publish('play', '#/playlist/' + playlistId);
		};

		$scope.getPlaylistData = function(listItem, index, _scope) {
			return {
				title: listItem.name,
				title2: $scope.trackCountText(listItem),
				tooltip: listItem.name,
				number: index + 1,
				id: listItem.id,
				art: listItem
			};
		};

		$scope.getPlaylistDraggable = function(playlistId) {
			return { playlist: playlistId };
		};
		
		$scope.onPodcastEpisodeClick = function(episodeId) {
			const currentTrack = $scope.$parent.currentTrack;
			if (currentTrack && currentTrack.id === episodeId && currentTrack.type == 'podcast') {
				playQueueService.publish('togglePlayback');
			}
			// on any other list item, start playing the list from this item
			else {
				const index = _.findIndex($scope.results.podcastEpisodes, { id: episodeId });
				play($scope.results.podcastEpisodes, index);
			}
		};

		$scope.getPodcastEpisodeData = function(listItem, index, _scope) {
			return {
				title: listItem.title,
				title2: listItem.channel.title,
				tooltip: listItem.title,
				tooltip2: listItem.channel.title,
				number: index + 1,
				id: listItem.id,
				art: listItem.channel
			};
		};
		
		$scope.onPodcastChannelClick = function(channelId) {
			// TODO: play/pause if currently playing channel clicked?
			const episodes = getTracksFromResult();
			const channel = _.find($scope.results.podcastChannels, { id: channelId });
			const index = _.findIndex(episodes, { id: channel.episodes[0].id });
			play(episodes, index);
		};

		$scope.getPodcastChannelData = function(listItem, index, _scope) {
			return {
				title: listItem.title,
				tooltip: listItem.title,
				number: index + 1,
				id: listItem.id,
				art: listItem
			};
		};

		$scope.onRadioStationClick = function(stationId) {
			const currentTrack = $scope.$parent.currentTrack;
			if (currentTrack && currentTrack.id === stationId && currentTrack.type == 'radio') {
				playQueueService.publish('togglePlayback');
			}
			// on any other list item, start playing the list from this item
			else {
				const index = _.findIndex($scope.results.radioStations, { id: stationId });
				play($scope.results.radioStations, index);
			}
		};

		$scope.getRadioStationData = function(listItem, index, _scope) {
			return {
				title: listItem.name,
				title2: listItem.stream_url,
				tooltip: listItem.name,
				tooltip2: listItem.stream_url,
				number: index + 1,
				id: listItem.id,
				art: listItem
			};
		};

		subscribe('scrollToTrack', function(_event, trackId) {
			if ($scope.$parent) {
				if ($scope.results?.tracks.length) {
					$scope.$parent.scrollToItem('track-' + trackId);
				} else if ($scope.results?.albums.length) {
					const track = libraryService.getTrack(trackId);
					if (track) {
						$scope.$parent.scrollToItem('album-' + track.album.id);
					}
				} else if ($scope.results?.artists.length) {
					const track = libraryService.getTrack(trackId);
					if (track) {
						$scope.$parent.scrollToItem('artist-' + track.artist.id);
					}
				}
			}
		});

		subscribe('scrollToPodcastEpisode', function(_event, episodeId) {
			if ($scope.$parent) {
				if ($scope.results?.podcastEpisodes.length) {
					$scope.$parent.scrollToItem('podcast-episode-' + episodeId);
				} else if ($scope.results?.podcastChannels.length) {
					const episode = libraryService.getPodcastEpisode(episodeId);
					if (episode) {
						$scope.$parent.scrollToItem('podcast-channel-' + episode.channel.id);
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
