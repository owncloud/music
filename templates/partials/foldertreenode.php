<div class="playlist-area folder-area flat-list-view" id="folder-{{ ::folder.id }}">
	<span class="icon icon-folder" ng-click="folder.expanded = !folder.expanded"></span>
	<img class="overlay svg"
		 src="<?php \OCA\Music\Utility\HtmlUtil::printSvgPath('expand') ?>"
		 ng-class="{ 'flip-vertically': folder.expanded }"
		 ng-click="folder.expanded = !folder.expanded" />
	<list-heading
		level="2"
		heading="folder.name || '(root folder)' | translate"
		on-click="onFolderTitleClick"
		get-draggable="getFolderDraggable"
		model="folder"
		tooltip="folder.path"
		show-play-icon="true">
	</list-heading>
	<ul class="subfolders" ng-if="folder.expanded">
		<li ng-repeat="folder in folder.subfolders" ng-include="'foldertreenode.html'"></li>
	</ul>
	<track-list
		ng-if="folder.expanded"
		tracks="folder.tracks"
		get-track-data="getTrackData"
		play-track="onTrackClick"
		show-track-details="showTrackDetails"
		get-draggable="getTrackDraggable"
		collapse-limit="10">
	</track-list>
</div>