<div id="album-details" class="sidebar-content" ng-controller="AlbumDetailsController" ng-if="contentType=='album'">

	<div favorite-toggle entity="album" rest-prefix="'albums'"></div>
	<div class="albumart clickable" ng-click="scrollToEntity('album', album)"></div>

	<ul class="tabHeaders">
		<li class="tabHeader" ng-class="{selected: selectedTab=='info'}" ng-click="selectedTab='info'">
			<a translate>Info</a>
		</li>
		<li class="tabHeader" ng-class="{selected: selectedTab=='tracks'}" ng-click="selectedTab='tracks'">
			<a translate>Tracks</a>
		</li>
		<li class="tabHeader" ng-class="{selected: selectedTab=='artists'}" ng-click="selectedTab='artists'">
			<a translate>Artists</a>
		</li>
	</ul>

	<div class="tabsContainer" ng-show="!loading" ng-init="selectedTab='info'">
		<div class="tab" id="infoTabView" ng-show="selectedTab=='info'">

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

					<dt ng-if="mbid" translate>MusicBrainz</dt>
					<dd ng-if="mbid" ng-bind-html="mbid"></dd>
				</dl>
			</div>
		</div>

		<div class="tab playlist-area" id="tracksTabView" ng-if="album" ng-show="selectedTab=='tracks'">
			<h2 translate translate-params-album="album.name">Tracks on {{album}}</h2>
				<track-list
					tracks="album.tracks"
					get-track-data="getTrackData"
					play-track="onTrackClick"
					show-track-details="showTrackDetails"
					get-draggable="getTrackDraggable"
					track-id-prefix="'sidebar-track'"
					content-type="'song'"
				></track-list>
		</div>

		<div class="tab playlist-area" id="artistsTabView" ng-if="album" ng-show="selectedTab=='artists'">
			<h2 translate translate-params-album="album.name">Artists featured on {{album}}</h2>
			<track-list
				tracks="featuredArtists"
				get-track-data="getArtistData"
				play-track="onArtistClick"
				show-track-details="showArtistDetails"
				get-draggable="getArtistDraggable"
				track-id-prefix="'sidebar-artist'"
				content-type="'artist'"
			></track-list>
		</div>
	</div>

</div>
