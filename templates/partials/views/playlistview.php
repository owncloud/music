<div id="playlist-view" class="view-container playlist-area" ng-show="!loading && !loadingCollection">
	<h1>
		<span ng-click="onHeaderClick()">
			<span>{{ playlist.name }}</span>
			<img class="play svg" alt="{{ 'Play' | translate }}" src="<?php \OCA\Music\Utility\HtmlUtil::printSvgPath('play-big') ?>"/>
		</span>
	</h1>
	<ul class="track-list">
		<li ng-repeat="entry in tracks | limitTo: incrementalLoadLimit"
			ng-init="song = entry.track"
			id="{{ 'playlist-track-' + $index }}"
			data-track-id="{{ ::song.id }}"
			ui-on-drop="reorderDrop($data, $index)"
			ui-on-drag-enter="updateHoverStyle($index)"
			drop-validate="allowDrop($data, $index)"
			drag-hover-class="drag-hover"
		>
			<div class="playlist-item-info" ng-click="onTrackClick($index)" ui-draggable="true" drag="getDraggable($index)"
				ng-class="{current: getCurrentTrackIndex() === $index, playing: playing}"
			>
				<span class="ordinal muted">{{ $index + 1 }}.</span>
				<div class="albumart" albumart="::song.album"></div>
				<div class="play-pause overlay"></div>
				<div class="title-lines">
					<div>{{ ::song.title }}</div>
					<div class="muted">{{ ::song.artist.name }}</div>
				</div>
			</div>
			<button class="action icon-details" ng-click="showTrackDetails(song.id)"
				alt="{{ 'Details' | translate }}" title="{{ 'Details' | translate }}"
				ng-if="song.type != 'error'"></button>
			<button class="action icon-close" ng-click="removeTrack($index)"
				alt="{{ 'Remove' | translate }}" title="{{ 'Remove track from playlist' | translate }}"></button>
		</li>
	</ul>

	<div class="emptycontent" ng-show="playlist.tracks.length == 0 && !scanning && !toScan && !noMusicAvailable">
		<div class="icon-audio svg"></div>
		<div>
			<h2 translate>No tracks</h2>
			<p translate>Add tracks with drag and drop from Albums or other playlists</p>
		</div>
	</div>

</div>
