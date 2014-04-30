			<ul ng-controller="PlaylistController">
				<li><a href="#/" translate>All</a></li>
				<li class="app-navigation-separator"><a href="#/" translate>Favorites</a></li>
				<li><a href="#/new" translate>+ New Playlist</a></li>
				<ul>
					<li ng-click="play('track', song)" ng-repeat="song in playlistSongs"><a href="#/playlist/{{currentPlaylist}}"><img class="play svg" alt="{{ 'Play' | translate }}" src="<?php p(OCP\image_path('music', 'play-big.svg')) ?>"
					ng-class="{playing: currentTrack.id == song.id}" />Song {{song.id}}: {{song.title}}</a></li>
				</ul>
			</ul>
