<li ng-class="{active: $parent.currentView == destination, playlist: playlist}">
	<a ng-click="$parent.navigateTo(destination)" ng-hide="playlist && $parent.showEditForm == playlist.id">
		<img class="play svg" src="<?php p(OCP\image_path('music', 'play-big.svg')) ?>"
			ng-class="{playing: $parent.playingView == destination}" />
		<span>{{ text }}</span>
	</a>
	<div ng-if="playlist && $parent.showEditForm == playlist.id">
		<input type="text" class="edit-list" ng-enter="$parent.$parent.commitEdit(playlist)" ng-model="playlist.name" />
		<button class="svg action icon-checkmark" ng-click="$parent.$parent.commitEdit(playlist)"></button>
	</div>
	<div class="actions" ng-if="playlist">
		<button class="svg action icon-delete" ng-hide="$parent.$parent.showEditForm == playlist.id"
			ng-click="$parent.$parent.remove(playlist)"
			alt="{{ 'Delete' | translate }}" title="{{ 'Delete' | translate }}"></button>
		<button class="svg action icon-edit" ng-hide="$parent.$parent.showEditForm == playlist.id"
			ng-click="$parent.$parent.startEdit(playlist)"
			alt="{{ 'Rename' | translate }}" title="{{ 'Rename' | translate }}"></button>
	</div>
</li>