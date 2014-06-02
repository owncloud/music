<div ng-controller="PlaylistController">
	      <input type="text"
			ng-model="id"
			placeholder="<?php p($l->t('Id')); ?>"
			name="id"
			value="{{cPlistId}}">
		<input type="text"
			ng-model="newName"
			placeholder="<?php p($l->t('Name')); ?>"
			name="name"
			value="{{cPlistN}}"
			autofocus>
		<input type="text"
			ng-model="songs"
			placeholder="<?php p($l->t('Songs')); ?>"
			name="songs"
			value="{{cPlistSongs}}"
			autofocus>
		<button title="<?php p($l->t('Update')); ?>"
			class="primary"
			ng-click="updatePlaylist(id, newName, songs)"><?php p($l->t('Update')); ?></button>

</div>
<ul ng-controller="PlaylistController">
	<ul>
		<li ng-click="play('track', song)" ng-repeat="song in playlistSongs"><a href="#/playlist/{{currentPlaylist}}"><img class="play svg" alt="{{ 'Play' | translate }}" src="<?php p(OCP\image_path('music', 'play-big.svg')) ?>"
		ng-class="{playing: currentTrack.id == song.id}" />Song {{song.id}}: {{song.title}}</a></li>
	</ul>

</ul>
