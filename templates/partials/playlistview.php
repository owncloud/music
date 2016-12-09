<div class="playlist-area">
	<h1 ng-click="playAll()" ng-if="currentPlaylist">{{currentPlaylist.name}}</h1>
	<h1 ng-click="playAll()" ng-if="currentView == 'tracks'" translate>All tracks</h1>
	<ul class="track-list">
		<li ng-repeat="song in currentTracks">
			<div ng-click="playTrack(song)" ui-draggable="true" drag="song">
				<img class="play svg" alt="{{ 'Play' | translate }}" src="<?php p(OCP\image_path('music', 'play-big.svg')) ?>"
					ng-class="{playing: currentTrack.id == song.id}" />
				<span class="muted">{{ $index + 1 }}.</span>
				<div>{{ song.artistName }} - {{song.title}}</div>
			</div>
			<button class="svg action icon-close" ng-click="removeTrack(song)" ng-if="currentPlaylist"
				alt="{{ 'Remove' | translate }}" title="{{ 'Remove track from playlist' | translate }}" />
		</li>
	</ul>

</div>
