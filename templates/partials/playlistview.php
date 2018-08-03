<div class="playlist-area" ng-show="!loading && !loadingCollection">
	<h1>
		<span ng-click="onHeaderClick()">
			<span>{{ playlist.name }}</span>
			<img class="play svg" alt="{{ ::('Play' | translate) }}" src="<?php p(OCP\Template::image_path('music', 'play-big.svg')) ?>"/>
		</span>
	</h1>
	<ul class="track-list">
		<li ng-repeat="entry in tracks | limitTo: incrementalLoadLimit"
			ng-init="song = entry.track"
			id="{{ 'playlist-track-' + $index }}"
			ui-on-drop="reorderDrop($data, $index)"
			ui-on-drag-enter="updateHoverStyle($index)"
			drop-validate="allowDrop($data, $index)"
			drag-hover-class="drag-hover">
			<div>
				<div ng-click="onTrackClick($index)" ui-draggable="true" drag="getDraggable($index)"
					ng-class="{current: getCurrentTrackIndex() === $index, playing: playing}"
				>
					<div class="play-pause" />
					<span class="muted">{{ $index + 1 }}.</span>
					<div>{{ ::song.artistName }} - {{ ::song.title }}</div>
				</div>
				<button class="svg action icon-details" ng-click="showSidebar(song.id)"
					alt="{{ ::('Details' | translate) }}" title="{{ ::('Details' | translate) }}"></button>
				<button class="svg action icon-close" ng-click="removeTrack($index)"
					alt="{{ ::('Remove' | translate) }}" title="{{ ::('Remove track from playlist' | translate) }}"></button>
			</div>
		</li>
	</ul>

	<div class="emptycontent" ng-show="playlist.tracks.length == 0 && !scanning && !toScan && !noMusicAvailable">
		<div class="icon-audio svg"></div>
		<h2 translate>No tracks</h2>
		<p translate>Add tracks with drag and drop from Albums or other playlists</p>
	</div>

</div>
