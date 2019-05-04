<div id="folders-area" ng-show="!loading && !loadingCollection">
	<div class="playlist-area" id="folder-{{ ::folder.id }}" ng-repeat="folder in folders">
		<h1>
			<span ng-click="onFolderTitleClick(folder)"
					title="{{ ::folder.name }}"
					ui-draggable="true" drag="getDraggable('folder', folder)">
				<span>{{ ::folder.name }}</span>
				<img class="play svg" alt="{{ 'Play' | translate }}" src="<?php p(OCP\Template::image_path('music', 'play-big.svg')) ?>"/>
			</span>
		</h1>
		<track-list
				tracks="folder.tracks"
				get-track-data="getTrackData"
				play-track="onTrackClick"
				show-track-details="showSidebar"
				get-draggable="getTrackDraggable"
				collapse-limit="10"
				more-text="'Show all {{ folder.tracks.length }} songs …' | translate"
				less-text="'Show less …' | translate"
				details-text="'Details' | translate">
		</track-list>
	</div>

	<alphabet-navigation ng-if="folders && folders.length" item-count="folders.length"
		get-elem-title="getFolderName" get-elem-id="getFolderElementId" scroll-to-target="scrollToItem">
	</alphabet-navigation>
</div>
