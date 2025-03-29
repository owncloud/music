<div id="track-details" class="sidebar-content" ng-controller="TrackDetailsController" ng-if="contentType=='track'">

	<div favorite-toggle entity="track" rest-prefix="'tracks'"></div>
	<div class="albumart"></div>
	<a id="path" title="{{ 'Show in Files' | translate }}">{{ details.path }}</a>

	<ul class="tabHeaders" ng-show="details">
		<li class="tabHeader" ng-class="{selected: selectedTab=='general'}" ng-click="selectedTab='general'">
			<a translate>General</a>
		</li>
		<li class="tabHeader" ng-class="{selected: selectedTab=='lyrics'}" ng-click="selectedTab='lyrics'" ng-show="details.lyrics">
			<a translate>Lyrics</a>
		</li>
		<li class="tabHeader" ng-class="{selected: selectedTab=='technical'}" ng-click="selectedTab='technical'">
			<a translate>Technical</a>
		</li>
		<li class="tabHeader" ng-class="{selected: selectedTab=='lastfm'}" ng-click="selectedTab='lastfm'" ng-show="details.lastfm.track">
			<a>Last.fm</a>
		</li>
	</ul>

	<div class="tabsContainer" ng-show="details">

		<div class="tab" id="generalTabView" ng-show="selectedTab=='general'">
			<dl class="tags">
				<dt ng-repeat-start="tag in details.tags | orderBy:tagRank" ng-if="tag.value">{{ formatDetailName(tag.key) }}</dt>
				<dd ng-repeat-end ng-show="tag.value" ng-class="{clickable: tagHasDetails(tag)}" ng-click="showTagDetails(tag)"
				>{{ formatDetailValue(tag.value) }}<button class="icon-info" ng-if="tagHasDetails(tag)"></button></dd>
	
				<dt ng-if="details.length">length</dt>
				<dd ng-if="details.length">{{ details.length | playTime }}</dd>
			</dl>
		</div>

		<div class="tab" id="lyricsTabView" ng-show="selectedTab=='lyrics'">
			<div class="lyrics" ng-if="!details.lyrics.synced">{{ formatDetailValue(details.lyrics.unsynced) }}</div>
			<div class="lyrics" ng-if="details.lyrics.synced"
				ng-repeat="row in details.lyrics.synced" data-timestamp="{{row.time}}">{{row.text}}</div>
		</div>

		<div class="tab" id="technicalTabView" ng-show="selectedTab=='technical'">
			<dl class="fileinfo">
				<dt ng-repeat-start="info in details.fileinfo">{{ formatDetailName(info.key) }}</dt>
				<dd ng-repeat-end>{{ formatDetailValue(info.value) }}</dd>
			</dl>
		</div>

		<div class="tab" id="lastfm-info" ng-show="selectedTab=='lastfm'">
			<p ng-if="lastfmInfo" collapsible-html="lastfmInfo" on-expand="adjustFixedPositions"></p>
			<dl class="tags">
				<dt ng-if="lastfmArtist" translate>Artist</dt>
				<dd ng-if="lastfmArtist" ng-bind-html="lastfmArtist"></dd>

				<dt ng-if="lastfmAlbum" translate>Album</dt>
				<dd ng-if="lastfmAlbum" ng-bind-html="lastfmAlbum"></dd>

				<dt ng-if="lastfmTags" translate>Tags</dt>
				<dd ng-if="lastfmTags" ng-bind-html="lastfmTags"></dd>

				<dt ng-if="lastfmMbid" translate>MusicBrainz</dt>
				<dd ng-if="lastfmMbid" ng-bind-html="lastfmMbid"></dd>
			</dl>
		</div>
	</div>

	<div class="icon-loading" ng-if="!details"></div>
</div>
