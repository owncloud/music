
<ul ng-controller="PlaylistController" ng-init="getPlaylist(currentPlaylist)">
	Inside playlist "{{currentPlaylistName}}":
	<ul>
		<li ng-click="play('track', song)" ng-repeat="song in currentPlaylistSongs"><a href="#/playlist/{{currentPlaylist}}"><img class="play svg" alt="{{ 'Play' | translate }}" src="<?php p(OCP\image_path('music', 'play-big.svg')) ?>"
		ng-class="{playing: currentTrack.id == song.id}" />Song {{song.id}}: {{song.title}}</a></li>
	</ul>

</ul>
