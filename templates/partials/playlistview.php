<div class="playlist-area">
	<h1 ng-click="playAll(currentPlaylist.id)">{{currentPlaylist.name}}</h1>
	<ul class="track-list">
		<li ng-repeat="song in currentPlaylist.trackIds">
			<div ng-click="playTrack(song)">
				<img class="play svg" alt="{{ 'Play' | translate }}" src="<?php p(OCP\image_path('music', 'play-big.svg')) ?>"
					ng-class="{playing: currentTrack.id == song.id}" />
				<span class="muted">{{ $index + 1 }}.</span>
				<div>{{ song.artistName }} - {{song.title}}</div>
			</div>
			<button class="svg action icon-close" ng-click="removeTrack(song)"
				alt="{{ 'Remove' | translate }}" title="{{ 'Remove track from playlist' | translate }}" />
		</li>
	</ul>

</div>
