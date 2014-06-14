<ul ng-controller="PlaylistController">
	<ul>
		<li ng-click="play('track', song)" ng-repeat="song in playlistSongs"><a href="#/playlist/{{currentPlaylist}}"><img class="play svg" alt="{{ 'Play' | translate }}" src="<?php p(OCP\image_path('music', 'play-big.svg')) ?>"
		ng-class="{playing: currentTrack.id == song.id}" />Song {{song.id}}: {{song.title}}</a></li>
	</ul>

</ul>
