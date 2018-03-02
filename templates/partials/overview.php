<div id="overview"  ng-show="!loading && !loadingCollection">
	<div bindonce class="artist-area" ng-repeat="artist in artists | limitTo: incrementalLoadLimit" ng-init="letter = artist.name.substr(0,1).toUpperCase()">
		<span bo-id="letter" bo-if="letterAvailable[letter]"></span>
		<h1 bo-id="'artist-' + artist.id">
			<span ng-click="playArtist(artist)" ui-draggable="true" drag="getDraggable('artist', artist)">
				<span bo-text="artist.name"></span>
				<img class="play svg" bo-alt="'Play' | translate" src="<?php p(OCP\Template::image_path('music', 'play-big.svg')) ?>"/>
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
				 src="<?php p(OCP\Template::image_path('music', 'play-big.svg')) ?>" />
			<track-list
					more-text="'Show all {{ album.tracks.length }} songs …' | translate"
					less-text="'Show less …' | translate"
			/>
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
