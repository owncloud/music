<div ng-hide="artists" id="emptycontent">
	<span ng-hide="loading" translate>Nothing in here. Upload your music!</span>
</div>

<div id="scanning" ng-show="scanning">
	<span translate>Scanning ... </span> {{ scanningScanned }}/{{ scanningTotal }}
</div>

<div class="artist-area" ng-repeat="artist in artists | orderBy:'name'" ng-init="letter = artist.name.substr(0,1).toUpperCase()">
	<span id="{{ letter }}" ng-show="letterAvailable[letter]"></span> <!-- TODO: use ng-if - introduced in 1.1.5 -->
	<h1 ng-click="playArtist(artist)">{{ artist.name }} <img class="play svg" alt="{{ 'Play' | translate }}"
		src="<?php p(OCP\image_path('music', 'play-big.svg')) ?>" /></h1>
	<div class="album-area" ng-repeat="album in artist.albums | orderBy:'year'">
		<h2 ng-click="playAlbum(album)" title="{{ album.name }} ({{ album.year}})"><div>{{ album.name }}
			<span ng-show="album.year" class="muted">({{ album.year }})</span></div>
		</h2>
		<div ng-click="playAlbum(album)" class="albumart" cover="{{ album.cover }}" albumart="{{ album.name }}"></div>
		<img ng-click="playAlbum(album)" class="play overlay svg" alt="{{ 'Play' | translate }}"
			src="<?php p(OCP\image_path('music', 'play-big.svg')) ?>" />
		<!-- variable "limit" toogles length of track list for each album -->
		<ul class="track-list" ng-init="limit.count = 5; trackcount = album.tracks.length">
			<li ng-click="playTrack(track)"
				ng-repeat="track in album.tracks | orderBy:'number' | limitTo:limit.count" title="{{ track.title }}">
				<img class="play svg" alt="{{ 'Play' | translate }}" src="<?php p(OCP\image_path('music', 'play-big.svg')) ?>"
					ng-class="{playing: currentTrack.id == track.id}" />
				<span ng-show="track.number" class="muted">{{ track.number }}.</span>
				{{ track.title }}
			</li>
			<li class="muted" translate translate-n="trackcount"
				translate-plural="Show all {{ trackcount }} songs ..."
				ng-click="limit.count = trackcount"
				ng-hide="trackcount <= limit.count || limit.count != 5"
				>Show all {{ trackcount }} songs ...</li>
			<li class="muted"
				ng-click="limit.count = 5"
				ng-hide="limit.count == 5" translate>Show less ...</li>
		</ul>
	</div>
</div>
