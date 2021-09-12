<div class="playlist-area folder-area flat-list-view" id="folder-{{ ::folder.id }}">
	<list-heading
		level="2"
		heading="folder.name || '(root folder)' | translate"
		on-click="onFolderTitleClick"
		get-draggable="getFolderDraggable"
		model="folder"
		tooltip="folder.path"
		show-play-icon="true">
	</list-heading>
	<ul class="subfolders">
		<li ng-repeat="folder in folder.subfolders" ng-include="'foldertreenode.html'"></li>
	</ul>
	<track-list
		tracks="folder.tracks"
		get-track-data="getTrackData"
		play-track="onTrackClick"
		show-track-details="showTrackDetails"
		get-draggable="getTrackDraggable"
		collapse-limit="10">
	</track-list>
</div>