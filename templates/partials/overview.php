<div id="overview"  ng-show="!loading">
	<div class="artist-area" ng-repeat="artist in artists | limitTo: incrementalLoadLimit" ng-init="letter = artist.name.substr(0,1).toUpperCase()">
		<span id="{{ letter }}" ng-if="letterAvailable[letter]"></span>
		<h1 id="{{ 'artist-' + artist.id }}">
			<span ng-click="playArtist(artist)" ui-draggable="true" drag="getDraggable('artist', artist)">
				{{ artist.name }}
				<img class="play svg" alt="{{ 'Play' | translate }}" src="<?php p(OCP\image_path('music', 'play-big.svg')) ?>"/>
			</span>
		</h1>
		<div class="album-area" ng-repeat="album in artist.albums">
			<h2 id="{{ 'album-' + album.id }}">
				<div ng-click="playAlbum(album)"
					title="{{ album.name + ((album.year) ? ' (' + album.year + ')' : '') }}"
					ui-draggable="true" drag="getDraggable('album', album)">
					{{ album.name }} <span ng-show="album.year" class="muted">({{ album.year }})</span>
				</div>
			</h2>
			<div ng-click="playAlbum(album)" class="albumart" cover="{{ album.cover }}" albumart="{{ album.name }}"></div>
			<img ng-click="playAlbum(album)" class="play overlay svg" alt="{{ 'Play' | translate }}"
				src="<?php p(OCP\image_path('music', 'play-big.svg')) ?>" />
			<!-- variable "limit" toogles length of track list for each album -->
			<ul class="track-list" ng-init="trackcount = album.tracks.length; limit.count = (trackcount == 6) ? 6 : 5">
				<li id="{{ 'track-' + track.id }}" 
					ng-click="playTrack(track)"
					ui-draggable="true" drag="getDraggable('track', track)"
					ng-repeat="track in album.tracks | limitTo:limit.count"
					title="{{ track.title + ((track.artistId != track.albumArtistId) ? '  (' + track.artistName + ')' : '') }}">
					<img class="play svg" alt="{{ 'Play' | translate }}" src="<?php p(OCP\image_path('music', 'play-big.svg')) ?>"
						ng-class="{playing: currentTrack.id == track.id}" />
					<span ng-show="track.number" class="muted">{{ track.number }}.</span>
					{{ track.title }}
					<span ng-if="track.artistId != track.albumArtistId" class="muted">&nbsp;({{ track.artistName }})</span>
				</li>
				<li class="muted more-less" translate translate-n="trackcount"
					translate-plural="Show all {{ trackcount }} songs ..."
					ng-click="limit.count = trackcount"
					ng-hide="trackcount <= 6 || limit.count > 6"
					>Show all {{ trackcount }} songs ...</li>
				<li class="muted more-less"
					ng-click="limit.count = 5"
					ng-hide="limit.count <= 6" translate>Show less ...</li>
			</ul>
		</div>
	</div>

	<div ng-show="artists" class="alphabet-navigation" ng-class="{started: started}" resize>
		<a du-smooth-scroll="{{ letter }}" offset="{{ scrollOffset() }}"
			ng-repeat="letter in letters" 
			ng-class="{available: letterAvailable[letter], filler: ($index % 2) == 1}">
			<span class="letter-content">{{ letter }}</span>
		</a>
	</div>
</div>
