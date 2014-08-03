
<ul ng-controller="PlaylistController" ng-init="getPlaylist(currentPlaylist)">
	Inside playlist "{{currentPlaylistName}}" - <a ng-click="playAll()">Play All</a>:
	<ul>
		<li ng-repeat="song in currentPlaylistSongs"><a href="#/playlist/{{currentPlaylist}}"><img class="play svg" alt="{{ 'Play' | translate }}" src="<?php p(OCP\image_path('music', 'play-big.svg')) ?>"
		ng-class="{playing: currentTrack.id == song.id}" /><a ng-click="playTrack(song)">Song {{song.id}}: {{song.title}}</a> <a ng-click="removeTrack(song.id)">x</a></li>
	</ul>

</ul>
