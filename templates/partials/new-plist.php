<div ng-controller="PlaylistController">

	<input type="text"
		ng-model="plistName"
		placeholder="<?php p($l->t('Name')); ?>"
		name="name"
		autofocus>
	<button title="<?php p($l->t('Add')); ?>"
			class="primary"
			ng-click="addPlaylist(plistName)"><?php p($l->t('Add')); ?></button>
</div>
