<div id="album-details" class="sidebar-content" ng-controller="AlbumDetailsController" ng-if="contentType=='album'">

	<div class="albumart clickable" ng-show="album.cover" ng-click="scrollToEntity('album', album)"></div>

	<h2 class="clickable" ng-click="showArtist()">{{album.artist.name}}<button class="icon-info"></button></h2>
	<h1 class="clickable" ng-click="scrollToEntity('album', album)">{{album.name}}</h1>

	<dl id="album-content-counts">
		<dt translate>Number of tracks</dt>
		<dd>{{ album.tracks.length }}</dd>

		<dt translate>Total length</dt>
		<dd>{{ totalLength | playTime }}</dd>
	</dl>

	<div id="lastfm-info" ng-show="lastfmInfo">
		<span class="missing-content" ng-if="!lastfmInfo.api_key_set"
			title="{{ 'Admin may set up the Last.fm API key to show album background information here. See the Settings view for details.' | translate }}"
			translate>(Last.fm has not been set up)</span>
		<span class="missing-content" ng-if="lastfmInfo.api_key_set && !lastfmInfo.connection_ok"
			title="{{ 'Problem connecting Last.fm. The API key may be invalid.' | translate }}"
			translate>(Failed to connect Last.fm)</span>
		<p ng-if="albumInfo" collapsible-html="albumInfo" on-expand="adjustFixedPositions"></p>
		<dl>
			<dt ng-if="albumTags" translate>Tags</dt>
			<dd ng-if="albumTags" ng-bind-html="albumTags"></dd>
		</dl>
	</div>

</div>
