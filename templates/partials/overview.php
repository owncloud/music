<div id="overview"  ng-show="!loading">
	<div bindonce class="artist-area" ng-repeat="artist in artists | limitTo: incrementalLoadLimit" ng-init="letter = artist.name.substr(0,1).toUpperCase()">
		<span bo-id="letter" bo-if="letterAvailable[letter]"></span>
		<h1 bo-id="'artist-' + artist.id">
			<span ng-click="playArtist(artist)" ui-draggable="true" drag="getDraggable('artist', artist)">
				<span bo-text="artist.name"></span>
				<img class="play svg" bo-alt="'Play' | translate" src="<?php p(OCP\image_path('music', 'play-big.svg')) ?>"/>
			</span>
		</h1>
		<div bindonce class="album-area" ng-repeat="album in artist.albums">
			<h2 bo-id="'album-' + album.id">
				<div ng-click="playAlbum(album)"
					bo-title="album.name + ((album.year) ? ' (' + album.year + ')' : '')"
					ui-draggable="true" drag="getDraggable('album', album)">
					<span bo-text="album.name"></span> <span bo-if="album.year" class="muted" bo-text="'(' + album.year + ')'"></span>
				</div>
			</h2>
			<div ng-click="playAlbum(album)" class="albumart" cover="{{ album.cover }}" albumart="{{ album.name }}"></div>
			<img ng-click="playAlbum(album)" class="play overlay svg" alt="{{ 'Play' | translate }}"
				src="<?php p(OCP\image_path('music', 'play-big.svg')) ?>" />
			<!-- variable "limit" toogles length of track list for each album -->
			<ul class="track-list" ng-init="trackcount = album.tracks.length; limit.count = (trackcount == 6) ? 6 : 5">
				<li bindonce
					bo-id="'track-' + track.id" 
					ng-click="playTrack(track)"
					ui-draggable="true" drag="getDraggable('track', track)"
					ng-repeat="track in album.tracks | limitTo:limit.count"
					bo-title="track.title + ((track.artistId != track.albumArtistId) ? '  (' + track.artistName + ')' : '')">
					<img class="play svg" bo-alt="'Play' | translate" src="<?php p(OCP\image_path('music', 'play-big.svg')) ?>"
						ng-class="{playing: currentTrack.id == track.id}" />
					<span bo-if="track.number" class="muted" bo-text="track.number + '.'"></span>
					<span bo-text="track.title"></span>
					<span bo-if="track.artistId != track.albumArtistId" class="muted" bo-text="'&nbsp;(' + track.artistName +')'"></span>
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
