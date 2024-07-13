<div class="view-container" id="adv-search-area">
	<h1 translate>Advanced search</h1>

	<div id="adv-search-controls">
		<div id="adv-search-common-parameters">
			<span translate>Search for</span>
			<select id="adv-search-type" ng-model="entityType" ng-change="onEntityTypeChanged()">
				<option value="track" translate>tracks</option>
				<option value="album" translate>albums</option>
				<option value="artist" translate>artists</option>
				<option value="playlist" translate>playlists</option>
				<option value="podcast_episode" translate>podcast episodes</option>
				<option value="podcast_channel" translate>podcast channels</option>
			</select>
			<select id="adv-search-conjunction" ng-model="conjunction">
				<option value="and" translate>matching all rules</option>
				<option value="or" translate>matching any rule</option>
			</select>
			<span translate>ordering results</span>
			<select id="adv-search-order" ng-model="order">
				<option ng-repeat="order in availableOrders[entityType]" ng-value="order.value">{{ order.text }}</option>
			</select>
			<span translate>limiting to</span>
			<select id="adv-search-limit" ng-model="maxResults">
				<option value="" translate>unlimited</option>
				<option value="10" translate>10 matches</option>
				<option value="30" translate>30 matches</option>
				<option value="100" translate>100 matches</option>
				<option value="500" translate>500 matches</option>
			</select>
		</div>
		<h2 translate>Rules</h2>
		<div id="adv-search-rules">
			<div class="adv-search-rule-row" ng-repeat="rule in searchRules" on-enter="search()">
				<select ng-model="rule.rule" ng-change="onRuleChanged(rule)">
					<option ng-if="!searchRuleTypes[entityType][0].label" ng-repeat="ruleType in searchRuleTypes[entityType][0].options" ng-value="ruleType.key">{{ ruleType.name }}</option>
					<optgroup ng-repeat="category in searchRuleTypes[entityType]" label="{{ category.label }}" ng-if="category.label">
						<option ng-repeat="ruleType in category.options" ng-value="ruleType.key">{{ ruleType.name }}</option>
					</optgroup>
				</select>

				<select ng-model="rule.operator">
					<option ng-repeat="ruleOp in operatorsForRule(rule.rule)" ng-value="ruleOp.key">{{ ruleOp.name }}</option>
				</select>

				<input ng-if="ruleType(rule.rule) == 'text'" type="text" ng-model="rule.input"/>
				<input ng-if="['numeric', 'numeric_limit'].includes(ruleType(rule.rule))" type="number" ng-model="rule.input"/>
				<input ng-if="ruleType(rule.rule) == 'date'" type="date" ng-model="rule.input"/>
				<select ng-if="ruleType(rule.rule) == 'numeric_rating'" ng-model="rule.input">
					<option ng-repeat="val in [0,1,2,3,4,5]" ng-value="val">{{ val }} Stars</option>
				</select>
				<select ng-if="ruleType(rule.rule) == 'playlist'" ng-model="rule.input">
					<option ng-repeat="pl in playlists" ng-value="pl.id">{{ pl.name }}</option>
				</select>

				<a class="icon icon-close" ng-click="removeSearchRule($index)"></a>
			</div>
			<div class="add-row clickable" ng-click="addSearchRule()">
				<a class="icon icon-add"></a>
			</div>
		</div>
		<button ng-click="search()" translate>Search</button><span style="color:red" ng-show="errorDescription">{{ errorDescription }}</span>
	</div>

	<div ng-if="results" class="flat-list-view playlist-area">
		<h2 ui-draggable="true" drag="getHeaderDraggable()">
			<span ng-class="{ clickable: resultCount() }" ng-click="onHeaderClick()">
				<span translate translate-n="resultCount()" translate-plural="{{ resultCount() }} results">{{ resultCount() }} result</span>
				<img ng-if="resultCount()" class="play svg" alt="{{ 'Play' | translate }}"
					src="<?php \OCA\Music\Utility\HtmlUtil::printSvgPath('play-big') ?>"/>
			</span>
		</h2>
		<track-list
			tracks="results.tracks"
			get-track-data="getTrackData"
			play-track="onTrackClick"
			show-track-details="showTrackDetails"
			get-draggable="getTrackDraggable"
		></track-list>
		<track-list
			tracks="results.albums"
			get-track-data="getAlbumData"
			play-track="onAlbumClick"
			show-track-details="showAlbumDetails"
			get-draggable="getAlbumDraggable"
			track-id-prefix="'album'"
			content-type="'album'"
		></track-list>
		<track-list
			tracks="results.artists"
			get-track-data="getArtistData"
			play-track="onArtistClick"
			show-track-details="showArtistDetails"
			get-draggable="getArtistDraggable"
			track-id-prefix="'artist'"
			content-type="'artist'"
		></track-list>
		<track-list
			tracks="results.playlists"
			get-track-data="getPlaylistData"
			play-track="onPlaylistClick"
			show-track-details="showPlaylistDetails"
			get-draggable="getPlaylistDraggable"
			track-id-prefix="'playlist'"
			content-type="'playlist'"
		></track-list>
		<track-list
			tracks="results.podcastEpisodes"
			get-track-data="getPodcastEpisodeData"
			play-track="onPodcastEpisodeClick"
			show-track-details="showPodcastEpisodeDetails"
			track-id-prefix="'podcast-episode'"
			content-type="'podcast'"
		></track-list>
		<track-list
			tracks="results.podcastChannels"
			get-track-data="getPodcastChannelData"
			play-track="onPodcastChannelClick"
			show-track-details="showPodcastChannelDetails"
			track-id-prefix="'podcast-channel'"
			content-type="'podcast-channel'"
		></track-list>
	</div>
</div>
