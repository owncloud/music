<div id="album-details" class="sidebar-content" ng-controller="AlbumDetailsController" ng-if="contentType=='album'">

	<div class="albumart" ng-show="album.cover"></div>

	<h2 class="clickable" ng-click="showArtist()">{{album.artist.name}}<button class="icon-info"></button></h2>
	<h1>{{album.name}}</h1>

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
		<p ng-if="albumInfo"
			ng-init="truncated = (albumInfo.length > 400)"
			ng-class="{clickable: truncated, truncated: truncated}"
			ng-bind-html="albumInfo | limitTo:(truncated ? 365 : undefined)"
			ng-click="truncated = false; adjustFixedPositions()"
			title="{{ truncated ? ('Click to expand' | translate) : '' }}">
		</p>
		<dl>
			<dt ng-if="albumTags" translate>Tags</dt>
			<dd ng-if="albumTags" ng-bind-html="albumTags"></dd>
		</dl>
	</div>

</div>
