<div id="pledit_dialog" ng-controller="PlaylistController">

	<input type="hidden"
		  id="id"
		  value="<?php p($_['plist']['id']) ?>">
	<input type="text"
		placeholder="<?php p($l->t('Name')); ?>"
		name="name"
		id="name"
		value="<?php p($_['plist']['name']) ?>">
	<input type="text"
		placeholder="<?php p($l->t('Songs')); ?>"
		name="songs"
		id="songs"
		value="<?php p($_['plist']['songs']) ?>">
	<button title="<?php p($l->t('Update')); ?>"
		class="primary"
		id="updatePlaylist"><?php p($l->t('Update')); ?></button>

</div>
