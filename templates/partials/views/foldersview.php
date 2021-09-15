<div class="view-container" id="folders-area" ng-show="!loading && !loadingCollection">

	<!-- Hierarchical tree layout -->
	<div class="folder-tree" ng-if="!foldersFlatLayout && rootFolder" ng-init="folder = rootFolder" ng-include="'foldertreenode.html'"></div>

	<!-- Flat layout -->
	<div ng-if="foldersFlatLayout" class="playlist-area folder-area flat-list-view" id="folder-{{ ::folder.id }}" in-view-observer
		in-view-observer-margin="1000"
		ng-repeat="folder in folders | limitTo: incrementalLoadLimit"
	>
		<list-heading
				level="2"
				heading="folder.name || '(library root)' | translate"
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

	<alphabet-navigation ng-if="folders && folders.length && foldersFlatLayout" item-count="folders.length"
		get-elem-title="getFolderName" get-elem-id="getFolderElementId" scroll-to-target="scrollToItem">
	</alphabet-navigation>

</div>
