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
			{ key: 'anywhere',			name: 'Any searchable text' },
			{ key: 'title',				name: 'Title' },
			{ key: 'favorite',			name: 'Favorite' },
			{ key: 'favorite_album',	name: 'Favorite album' },
			{ key: 'favorite_artist',	name: 'Favorite artist' },
//			{ key: 'rating',			name: 'Rating' },
//			{ key: 'albumrating',		name: 'Album rating' },
//			{ key: 'artistrating',		name: 'Artist rating' },
//			{ key: 'added',				name: 'Add date' },
//			{ key: 'updated',			name: 'Update date' },
//			{ key: 'recent_added',		name: 'Recently added' },
//			{ key: 'recent_updated',	name: 'Recently updated' },
			{ key: 'album',				name: 'Album name' },
			{ key: 'artist',			name: 'Artist name' },
			{ key: 'album_artist',		name: 'Album artist name' },
//			{ key: 'track',				name: 'Track number' },
//			{ key: 'year',				name: 'Year' },
//			{ key: 'played_times',		name: 'Played times' },
//			{ key: 'last_play',			name: 'Last played' },
//			{ key: 'recent_played',		name: 'Recently played' },
//			{ key: 'myplayed',			name: 'Is played' },
//			{ key: 'myplayedalbum',		name: 'Is played album' },
//			{ key: 'myplayedartist',	name: 'Is played artist' },
//			{ key: 'time',				name: 'Duration' },
			{ key: 'song_genre',		name: 'Song genre' },
			{ key: 'album_genre',		name: 'Album genre' },
			{ key: 'artist_genre',		name: 'Artist genre' },
//			{ key: 'no_genre',			name: 'Has no genre' },
//			{ key: 'playlist',			name: 'Playlist is' },
			{ key: 'playlist_name',		name: 'Playlist name' },
			{ key: 'file',				name: 'File name' },
		];

		$scope.searchRuleOperators = [
			{ key: 'contain',		name: 'Contains' },
			{ key: 'notcontain',	name: 'Does not contain' },
			{ key: 'start',			name: 'Starts with' },
			{ key: 'end',			name: 'Ends with' },
			{ key: 'is',			name: 'Is' },
			{ key: 'isnot',			name: 'Is not' },
			{ key: 'sounds',		name: 'Sounds like' },
			{ key: 'notsounds',		name: 'Does not sound like' },
			{ key: 'regexp',		name: 'Matches regular expression' },
			{ key: 'notregexp',		name: 'Does not match regular expression' },
//			{ key: 'true',			name: 'Is true' },
//			{ key: 'false',			name: 'Is false' },
//			{ key: 'equal',			name: 'Is equal to' },
//			{ key: 'ne',			name: 'Is not equal to' },
//			{ key: 'limit',			name: 'Limit' },
//			{ key: '>=',			name: 'Is greater than or equal to' },
//			{ key: '<=',			name: 'Is less than or equal to' },
//			{ key: '=',				name: 'Equals' },
//			{ key: '!=',			name: 'Does not equal' },
//			{ key: '>',				name: 'Is greater than' },
//			{ key: '<',				name: 'Is less than' },
		];

		$scope.resultTracks = null;
		$scope.errorDescription = null;
		let resultSetId = 0;

		$scope.searchRules = [
			{ rule: null, operator: null, input: null }
		];

		$scope.addSearchRule = function() {
			$scope.searchRules.push({ type: null, operator: null, input: null });
		};

		$scope.removeSearchRule = function(index) {
			$scope.searchRules.splice(index, 1);
		};

		$scope.search = function() {
			$scope.resultTracks = null;
			$scope.errorDescription = null;

			const searchArgs = {
				conjunction: 'and', // TODO: support 'or'
				rules: JSON.stringify($scope.searchRules)
			};

			Restangular.one('advanced_search').customPOST(searchArgs).then(
				(result) => {
					resultSetId++;
					$scope.resultTracks = libraryService.entriesForTrackIds(result);
				},
				(error) => {
					$scope.errorDescription = error.data.message;
				}
			);
		};

		function play(startIndex = null) {
			playlistService.setPlaylist('adv_search_results' + resultSetId, $scope.resultTracks, startIndex);
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
				let index = _.findIndex($scope.resultTracks, (i) => i.track.id == trackId);
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
