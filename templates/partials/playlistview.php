<div class="playlist-area">
	<h1 ng-click="playAll(currentPlaylist.id)">{{currentPlaylist.name}}</h1>
	<ul class="track-list">
		<li ng-click="playTrack(song)" ng-repeat="song in currentPlaylist.trackIds">
			<img class="play svg" alt="{{ 'Play' | translate }}" src="<?php p(OCP\image_path('music', 'play-big.svg')) ?>"
				ng-class="{playing: currentTrack.id == song.id}" />
			<span class="muted">{{ $index + 1 }}.</span>
			{{ song.artistName }} - {{song.title}}
			<a class="action" ng-click="removeTrack(song)">x</a>
		</li>
	</ul>

</div>
