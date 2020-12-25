<li class="music-navigation-item" ng-class="{active: $parent.currentView == destination, playlist: playlist}">
	<div class="music-navigation-item-content" ng-click="$parent.navigateTo(destination)"
		ng-class="{current: $parent.playingView == destination, playing: $parent.playing}" 
	>
		<div class="play-pause-button svg" ng-hide="playlist && $parent.showEditForm == playlist.id"
			ng-class="icon ? 'icon-' + icon : ''"
			ng-click="$parent.togglePlay(destination, playlist); $event.stopPropagation()"
			title="{{ (($parent.playingView == destination && $parent.playing) ? 'Pause' : 'Play list') | translate }}"
		>
			<div class="play-pause"></div>
		</div>
		<span ng-hide="playlist && $parent.showEditForm == playlist.id">{{ text }}</span>
		<div ng-show="playlist && $parent.showEditForm == playlist.id">
			<div class="input-container">
				<input type="text" class="edit-list" maxlength="256"
					ng-enter="$parent.$parent.commitEdit(playlist)" ng-model="playlist.name"/>
			</div>
			<button class="action icon-checkmark app-navigation-noclose"
				ng-class="{ disabled: playlist.name.length == 0 }" 
				ng-click="$parent.$parent.commitEdit(playlist); $event.stopPropagation()"></button>
		</div>
		<div class="actions" title="" ng-show="playlist && $parent.showEditForm == null">
			<span class="icon-more" ng-show="!playlist.busy"
				ng-click="$parent.$parent.onPlaylistMoreButton(playlist); $event.stopPropagation()"></span>
			<span class="icon-loading-small" ng-show="playlist.busy"></span>
			<div class="popovermenu bubble" ng-show="$parent.$parent.popupShownForPlaylist == playlist">
				<ul>
					<li ng-click="$parent.$parent.showDetails(playlist)">
						<a class="icon-details"><span translate>Details</span></a>
					</li>
					<li ng-click="$parent.$parent.startEdit(playlist)" class="app-navigation-noclose">
						<a class="icon-rename"><span translate>Rename</span></a>
					</li>
					<li ng-click="$parent.$parent.exportToFile(playlist)">
						<a class="icon-to-file"><span translate>Export to file</span></a>
					</li>
					<li ng-click="$parent.$parent.importFromFile(playlist)">
						<a class="icon-from-file"><span translate>Import from file</span></a>
					</li>
					<li ng-click="$parent.$parent.remove(playlist)">
						<a class="icon-delete"><span translate>Delete</span></a>
					</li>
				</ul>
			</div>
		</div>
	</div>
</li>