<div id="artist-details" class="sidebar-content" ng-controller="ArtistDetailsController" ng-if="contentType=='artist'">

	<div favorite-toggle entity="artist" rest-prefix="'artists'"></div>
	<div class="albumart clickable" ng-show="!loading" ng-click="scrollToEntity('artist', artist)">
		<span ng-if="!artAvailable" title="{{ noImageHint }}"
			translate>(no artist image available)</span>
	</div>

	<ul class="tabHeaders" ng-show="!loading">
		<li class="tabHeader" ng-class="{selected: selectedTab=='info'}" ng-click="selectedTab='info'">
			<a translate>Info</a>
		</li>
		<li class="tabHeader" ng-class="{selected: selectedTab=='albums'}" ng-click="selectedTab='albums'">
			<a translate>Albums</a>
		</li>
		<li class="tabHeader" ng-class="{selected: selectedTab=='tracks'}" ng-click="selectedTab='tracks'" ng-show="artistTracks.length">
			<a translate>Tracks</a>
		</li>
	</ul>

	<div class="tabsContainer" ng-show="!loading" ng-init="selectedTab='info'">
		<div class="tab" id="infoTabView" ng-show="selectedTab=='info'">
			<h1 class="clickable" ng-show="!loading" ng-click="scrollToEntity('artist', artist)">{{artist.name}}</h1>
			<dl id="artist-content-counts" ng-show="!loading">
				<dt translate>Number of albums</dt>
				<dd>{{ artist.albums.length }}</dd>

				<dt translate>Number of tracks on albums</dt>
				<dd>{{ artistAlbumTrackCount }}</dd>

				<dt translate>Number of performed tracks</dt>
				<dd>{{ artistTracks.length }}</dd>
			</dl>

			<div id="lastfm-info" ng-show="!loading && lastfmInfo">
				<span class="missing-content" ng-if="!lastfmInfo.api_key_set"
					title="{{ 'Admin may set up the Last.fm API key to show artist biography here. See the Settings view for details.' | translate }}"
					translate>(Last.fm has not been set up)</span>
				<span class="missing-content" ng-if="lastfmInfo.api_key_set && !lastfmInfo.connection_ok"
					title="{{ 'Problem connecting Last.fm. The API key may be invalid.' | translate }}"
					translate>(Failed to connect Last.fm)</span>
				<p ng-if="artistBio" collapsible-html="artistBio" on-expand="adjustFixedPositions"></p>
				<dl>
					<dt ng-if="artistTags" translate>Tags</dt>
					<dd ng-if="artistTags" ng-bind-html="artistTags"></dd>

					<dt ng-if="similarArtistsInLib.length || similarArtistsNotInLib" translate>Similar to</dt>
					<dd ng-if="similarArtistsInLib.length || similarArtistsNotInLib" class="similar-artists">
						<span class="in-lib-artist clickable" ng-repeat="similarArtist in similarArtistsInLib"
							ng-click="onClickKnownArtist(similarArtist.id)">{{ similarArtist.name }}<button class="icon-info"></button
							><span class="separator" ng-if="!$last || similarArtistsNotInLib">,  </span>
						</span>
						<span ng-bind-html="similarArtistsNotInLib"></span>
						<span class="show-all" ng-if="!allSimilarShown && !allSimilarLoading" ng-click="onShowAllSimilar()" translate>Show all…</span>
						<div class="icon-loading inline" ng-if="allSimilarLoading"></div>
					</dd>

					<dt ng-if="mbid" translate>MusicBrainz</dt>
					<dd ng-if="mbid" ng-bind-html="mbid"></dd>
				</dl>
			</div>
		</div>

		<div class="tab playlist-area" id="albumsTabView" ng-show="selectedTab=='albums'">
			<div ng-if="!loading && artist.albums.length">
				<h2 translate translate-params-artist="artist.name">Albums by {{artist}}</h2>
				<track-list
					tracks="artist.albums"
					get-track-data="getAlbumData"
					play-track="onAlbumClick"
					show-track-details="showAlbumDetails"
					get-draggable="getAlbumDraggable"
					track-id-prefix="'sidebar-album'"
					content-type="'album'"
				></track-list>
			</div>
			<div ng-if="!loading && featuredAlbums.length">
				<h2 translate translate-params-artist="artist.name">{{artist}} appears on albums</h2>
				<track-list
					tracks="featuredAlbums"
					get-track-data="getAlbumData"
					play-track="onAlbumClick"
					show-track-details="showAlbumDetails"
					get-draggable="getAlbumDraggable"
					track-id-prefix="'sidebar-album'"
					content-type="'album'"
				></track-list>
			</div>
		</div>

		<div class="tab playlist-area" id="tracksTabView" ng-show="selectedTab=='tracks'">
			<div ng-if="!loading">
				<h2 translate translate-params-artist="artist.name">Tracks by {{artist}}</h2>
				<track-list
					tracks="artistTracks"
					get-track-data="getTrackData"
					play-track="onTrackClick"
					show-track-details="showTrackDetails"
					get-draggable="getTrackDraggable"
					track-id-prefix="'sidebar-track'"
					content-type="'song'"
				></track-list>
			</div>
		</div>
	</div>

	<div class="icon-loading" ng-show="loading"></div>

</div>
