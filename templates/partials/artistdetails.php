<div id="artist-details" class="sidebar-content" ng-controller="ArtistDetailsController" ng-if="contentType=='artist'">

	<div class="albumart" ng-show="!loading">
		<span ng-if="!artAvailable" title="{{ noImageHint }}"
			translate>(no artist image available)</span>
	</div>

	<h1 ng-show="!loading">{{artist.name}}</h1>

	<dl ng-show="!loading">
		<dt translate>Number of albums</dt>
		<dd>{{ artist.albums.length }}</dd>

		<dt translate>Number of tracks on albums</dt>
		<dd>{{ artistAlbumTrackCount }}</dd>

		<dt translate>Number of performed tracks</dt>
		<dd>{{ artistTrackCount }}</dd>
	</dl>

	<div id="lastfm-info" ng-show="!loading && lastfmInfo">
		<span ng-if="!lastfmInfo.api_key_set"
			title="{{ 'Admin may set up the Last.FM API key to show artist biography here. See the Settings view for details.' | translate }}"
			translate>(Last.FM has not been set up)</span>
		<span ng-if="lastfmInfo.api_key_set && !lastfmInfo.connection_ok"
			title="{{ 'Problem connecting Last.fm. The API key may be invalid.' | translate }}"
			translate>(Failed to connect Last.fm)</span>
		<p ng-bind-html="lastfmInfo.artist.bio.content"></p>
	</div> 

	<div class="icon-loading" ng-show="loading"></div>

</div>
