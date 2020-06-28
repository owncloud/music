<div id="artist-details" class="sidebar-content" ng-controller="ArtistDetailsController" ng-if="contentType=='artist'">

	<div class="albumart" ng-show="!loading">
		<span ng-if="!artAvailable"
			title="Upload image named '{{artist.name}}.*' to anywhere within your library path to see it here."
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

	<div class="icon-loading" ng-show="loading"></div>

</div>
