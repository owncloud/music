<div id="emptycontent" ng-hide="toScan || scanning || loading || artists">
	<div class="icon-audio svg"></div>
	<h2 translate>No music found</h2>
	<p translate>Upload music in the files app to listen to it here</p>
</div>

<img id="updateData" ng-show="updateAvailable"
	 class="svg clickable" src="<?php p(OCP\image_path('music', 'repeat.svg')) ?>"  ng-click="update()"
	 alt  ="{{ 'New music available. Click here to reload the music library.' | translate }}"
	 title="{{ 'New music available. Click here to reload the music library.' | translate }}" >

<div id="toScan" ng-show="toScan" class="emptycontent clickable" ng-click="processNextScanStep(0)">
	<div class="icon-audio svg"></div>
	<h2 translate>New music available</h2>
	<p translate>Click here to start the scan</p>
</div>

<div id="scanning" class="emptycontent" ng-show="scanning">
	<div class="icon-loading svg"></div>
	<h2 translate>Scanning music …</h2>
	<p translate>{{ scanningScanned }} of {{ scanningTotal }}</p>
</div>

<div class="artist-area" ng-repeat="artist in artists | orderBy:'name'" ng-init="letter = artist.name.substr(0,1).toUpperCase()">
	<span id="{{ letter }}" ng-show="letterAvailable[letter]"></span> <!-- TODO: use ng-if - introduced in 1.1.5 -->
	<h1 id="{{ 'artist-' + artist.id }}" ng-click="playArtist(artist)">{{ artist.name }} <img class="play svg" alt="{{ 'Play' | translate }}"
		src="<?php p(OCP\image_path('music', 'play-big.svg')) ?>" /></h1>
	<div class="album-area" ng-repeat="album in artist.albums | orderBy:['year', 'name', 'disk']">
		<h2 id="{{ 'album-' + album.id }}" ng-click="playAlbum(album)" title="{{ album.name }} ({{ album.year }})"><div>{{ album.name }}
			<span ng-show="album.year" class="muted">({{ album.year }})</span></div>
		</h2>
		<div ng-click="playAlbum(album)" class="albumart" cover="{{ album.cover }}" albumart="{{ album.name }}"></div>
		<img ng-click="playAlbum(album)" class="play overlay svg" alt="{{ 'Play' | translate }}"
			src="<?php p(OCP\image_path('music', 'play-big.svg')) ?>" />
		<!-- variable "limit" toogles length of track list for each album -->
		<ul class="track-list" ng-init="limit.count = 5; trackcount = album.tracks.length">
			<li id="{{ 'track-' + track.id }}" 
				ng-click="playTrack(track)"
				ui-draggable="true" drag="track"
				ng-repeat="track in album.tracks | orderBy:'number' | limitTo:limit.count"
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
				ng-hide="trackcount <= limit.count || limit.count != 5"
				>Show all {{ trackcount }} songs ...</li>
			<li class="muted more-less"
				ng-click="limit.count = 5"
				ng-hide="limit.count == 5" translate>Show less ...</li>
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
