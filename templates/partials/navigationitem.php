<li class="music-navigation-item" ng-class="{active: $parent.currentView == destination, playlist: playlist}">
	<a ng-click="$parent.navigateTo(destination)" ng-hide="playlist && $parent.showEditForm == playlist.id"
		ng-class="{current: $parent.playingView == destination, playing: $parent.playing}" 
	>
		<div class="play-pause-button"
			ng-click="$parent.togglePlay(destination, playlist); $event.stopPropagation()"
			title="{{ (($parent.playingView == destination && $parent.playing) ? 'Pause' : 'Play list') | translate }}"
		>
			<div class="play-pause"></div>
		</div>
		<span>{{ text }}</span>
	</a>
	<div ng-if="playlist && $parent.showEditForm == playlist.id">
		<div class="input-container">
			<input type="text" class="edit-list" maxlength="256"
				ng-enter="$parent.$parent.commitEdit(playlist)" ng-model="playlist.name"/>
		</div>
		<button class="action icon-checkmark"
			ng-class="{ disabled: playlist.name.length == 0 }" 
			ng-click="$parent.$parent.commitEdit(playlist)"></button>
	</div>
	<div class="actions" title="" ng-if="playlist && $parent.showEditForm == null">
		<span class="icon-more action" ng-click="$parent.$parent.onPlaylistMoreButton(playlist); $event.stopPropagation()"></span>
		<div class="popovermenu bubble" ng-show="$parent.$parent.popupShownForPlaylist == playlist">
			<ul>
				<li ng-click="$parent.$parent.showDetails(playlist)">
					<a class="icon-details"> <span translate>Details</span></a>
				</li>
				<li ng-click="$parent.$parent.startEdit(playlist)">
					<a class="icon-rename"><span translate>Rename</span></a>
				</li>
				<li ng-click="$parent.$parent.exportToFile(playlist)">
					<a class="icon-file"><span translate>Export to file</span></a>
				</li>
				<li ng-click="$parent.$parent.remove(playlist)">
					<a class="icon-delete"> <span translate>Delete</span></a>
				</li>
			</ul>
		</div>
	</div>
</li>