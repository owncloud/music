<div class="view-container" id="folders-area" ng-show="!loading && !loadingCollection">
	<div class="playlist-area folder-area" id="folder-{{ ::folder.id }}" in-view-observer
		in-view-observer-margin="1000"
		ng-repeat="folder in folders | limitTo: incrementalLoadLimit"
	>
		<list-heading 
				level="1"
				heading="folder.name || '(root folder)' | translate"
				on-click="onFolderTitleClick"
				get-draggable="getFolderDraggable"
				model="folder"
				tooltip="folder.path"
				show-play-icon="true">
		</list-heading>
		<track-list
				tracks="folder.tracks"
				get-track-data="getTrackData"
				play-track="onTrackClick"
				show-track-details="showTrackDetails"
				get-draggable="getTrackDraggable"
				collapse-limit="10">
		</track-list>
	</div>

	<alphabet-navigation ng-if="folders && folders.length" item-count="folders.length"
		get-elem-title="getFolderName" get-elem-id="getFolderElementId" scroll-to-target="scrollToItem">
	</alphabet-navigation>
</div>
