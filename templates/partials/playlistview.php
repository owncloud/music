
<ul ng-controller="PlaylistController">
	Inside playlist "{{currentPlaylist.name}}" - <a ng-click="playAll(currentPlaylist.id)">Play All</a>:
	<ul>
		<li ng-repeat="song in currentPlaylist.trackIds">
			<a href="#/playlist/{{currentPlaylist.id}}"><img class="play svg" alt="{{ 'Play' | translate }}" src="<?php p(OCP\image_path('music', 'play-big.svg')) ?>" ng-class="{playing: currentTrack.id == song.id}" /></a>
			<a ng-click="playTrack(song)">Song {{song.id}}: {{song.title}}</a>
			<a class="action" ng-click="removeTrack(song)">x</a>
		</li>
	</ul>

</ul>
