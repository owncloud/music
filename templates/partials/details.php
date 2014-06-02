<div ng-controller="PlaylistController">

	<input type="text"
		ng-model="plistName"
		placeholder="<?php p($l->t('Name')); ?>"
		name="name"
		autofocus>
	<button title="<?php p($l->t('Add')); ?>"
			class="primary"
			ng-click="addPlaylist(plistName)"><?php p($l->t('Add')); ?></button>
	<input type="text"
		ng-model="id"
		placeholder="<?php p($l->t('Id')); ?>"
		name="id"
		autofocus>
	<button title="<?php p($l->t('Remove')); ?>"
			class="primary"
			ng-click="removePlaylist(id)"><?php p($l->t('Remove')); ?></button>

	<button title="<?php p($l->t('GET')); ?>"
			class="primary"
			ng-click="getPlaylists()"><?php p($l->t('GET')); ?></button>

</div>

<div>
	<ul ng-controller="PlaylistController">
		<li ng-repeat="playlist in playlists">
			<a href="#/playlist/{{playlist.id}}">{{playlist.name}}</a>
			<img alt="{{ 'Delete' | translate }}" src="<?php p(OCP\image_path('core', 'actions/close.svg')) ?>" />
		</li>
	</ul>
</div>
