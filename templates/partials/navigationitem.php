<li class="music-navigation-item" ng-class="{active: $parent.currentView == destination, playlist: playlist}">
	<a ng-click="$parent.navigateTo(destination)" ng-hide="playlist && $parent.showEditForm == playlist.id"
		ng-class="{current: $parent.playingView == destination, playing: $parent.playing}" 
	>
		<div class="play-pause-button"
			ng-click="$parent.togglePlay(destination, playlist); $event.stopPropagation()"
			title="{{ (($parent.playingView == destination && $parent.playing) ? 'Pause' : 'Play list') | translate }}"
		>
			<div class="play-pause" />
		</div>
		<span>{{ text }}</span>
	</a>
	<div ng-if="playlist && $parent.showEditForm == playlist.id">
		<input type="text" class="edit-list" ng-enter="$parent.$parent.commitEdit(playlist)" ng-model="playlist.name" />
		<button class="svg action icon-checkmark" ng-click="$parent.$parent.commitEdit(playlist)"></button>
	</div>
	<div class="actions" ng-if="playlist && $parent.showEditForm != playlist.id">
		<button class="svg action icon-delete"
			ng-click="$parent.$parent.remove(playlist)"
			alt="{{ 'Delete' | translate }}" title="{{ 'Delete' | translate }}"></button>
		<button class="svg action icon-edit"
			ng-click="$parent.$parent.startEdit(playlist)"
			alt="{{ 'Rename' | translate }}" title="{{ 'Rename' | translate }}"></button>
	</div>
</li>