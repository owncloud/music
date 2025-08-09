<div class="playlist-area folder-area flat-list-view" ng-class="{'matched': folder.matched}" id="folder-{{ folder.id }}">
	<span class="icon icon-folder"></span>
	<img class="caret-overlay svg"
		 ng-if="!searchMode"
		 src="<?php \OCA\Music\Utility\HtmlUtil::printSvgPath('expand') ?>"
		 ng-class="{ 'flip-vertically': folder.expanded }"
		 ng-click="folder.expanded = !folder.expanded" />
	<list-heading
		level="2"
		heading="folder.name || 'Music library' | translate"
		on-click="onFolderTitleClick"
		get-draggable="getFolderDraggable"
		model="folder"
		tooltip="folder.path"
		show-play-icon="true">
	</list-heading>
	<ul class="subfolders" ng-if="folder.expanded || folder.matched">
		<li ng-repeat="folder in folder.subfolders" ng-include="'foldertreenode.html'"></li>
	</ul>
	<track-list
		ng-if="folder.expanded || folder.matched"
		tracks="folder.tracks"
		get-track-data="getTrackData"
		play-track="onTrackClick"
		show-track-details="showTrackDetails"
		get-draggable="getTrackDraggable"
		collapse-limit="10">
	</track-list>
</div>